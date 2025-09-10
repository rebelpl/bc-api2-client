<?php
namespace Rebel\BCApi2\Entity;

use Nette\PhpGenerator\PhpFile;
use Rebel\BCApi2\Entity;
use Rebel\BCApi2\Exception;
use Rebel\BCApi2\Metadata;
use Rebel\BCApi2\Client;
use Nette\PhpGenerator;
use Rebel\BCApi2\Metadata\EntityType;
use Carbon\Carbon;

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

    private string $namespacePrefix;

    public function __construct(
        private Metadata $metadata,
        string           $namespacePrefix = 'Rebel\\BCApi2\\Entity\\')
    {
        $this->namespacePrefix = rtrim($namespacePrefix, '\\') . '\\';
    }

    /**
     * @return PhpGenerator\PhpFile[]
     */
    public function generateAllFiles(): array
    {
        return array_merge(
            $this->generateFilesForAllEntitySets(),
            $this->generateFilesForAllEnumTypes());
    }

    public function saveFilesTo(array $files, string $folder, bool $overwrite = false): void
    {
        $folder = trim($folder, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        foreach ($files as $path => $file) {
            $this->saveFileTo($file, $folder . $path . '.php', $overwrite);
        }
    }

    public function saveAllFilesTo(string $folder, bool $overwrite = false): void
    {
        $files = $this->generateAllFiles();
        $this->saveFilesTo($files,$folder, $overwrite);
    }

    public function saveFileTo(PhpGenerator\PhpFile $file, string $path, bool $overwrite = false): void
    {
        if (!$overwrite && is_file($path)) {
            return;
        }

        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $printer = new PhpGenerator\PsrPrinter();
        $contents = str_replace("<?php\n", "<?php", $printer->printFile($file));
        file_put_contents($path, $contents);
    }

    private function generateClassFile($class, string $namespace, array $imports = []): PhpFile
    {
        $file = new PhpFile();
        $ns = new PhpGenerator\PhpNamespace($this->namespacePrefix . $namespace);
        $ns->add($class);
        foreach ($imports as $import => $alias) {
            $ns->addUse($import, $alias);
        }

        $file->addNamespace($ns);
        return $file;
    }

    /**
     * @return PhpGenerator\PhpFile[]
     * @throws Exception
     */
    public function generateFilesForAllEntitySets(): array
    {
        $files = [];
        foreach ($this->metadata->getEntitySets() as $entitySet) {
            $name = $entitySet->getName();
            if (!in_array($name, self::EXCLUDED_ENTITYSETS)) {
                $files = array_merge($files, $this->generateFilesForEntitySet($name));
            }
        }

        return $files;
    }

    /**
     * @return PhpGenerator\PhpFile[]
     * @throws Exception
     */
    public function generateFilesForEntitySet(string $name): array
    {
        $entitySet = $this->metadata->getEntitySet($name) ?? $this->metadata->getEntitySetFor($name);
        if (!$entitySet) {
            throw new Exception("Entity set '{$name}' not found in metadata.");
        }

        $files = [];
        $entityType = $entitySet->getEntityType();
        $entityName = ucfirst($entityType->getName());

        // properties
        $class = $this->generatePropertiesFor($entityType);
        $path = $entityName . DIRECTORY_SEPARATOR . $class->getName();
        $files[ $path ] = $this->generateClassFile($class, $entityName);

        // repository
        $class = $this->generateRepositoryFor($entitySet);
        $path = $entityName . DIRECTORY_SEPARATOR . $class->getName();
        $files[ $path ] = $this->generateClassFile($class, $entityName, [
            Client::class => null,
            Repository::class => 'EntityRepository',
        ]);

        // record
        $class = $this->generateRecordFor($entityType, $entitySet->isUpdatable());
        $path = $entityName  . DIRECTORY_SEPARATOR . $class->getName();
        $files[ $path ] = $this->generateClassFile($class, $entityName, array_merge([
            Carbon::class => null,
            Entity::class => null,
            $this->namespacePrefix . 'Enums' => null,
        ], $this->generateRecordImports($entityType)));

        return $files;
    }

    protected function generateRecordImports(EntityType $entityType): array
    {
        $imports = [];
        foreach ($entityType->getNavigationProperties() as $property) {
            $targetType = $property->isCollection()
                ? $property->getCollectionType()
                : $property->getType();

            $targetEntity = $this->metadata->getEntityType($targetType, true);
            if (!$targetEntity) {
                throw new Exception("Entity type '{$targetType}' not found in metadata.");
            }

            $targetEntityName = ucfirst($targetEntity->getName());
            $imports[ $this->namespacePrefix . $targetEntityName ] = null;
        }

        return $imports;
    }

    public function generateRecordFor(EntityType $entityType, bool $isUpdateable): PhpGenerator\ClassType
    {
        $className = 'Record';
        $class = new PhpGenerator\ClassType($className)
            ->setExtends(Entity::class);

        $properties = $entityType->getProperties();
        foreach ($properties as $name => $property) {
            if (str_ends_with($name, 'Filter')) {
                continue;
            }

            $type = $this->matchPropertTypeToPhpType($property->getType());
            $class->addMember(
                $this->generateRecordProperty($name, $type, $property->getType(), $isUpdateable)
            );
        }

        $classMap = [];
        $navProperties = $entityType->getNavigationProperties();
        foreach ($navProperties as $name => $property) {

            $targetType = $property->isCollection()
                ? $property->getCollectionType()
                : $property->getType();

            $targetEntity = $this->metadata->getEntityType($targetType, true);
            if (!$targetEntity) {
                throw new Exception("Entity type '{$targetType}' not found in metadata.");
            }

            $targetEntityName = ucfirst($targetEntity->getName()) . '\\Record';
            if ($property->isCollection()) {
                $class->addMember(
                    $this->generateRecordProperty($name, Entity\Collection::class, $property->getType(), false)
                        ->addComment("@var ?Entity\\Collection<{$targetEntityName}>")
                );
            }
            else {
                $class->addMember(
                    $this->generateRecordProperty($name, $this->namespacePrefix . $targetEntityName, $property->getType(), false)
                );
            }

            $classMap[ $name ] = $targetEntityName;
        }

        if (!empty($classMap)) {
            $class->addProperty('classMap', array_map(function ($value) { return new PhpGenerator\Literal($value . '::class'); }, $classMap))
                ->setType('array')
                ->setProtected();
        }

        return $class;
    }

    protected function generateRecordProperty(string $name, string $phpType, string $propertyType, bool $isUpdateable): PhpGenerator\Property
    {
        // id is always read-only
        if ($name === 'id') {
            $isUpdateable = false;
        }

        $property = new PhpGenerator\Property($name)->setNullable();

        // enum type
        if (str_starts_with($phpType, $this->metadata->getNamespace())) {
            $enumName = ucfirst(substr($phpType, strlen($this->metadata->getNamespace()) + 1));
            $property->setType($this->namespacePrefix . 'Enums\\' . $enumName);

            $property->addHook('get', "\$this->getAsEnum('{$name}', Enums\\{$enumName}::class)");
            if ($isUpdateable) {
                $property->addHook('set')->setBody("\$this->set('{$name}', \$value);");
            }

            return $property;
        }

        // datetime types
        if ($phpType === \DateTime::class) {
            $property->setType(Carbon::class);
            $property->addHook('get', "\$this->getAsDateTime('{$name}')");
            if ($isUpdateable) {
                $property->addHook('set')->setBody($propertyType === 'Edm.Date'
                    ? "\$this->setAsDate('{$name}', \$value);"
                    : "\$this->setAsDateTime('{$name}', \$value);");
            }

            return $property;
        }

        // collection
        if ($phpType === Entity\Collection::class) {
            $property->setType($phpType);
            $property->addHook('get', "\$this->get('{$name}', 'collection')");
            return $property;
        }

        // default
        $property->setType($phpType);
        $property->addHook('get', "\$this->get('{$name}')");
        if ($isUpdateable) {
            $property->addHook('set')
                ->setBody("\$this->set('{$name}', \$value);");
        }

        return $property;
    }

    private function matchPropertTypeToPhpType(string $type): string
    {
        return match ($type) {
            'Edm.String', 'Edm.Guid', 'Edm.Stream', 'Edm.Binary' => 'string',
            'Edm.Int32', 'Edm.Int64' => 'int',
            'Edm.Decimal', 'Edm.Double' => 'float',
            'Edm.Boolean' => 'bool',
            'Edm.Date', 'Edm.DateTimeOffset' => \DateTime::class,
            default => $type,
        };
    }

    protected function generateRepositoryFor(Metadata\EntitySet $entitySet): PhpGenerator\ClassType
    {
        $className = 'Repository';
        $class = new PhpGenerator\ClassType($className)
            ->setExtends(Repository::class)
            ->setReadOnly();

        $constructor = $class->addMethod('__construct')
            ->setBody("parent::__construct(\$client, entitySetName: '{$entitySet->getName()}', entityClass: Record::class);");

        $constructor->addParameter('client')
            ->setType(Client::class);

        return $class;
    }

    protected function generatePropertiesFor(Metadata\EntityType $entityType): PhpGenerator\EnumType
    {
        $className = 'Properties';
        $class = new PhpGenerator\EnumType($className);

        $properties = $entityType->getProperties();
        foreach ($properties as $name => $property) {
            $class->addCase($name);
        }

        $navProperties = $entityType->getNavigationProperties();
        foreach ($navProperties as $name => $property) {
            $class->addCase($name);
        }

        return $class;
    }

    /**
     * @return PhpGenerator\PhpFile[]
     * @throws Exception
     */
    public function generateFilesForAllEnumTypes(): array
    {
        $files = [];
        $enumTypes = $this->metadata->getEnumTypes();
        foreach ($enumTypes as $name) {
            $class = $this->generateEnumTypeFor($name);
            $path = 'Enums' . DIRECTORY_SEPARATOR . $class->getName();
            $files[ $path ] = $this->generateClassFile($class, 'Enums');
        }

        return $files;
    }

    public function generateEnumTypeFor(string $name): PhpGenerator\EnumType
    {
        $enumMembers = $this->metadata->getEnumTypeMembers($name);
        if (is_null($enumMembers)) {
            throw new Exception("Enum type '{$name}' not found in metadata.");
        }

        $className = ucfirst($name);
        $enum = new PhpGenerator\EnumType($className);

        foreach ($enumMembers as $value) {
            $case = preg_replace('/_x002[a-zA-Z0-9]_/', '', $value) ?: 'Null';
            $enum->addCase($case, $value);
        }

        return $enum;
    }
}