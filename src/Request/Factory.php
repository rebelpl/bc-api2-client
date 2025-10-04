<?php
namespace Rebel\BCApi2\Request;

use Rebel\BCApi2\Entity;
use Rebel\BCApi2\Exception;
use Rebel\BCApi2\Request;

class Factory
{
    public static function createEntity(string $baseUrl, ?Entity $entity, array $data): Request
    {
        return new Request('POST',
            new Request\UriBuilder($baseUrl)
                ->expand($entity ? $entity->getExpandedProperties() : []),
            body: json_encode($data));
    }

    public static function updateEntity(string $baseUrl, Entity $entity, array $data): Request
    {
        // $expand does not work with PATCH, so we use $batch to update the entity, then read it back from BC
        // https://learn.microsoft.com/en-us/dynamics365/business-central/dev-itpro/webservices/odata-known-limitations
        $requests = [];
        foreach ($data as $name => $value) {
            if ($entity->isExpandedProperty($name)) {
                $targetUrl = new Request\UriBuilder($baseUrl, $entity->getPrimaryKey())->include($name);
                $property = $entity->get($name);
                if ($property instanceof Entity\Collection) {
                    foreach ($value as $i => $changes) {

                        /** @var Entity $target */
                        $target = $property[ $i ] ?? null;
                        $key = "$name/$i";

                        $requests[ $key ] = Request\Factory::saveEntity($targetUrl, $target, $changes);
                    }
                }
                elseif ($property instanceof Entity) {
                    $property->setPrimaryKey(null);
                    $requests[ $name ] = Request\Factory::updateEntity($targetUrl, $property, $value);
                }
                else {
                    throw new Exception(sprintf("Invalid property type ($property): %s.", gettype($property)));
                }

                unset($data[ $name ]);
            }
        }

        $request = new Request('PATCH',
            new Request\UriBuilder($baseUrl, $entity->getPrimaryKey()),
            body: json_encode($data, JSON_FORCE_OBJECT),
            etag: $entity->getETag());

        if (!$entity->hasExpandedProperties()) {
            return $request;
        }

        $batch = new Request\Batch();
        if (!empty($data)) {
            // update the entity
            $batch->add('$update', $request);
        }

        // update expanded properties
        foreach ($requests as $key => $request) {
            $batch->add($key, $request);
        }

        $request = new Request('GET',
            new Request\UriBuilder($baseUrl, $entity->getPrimaryKey())
                ->expand($entity->getExpandedProperties()));

        // read the entity back from database
        $batch->add('$read', $request);
        return $batch->getRequest();
    }

    public static function saveEntity(string $baseUrl, ?Entity $entity, array $data): Request
    {
        return $entity && $entity->getETag()
            ? self::updateEntity($baseUrl, $entity, $data)
            : self::createEntity($baseUrl, $entity, $data);
    }

    public static function deleteEntity(string $baseUrl, Entity $entity): Request
    {
        return new Request('DELETE',
            new Request\UriBuilder($baseUrl, $entity->getPrimaryKey()),
            etag: $entity->getETag());
    }
}