<?php
namespace Rebel\BCApi2\Entity;

use Rebel\BCApi2\Metadata;
use Rebel\BCApi2\Exception;
use Rebel\BCApi2\Metadata\EntitySet;

readonly class Generator
{
    const array EXCLUDED_ENTITYSETS = [
        'entityDefinitions',
        'companies',
        'subscriptions',
        'externaleventsubscriptions',
        'externalbusinesseventdefinitions',
        'apicategoryroutes'
    ];

    private string $outputDir;
    private string $namespacePrefix;
    private string $apiRoute;

    public function __construct(
        private Metadata $metadata,
        string           $apiRoute = 'v2.0',
        string           $outputDir = 'src/Entity/',
        string           $namespacePrefix = 'Rebel\\BCApi2\\Entity\\')
    {
        $this->apiRoute = trim($apiRoute, '/');
        $this->outputDir = rtrim($outputDir, '/\\') . DIRECTORY_SEPARATOR;
        $this->namespacePrefix = rtrim($namespacePrefix, '\\') . '\\';
    }

    public function generateAll(bool $overwrite = false): void
    {
        $this->generateAllEnumTypes($overwrite);
        foreach ($this->metadata->getEntitySets() as $entitySet) {
            $name = $entitySet->getName();
            if (!in_array($name, self::EXCLUDED_ENTITYSETS)) {
                echo ' - ' . $name . PHP_EOL;
                $this->generateRepositoryFor($name, $overwrite);
                $this->generatePropertiesFor($name, $overwrite);
                $this->generateRecordFor($name, $overwrite);
            }
        }
    }

    private function buildOutputPath(string $folder, ?string $filename = null): string
    {
        return $this->outputDir .
            // str_replace('.', DIRECTORY_SEPARATOR, $this->metadata->getNamespace()) . DIRECTORY_SEPARATOR .
            $folder . DIRECTORY_SEPARATOR . $filename;
    }

    private function createOutputDir(string $name): void
    {
        $dir = $this->buildOutputPath($name);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    private function buildNamespace(string $name): string
    {
        return ltrim($this->namespacePrefix .
            // str_replace('.', '\\', $this->metadata->getNamespace()) . '\\' .
            $name, '\\');
    }

    public function generateAllEnumTypes(bool $overwrite = false): void
    {
        $enumTypes = $this->metadata->getEnumTypes();
        if (empty($enumTypes)) {
            return; // No enums to generate
        }

        foreach ($enumTypes as $type) {
            $this->generateEnumTypeFor($type, $overwrite);
        }
    }

    public function generateEnumTypeFor(string $name, bool $overwrite = false): bool
    {
        $enumMembers = $this->metadata->getEnumTypeMembers($name);
        if (is_null($enumMembers)) {
            throw new Exception("Enum type '{$name}' not found in metadata.");
        }

        // Determine namespace and class name
        $namespace = $this->buildNamespace('Enums');
        $className = ucfirst($name);

        // Check if file exists and we're not overwriting
        $outputFile = $this->buildOutputPath('Enums', $className . '.php');
        if (file_exists($outputFile) && !$overwrite) {
            return false;
        }

        // Build enum cases
        $enumCases = [];
        foreach ($enumMembers as $value) {
            $name = preg_replace('/_x002[a-zA-Z0-9]_/', '', $value) ?: 'Null';
            $enumCases[] = "    case {$name} = '$value';";
        }

        // Generate file content
        $enumContent = implode("\n", $enumCases);
        $content = <<<PHP
<?php
namespace {$namespace};

enum {$className}: string
{
{$enumContent}
}
PHP;

        $this->createOutputDir('Enums');
        return file_put_contents($outputFile, $content) !== false;
    }

    public function generateRepositoryFor(string $name, bool $overwrite = false): bool
    {
        $entitySet = $this->metadata->getEntitySet($name) ?? $this->metadata->getEntitySetFor($name);
        if (!$entitySet) {
            throw new Exception("Entity set '{$name}' not found in metadata.");
        }

        $entityType = $entitySet->getEntityType();
        $entityName = ucfirst($entityType->getName());

        // Determine namespace and class name
        $namespace = $this->buildNamespace($entityName);
        $className = 'Repository';

        // Check if file exists and we're not overwriting
        $outputFile = $this->buildOutputPath($entityName, $className . '.php');
        if (file_exists($outputFile) && !$overwrite) {
            return false;
        }

        $content = <<<PHP
<?php
namespace {$namespace};

use Rebel\BCApi2\Client;
use Rebel\BCApi2\Entity\Repository as EntityRepository;

readonly class {$className} extends EntityRepository
{
    public function __construct(Client \$client)
    {
        parent::__construct(\$client, '{$entitySet->getName()}', '{$this->apiRoute}', Record::class);
    }
}
PHP;

        $this->createOutputDir($entityName);
        return file_put_contents($outputFile, $content) !== false;
    }

    public function generatePropertiesFor(string $name, bool $overwrite = false): bool
    {
        $entitySet = $this->metadata->getEntitySet($name) ?? $this->metadata->getEntitySetFor($name);
        if (!$entitySet) {
            throw new Exception("Entity set '{$name}' not found in metadata.");
        }

        $entityType = $entitySet->getEntityType();
        $entityName = ucfirst($entityType->getName());

        $navProperties = $entityType->getNavigationProperties();
        $properties = $entityType->getProperties();

        if (empty($navProperties) && empty($properties)) {
            return false; // No properties to generate
        }

        // Determine namespace and class name
        $namespace = $this->buildNamespace($entityName);
        $className = 'Properties';

        // Check if file exists and we're not overwriting
        $outputFile = $this->buildOutputPath($entityName, $className . '.php');
        if (file_exists($outputFile) && !$overwrite) {
            return false;
        }

        // Build enum cases
        $enumCases = [];
        foreach ($properties as $name => $property) {
            $enumCases[] = "    case {$name};";
        }

        $enumCases[] = '';
        foreach ($navProperties as $name => $property) {
            $enumCases[] = "    case {$name};";
        }

        $enumContent = implode("\n", $enumCases);

        // Generate file content
        $content = <<<PHP
<?php
namespace {$namespace};

enum {$className}
{
{$enumContent}
}
PHP;

        $this->createOutputDir($entityName);
        return file_put_contents($outputFile, $content) !== false;
    }

    public function generateRecordFor(string $name, bool $overwrite = false): bool
    {
        $entitySet = $this->metadata->getEntitySet($name) ?? $this->metadata->getEntitySetFor($name);
        if (!$entitySet) {
            throw new Exception("Entity set '{$name}' not found in metadata.");
        }

        $entityType = $entitySet->getEntityType();
        $entityName = ucfirst($entityType->getName());

        // Determine namespace and class name
        $namespace = $this->buildNamespace($entityName);
        $className = 'Record';

        // Check if file exists and we're not overwriting
        $outputFile = $this->buildOutputPath($entityName, $className . '.php');
        if (file_exists($outputFile) && !$overwrite) {
            return false;
        }

        $enumNamespace = $this->buildNamespace('Enums');
        $imports = ["use Rebel\\BCApi2\\Entity;"];
        $classMapEntries = [];
        $classProperties = [];
        $methods = [];

        $enumTypes = $this->metadata->getEnumTypes();
        $properties = $entityType->getProperties();
        foreach ($properties as $name => $property) {

            if (str_ends_with($name, 'Filter')) {
                continue;
            }

            if (str_starts_with($property->getType(), $this->metadata->getNamespace())) {
                $imports[] = "use $enumNamespace;";
                $enumName = substr($property->getType(), strlen($this->metadata->getNamespace()) + 1);

                if (!in_array($enumName, $enumTypes)) {
                    throw new Exception("Type '{$enumName}' is not a valid enum type.");
                }

                $phpType = 'Enums\\' . ucfirst($enumName);
                $classProperties[] = $this->getPropertyAsEnum($name, $phpType, $entitySet->isUpdatable());
            }
            elseif (str_ends_with($property->getType(), 'DateTimeOffset')) {
                $classProperties[] = $this->getPropertyAsDateTime($name, $entitySet->isUpdatable());
            }
            elseif (str_ends_with($property->getType(), 'Date')) {
                $classProperties[] = $this->getPropertyAsDate($name, $entitySet->isUpdatable());
            }
            else {
                $phpType = $this->mapODataTypeToPhpType($property->getType());
                $classProperties[] = $this->getProperty($name, $phpType, null, $entitySet->isUpdatable());
            }
        }

        $navProperties = $entityType->getNavigationProperties();
        foreach ($navProperties as $name => $navProperty) {
            $targetType = $navProperty->isCollection()
                ? $navProperty->getCollectionType()
                : $navProperty->getType();

            $targetEntity = $this->metadata->getEntityType($targetType, true);
            if (!$targetEntity) {
                throw new Exception("Entity type '{$targetType}' not found in metadata.");
            }

            $targetEntityName = ucfirst($targetEntity->getName());
            $targetNamespace = $this->buildNamespace($targetEntityName);

            $imports[] = "use {$targetNamespace};";
            $classMapEntries[] = "\t\t\t'{$name}' => {$targetEntityName}\\Record::class,";

            $classProperties[] = $navProperty->isCollection()
                ? $this->getPropertyCollection($name, $targetEntityName . '\\Record')
                : $this->getProperty($name, $targetEntityName . '\\Record', false);
        }

        if ($classMapEntries) {
            $classMapContent = implode("\n", $classMapEntries);
            $methods[] = $this->constructorMethod($classMapContent);
        }

        $importContent = implode("\n", array_unique($imports));
        $classPropertiesContent = implode("\n", $classProperties);
        $methodsContent = implode("\n\n", $methods);

        // Generate file content
        $content = <<<PHP
<?php
namespace {$namespace};

{$importContent}

class Record extends Entity
{
{$classPropertiesContent}
{$methodsContent}
}
PHP;

        $this->createOutputDir($entityName);
        return file_put_contents($outputFile, $content) !== false;
    }

    private function constructorMethod(string $classMapContent): string
    {
        return <<<PHP
    public function __construct(array \$data = [], ?string \$context = null)
    {
        parent::__construct(\$data, \$context);

        \$this->classMap = [
{$classMapContent}
        ];
    }
PHP;
    }

    private function getPropertyCollection(string $name, string $phpType): string
    {
        return "\t/** @var Entity\\Collection<{$phpType}> */\n"
            . $this->getProperty($name, 'Entity\\Collection', 'collection', false, false);
    }

    private function getPropertyAsEnum(string $name, string $phpType, bool $isUpdatable): string
    {
        return $this->getProperty($name, $phpType, $phpType, $isUpdatable);
    }

    private function getPropertyAsDate(string $name, bool $isUpdatable): string
    {
        return $this->getProperty($name, '\\DateTime', 'date', $isUpdatable);
    }

    private function getPropertyAsDateTime(string $name, bool $isUpdatable): string
    {
        return $this->getProperty($name, '\\DateTime', 'datetime', $isUpdatable);
    }

    private function getProperty(string $name, string $phpType, ?string $castType = null, bool $isUpdatable = true, bool $isNullable = true): string
    {
        $nullable = $isNullable ? '?' : '';
        if (empty($castType)) {
            $cast = '';
        }
        elseif (str_starts_with($castType, 'Enums\\')) {
            $cast = ", {$castType}::class";
        }
        else {
            $cast = ", '{$castType}'";
        }

        return
            "\tpublic {$nullable}{$phpType} \${$name} {\n" .
            "\t\tget => \$this->get('{$name}'{$cast});\n" .
            ($isUpdatable ? "\t\tset => \$this->set('{$name}', \$value);\n" : "") .
            "\t}\n";
    }

    private function mapODataTypeToPhpType(string $odataType): string
    {
        // Handle collection types
        if (str_starts_with($odataType, 'Collection(')) {
            $odataType = substr($odataType, 11, -1);
            throw new Exception("Entity type '{$odataType}' is a collection.");
        }

        // Handle enum types
        if (str_starts_with($odataType, $this->metadata->getNamespace())) {
            $enumTypes = $this->metadata->getEnumTypes();
            $odataType = substr($odataType, strlen($this->metadata->getNamespace()) + 1);
            if (!in_array($odataType, $enumTypes)) {
                throw new Exception("Entity type '{$odataType}' is not a valid enum type.");
            }

            return 'Enums\\' . ucfirst($odataType);
        }

        $typeMap = [
            'Edm.String' => 'string',
            'Edm.Int32' => 'int',
            'Edm.Int64' => 'int',
            'Edm.Decimal' => 'float',
            'Edm.Double' => 'float',
            'Edm.Boolean' => 'bool',
            'Edm.Guid' => 'string',
            'Edm.Binary' => 'string',
            'Edm.Stream' => 'string',
        ];

        if (!isset($typeMap[ $odataType ])) {
            throw new Exception("Entity type '{$odataType}' cannot be mapped correctly.");
        }

        return $typeMap[ $odataType ];
    }
}