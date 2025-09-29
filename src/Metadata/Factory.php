<?php
namespace Rebel\BCApi2\Metadata;

use Rebel\BCApi2\Metadata;
use SimpleXMLElement;

class Factory
{
    public static function fromXml(SimpleXMLElement $xml): Metadata
    {
        // Create metadata object
        $xml->registerXPathNamespace('edmx', 'http://docs.oasis-open.org/odata/ns/edmx');
        $xml->registerXPathNamespace('edm', 'http://docs.oasis-open.org/odata/ns/edm');

        // Extract Namespace
        $namespace = $xml->xpath('//edm:Schema/@Namespace')[0];
        $metadata = new Metadata($namespace);

        // Extract EnumTypes
        $enumTypes = $xml->xpath('//edm:Schema/edm:EnumType');
        foreach ($enumTypes as $enumType) {
            $name = (string)$enumType['Name'];
            $members = [];
            foreach ($enumType->Member as $member) {
                $members[ (int)$member['Value'] ] = (string)$member['Name'];
            }

            $metadata->addEnumType($name, $members);
        }

        // Extract EntityTypes
        $entityTypes = $xml->xpath('//edm:Schema/edm:EntityType');
        foreach ($entityTypes as $entityType) {
            $name = (string)$entityType['Name'];
            
            // Extract Primary Key
            $key = (string)$entityType->Key->PropertyRef['Name']; 

            // Extract Properties
            $properties = [];
            foreach ($entityType->Property as $property) {
                $properties[(string)$property['Name']] = new Metadata\Property(
                    (string)$property['Type'],
                    (string)$property['Nullable'] !== 'false',
                    (int)$property['MaxLength'] ?: null
                );
            }

            // Extract NavigationProperties
            $navigationProperties = [];
            foreach ($entityType->NavigationProperty as $navProperty) {
                $referentialConstraints = [];
                foreach ($navProperty->ReferentialConstraint as $referentialConstraint) {
                    $referentialConstraints[(string)$referentialConstraint['Property']] = (string)$referentialConstraint['ReferencedProperty'];
                }

                $navigationProperties[(string)$navProperty['Name']] = new Metadata\NavigationProperty(
                    (string)$navProperty['Type'],
                    (string)$navProperty['Partner'] ?: null,
                    $referentialConstraints
                );
            }

            $metadata->addEntityType(new EntityType($name, $key, $properties, $navigationProperties));
        }

        // Extract EntitySets
        $entitySets = $xml->xpath('//edm:Schema/edm:EntityContainer/edm:EntitySet');
        foreach ($entitySets as $entitySet) {
            $name = (string)$entitySet['Name'];
            $type = (string)$entitySet['EntityType'];
            $entityType = $metadata->getEntityType($type, true);

            $capabilities = [];
            foreach ($entitySet->Annotation as $annotation) {
                $key = $annotation['Term'] .'.'. $annotation->Record->PropertyValue['Property'];
                $capabilities[ $key ] = (string)$annotation->Record->PropertyValue['Bool'] === 'true';
            }

            $metadata->addEntitySet(new EntitySet($name, $entityType, $capabilities));
        }

        // Extract BoundActions
        $boundActions = $xml->xpath('//edm:Schema/edm:Action');
        foreach ($boundActions as $boundAction) {
            $name = (string)$boundAction['Name'];
            $type = (string)$boundAction->Parameter['Type'];
            $entityType = $metadata->getEntityType($type, true);
            $metadata->addBoundAction(new BoundAction($name, $entityType));
        }

        return $metadata;
    }

    public static function fromString(string $contents): Metadata
    {
        $xml = simplexml_load_string($contents);
        if ($xml === false) {
            throw new Exception('Failed to parse metadata XML.');
        }

        return Factory::fromXml($xml);
    }
}
