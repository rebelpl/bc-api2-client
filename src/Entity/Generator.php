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
        $this->saveFilesTo($files, $folder, $overwrite);
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
            throw new Exception("Entity set '$name' not found in metadata.");
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
                throw new Exception("Entity type '$targetType' not found in metadata.");
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

        foreach ($this->generateRecordProperties($entityType, $isUpdateable) as $classMember) {
            $class->addMember($classMember);
        }

        foreach ($this->generateRecordNavProperties($entityType) as $classMember) {
            $class->addMember($classMember);
        }
        
        return $class;
    }

    /**
     * @return PhpGenerator\Property[]
     */
    protected function generateRecordNavProperties(EntityType $entityType): array
    {
        $classMembers = [];
        $classMap = [];
        $navProperties = $entityType->getNavigationProperties();
        foreach ($navProperties as $name => $property) {

            $targetType = $property->isCollection()
                ? $property->getCollectionType()
                : $property->getType();

            $targetEntity = $this->metadata->getEntityType($targetType, true);
            if (!$targetEntity) {
                throw new Exception("Entity type '$targetType' not found in metadata.");
            }

            $targetEntityName = ucfirst($targetEntity->getName()) . '\\Record';
            $classMap[ $name ] = $targetEntityName;
            
            $classMembers[] = $property->isCollection() 
                ? $this->generateRecordNavPropertyCollection($name, $targetEntityName)
                : $this->generateRecordNavPropertySingle($name, $targetEntityName);
        }

        if (!empty($classMap)) {
            $classMembers[] = $this->generateRecordClassMap($classMap);
        }
        
        return $classMembers;
    }
    
    protected function generateRecordClassMap(array $classMap): ?PhpGenerator\Property
    {
        return new PhpGenerator\Property('classMap')
            ->setValue(array_map(function ($value) { return new PhpGenerator\Literal($value . '::class'); }, $classMap))
            ->setType('array')
            ->setProtected();
    }

    protected function generateRecordNavPropertySingle(string $name, string $targetEntityName): ?PhpGenerator\Property
    {
        $property = new PhpGenerator\Property($name)
            ->setType($this->namespacePrefix . $targetEntityName)
            ->setNullable();

        $property->addHook('get', "\$this->get('$name')");
        return $property;
    }

    protected function generateRecordNavPropertyCollection(string $name, string $targetEntityName): PhpGenerator\Property
    {
        $property = new PhpGenerator\Property($name)
            ->setType(Entity\Collection::class)
            ->setNullable(false);

        $property->addHook('get', "\$this->getAsCollection('$name')");
        $property->addComment("@var Entity\\Collection<$targetEntityName>");
        return $property;
    }

    /**
     * @return PhpGenerator\Property[]
     */
    protected function generateRecordProperties(EntityType $entityType, bool $isUpdateable): array
    {
        $classMembers = [];
        $properties = $entityType->getProperties();
        foreach ($properties as $name => $property) {
            $classMembers[] = $this->generateRecordProperty($name, $property->getType(), $isUpdateable);
        }
        
        return array_filter($classMembers);
    }
    
    protected function generateRecordProperty(string $name, string $propertyType, bool $isUpdateable): ?PhpGenerator\Property
    {
        if (str_ends_with($name, 'Filter') or str_ends_with($name, Metadata::FILTER_SUFFIX)) {
            return null;
        }

        return match ($propertyType) {
            'Edm.String', 'Edm.Guid', 'Edm.Boolean',
            'Edm.Int32', 'Edm.Int64', 'Edm.Decimal',
            'Edm.Double' => $this->generateRecordPropertySimple($name, $propertyType, $isUpdateable),
            'Edm.Date', 'Edm.DateTimeOffset' => $this->generateRecordPropertyDateTime($name, $propertyType, $isUpdateable),
            'Edm.Stream' => $this->generateRecordPropertyStream($name),
            default => $this->generateRecordPropertyEnum($name, $propertyType, $isUpdateable),
        };
    }
    
    protected function generateRecordPropertyDateTime(string $name, string $propertyType, bool $isUpdateable): PhpGenerator\Property
    {
        $property = new PhpGenerator\Property($name)
                ->setType(Carbon::class)
                ->setNullable();
        
        $property->addHook('get', $propertyType === 'Edm.Date'
            ? "\$this->getAsDate('$name')"
            : "\$this->getAsDateTime('$name')");
        
        if ($isUpdateable) {
            $property->addHook('set')->setBody($propertyType === 'Edm.Date'
                ? "\$this->setAsDate('$name', \$value);"
                : "\$this->setAsDateTime('$name', \$value);");
        }

        return $property;
    }

    protected function generateRecordPropertyEnum(string $name, string $propertyType, bool $isUpdateable): PhpGenerator\Property
    {
        if (!str_starts_with($propertyType, $this->metadata->getNamespace())) {
            throw new Exception("Property type '$propertyType' not found in metadata.");
        }

        $enumName = ucfirst(substr($propertyType, strlen($this->metadata->getNamespace()) + 1));
        $property = new PhpGenerator\Property($name)
            ->setType($this->namespacePrefix . 'Enums\\' . $enumName)
            ->setNullable();

        $property->addHook('get', "\$this->getAsEnum('$name', Enums\\$enumName::class)");
        if ($isUpdateable) {
            $property->addHook('set')->setBody("\$this->set('$name', \$value);");
        }

        return $property;
    }
    
    protected function generateRecordPropertyStream(string $name): PhpGenerator\Property
    {
        $property = new PhpGenerator\Property($name)
            ->setType(DataStream::class)
            ->setNullable(false);

        $property->addHook('get', "\$this->get('$name')");
        return $property;
    }

    protected function generateRecordPropertySimple(string $name, string $propertyType, bool $isUpdateable): PhpGenerator\Property
    {
        // id is always read-only
        if ($name === 'id') {
            $isUpdateable = false;
        }

        $property = new PhpGenerator\Property($name)
            ->setType($this->matchPropertTypeToPhpType($propertyType))
            ->setNullable();
        
        $property->addHook('get', "\$this->get('$name')");
        if ($isUpdateable) {
            $property->addHook('set')
                ->setBody("\$this->set('$name', \$value);");
        }

        return $property;
    }

    private function matchPropertTypeToPhpType(string $propertyType): string
    {
        return match ($propertyType) {
            'Edm.String', 'Edm.Guid', => 'string',
            'Edm.Int32', 'Edm.Int64' => 'int',
            'Edm.Decimal', 'Edm.Double' => 'float',
            'Edm.Boolean' => 'bool',
            default => throw new Exception("Property type '$propertyType' not matched to PHP type.")
        };
    }

    protected function generateRepositoryFor(Metadata\EntitySet $entitySet): PhpGenerator\ClassType
    {
        $className = 'Repository';
        $class = new PhpGenerator\ClassType($className)
            ->setExtends(Repository::class);

        $class->addMethod('__construct')
            ->setBody("parent::__construct(\$client, entitySetName: '{$entitySet->getName()}', entityClass: Record::class);")
            ->addParameter('client')
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
            throw new Exception("Enum type '$name' not found in metadata.");
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
