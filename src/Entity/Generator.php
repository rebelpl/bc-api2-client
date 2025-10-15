<?php
namespace Rebel\BCApi2\Entity;

use Nette\PhpGenerator\PhpFile;
use Rebel\BCApi2\Entity;
use Rebel\BCApi2\Exception;
use Rebel\BCApi2\Metadata;
use Rebel\BCApi2\Client;
use Nette\PhpGenerator;
use Carbon\Carbon;

class Generator
{
    private string $namespacePrefix;

    private array $excludedEntitySets = [
        'entityDefinitions',
        'companies',
        'subscriptions',
        'externaleventsubscriptions',
        'externalbusinesseventdefinitions',
        'apicategoryroutes'
    ];

    private array $readonlyProperties = [
        'id', 'lastModifiedDateTime', 'rowVersion',
        'systemModifiedAt', 'systemModifiedBy',
        'systemCreatedAt', 'systemCreatedBy'
    ];

    public function __construct(
        private readonly Metadata $metadata,
        string           $namespacePrefix = 'Rebel\\BCApi2\\Entity\\')
    {
        $this->namespacePrefix = rtrim($namespacePrefix, '\\') . '\\';
    }

    public function addExcludedEntitySets(array $entitySets): self
    {
        $this->excludedEntitySets = array_merge($this->excludedEntitySets, $entitySets);
        return $this;
    }

    public function setExcludedEntitySets(array $entitySets): self
    {
        $this->excludedEntitySets = $entitySets;
        return $this;
    }

    public function addReadonlyProperties(array $properties): self
    {
        $this->readonlyProperties = array_merge($this->readonlyProperties, $properties);
        return $this;
    }

    public function setReadonlyProperties(array $properties): self
    {
        $this->readonlyProperties = array_merge($this->excludedEntitySets, $properties);
        return $this;
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

    protected function isEntitySetExcluded(string $name): bool
    {
        return in_array($name, $this->excludedEntitySets);
    }

    protected function isPropertyReadOnly(string $name): bool
    {
        return in_array($name, $this->readonlyProperties);
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
            if (!$this->isEntitySetExcluded($name)) {
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
            Entity::class => null,
        ]);
        
        // record
        $class = $this->generateRecordFor($entitySet);
        $path = $entityName  . DIRECTORY_SEPARATOR . $class->getName();
        $files[ $path ] = $this->generateClassFile($class, $entityName, $this->generateRecordImports($entityType));

        return $files;
    }

    protected function generateRecordImports(Metadata\EntityType $entityType): array
    {
        $imports = [
            Carbon::class => null,
            Client::class => null,
            Entity::class => null,
            $this->namespacePrefix . 'Enums' => null,
        ];
        
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

    public function generateRecordFor(Metadata\EntitySet $entitySet): PhpGenerator\ClassType
    {
        $className = 'Record';
        $class = new PhpGenerator\ClassType($className)
            ->setExtends(Entity::class);

        $entityType = $entitySet->getEntityType();
        $class->addMember(new PhpGenerator\Property('primaryKey')
            ->setValue($entityType->getPrimaryKey())
            ->setType('string')
            ->setProtected());

        foreach ($this->generateRecordProperties($entityType, $entitySet->isUpdatable()) as $classMember) {
            $class->addMember($classMember);
        }

        foreach ($this->generateRecordNavProperties($entityType, $entitySet->isUpdatable()) as $classMember) {
            $class->addMember($classMember);
        }
        
        $class->addMember($this->generateRecordCasts($entityType));
        return $class;
    }
    
    /**
     * @return PhpGenerator\Property[]
     */
    protected function generateRecordNavProperties(Metadata\EntityType $entityType, bool $isUpdateable): array
    {
        $classMembers = [];
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
            $classMembers[] = $property->isCollection() 
                ? $this->generateRecordNavPropertyCollection($name, $targetEntityName, $isUpdateable)
                : $this->generateRecordNavPropertyRelation($name, $targetEntityName);
        }
        
        return $classMembers;
    }
    
    protected function generateRecordNavPropertyRelation(string $name, string $targetEntityName): ?PhpGenerator\Property
    {
        $property = new PhpGenerator\Property($name)
            ->setType($this->namespacePrefix . $targetEntityName)
            ->setNullable();

        $property->addHook('get', "\$this->get('$name')");
        return $property;
    }

    protected function generateRecordNavPropertyCollection(string $name, string $targetEntityName, bool $isUpdateable): PhpGenerator\Property
    {
        $property = new PhpGenerator\Property($name)
            ->setType(Entity\Collection::class)
            ->setNullable(false);

        $property->addHook('get', "\$this->get('$name', 'collection')");
        $property->addComment("@var Entity\\Collection<$targetEntityName>");
        
        if ($isUpdateable) {
            $property->addHook('set')
                ->setBody('$this->set(?, $value);', [ $name ]);
        }
        
        return $property;
    }

    /**
     * @return PhpGenerator\Property[]
     */
    protected function generateRecordProperties(Metadata\EntityType $entityType, bool $isUpdateable): array
    {
        $classMembers = [];
        $properties = $entityType->getProperties();
        foreach ($properties as $name => $property) {
            if (!$this->shouldSkipPropertyInRecord($name)) {
                $classMembers[] = $this->generateRecordPropertyHooks($name, $property->getType(), $isUpdateable);
            }
        }
        
        return $classMembers;
    }

    protected function generateRecordCasts(Metadata\EntityType $entityType): PhpGenerator\Property
    {
        $casts = array_map(function ($property) {
            return $this->getNavPropertyCast($property);
        }, $entityType->getNavigationProperties());
        
        return new PhpGenerator\Property('casts')
            ->setValue(array_filter($casts))
            ->setType('array')
            ->setProtected();
    }
    
    protected function getNavPropertyCast(Metadata\NavigationProperty $property): array|PhpGenerator\Literal
    {
        $targetType = $property->isCollection()
            ? $property->getCollectionType()
            : $property->getType();

        $targetEntity = $this->metadata->getEntityType($targetType, true);
        if (!$targetEntity) {
            throw new Exception("Entity type '$targetType' not found in metadata.");
        }

        $targetEntityName = ucfirst($targetEntity->getName()) . '\\Record';
        return $property->isCollection()
            ? [ new PhpGenerator\Literal($targetEntityName . '::class') ]
            : new PhpGenerator\Literal($targetEntityName . '::class');
    }
    
    protected function getPropertyCast(string $propertyType): null|string|PhpGenerator\Literal
    {
        $prefix = $this->metadata->getNamespace();
        return match ($propertyType) {
            'Edm.String', 'Edm.Int32', 'Edm.Int64', 'Edm.Decimal', 'Edm.Double', 'Edm.Boolean' => null,
            'Edm.DateTimeOffset' => 'datetime',
            'Edm.Date' => 'date',
            'Edm.Guid' => 'guid',
            'Edm.Stream' => new PhpGenerator\Literal('Entity\DataStream::class'),
            default => new PhpGenerator\Literal('Enums\\' . ucfirst(substr($propertyType, strlen($prefix) + 1)) . '::class'),
        };
    }

    protected function shouldSkipPropertyInRecord(string $name): bool
    {
        return str_ends_with($name, 'Filter') or str_ends_with($name, Metadata::FILTER_SUFFIX);
    }

    protected function generateRecordPropertyHooks(string $name, string $propertyType, bool $isUpdateable): PhpGenerator\Property
    {
        if ($this->isPropertyReadOnly($name) || $propertyType === 'Edm.Stream') {
            $isUpdateable = false;
        }

        $property = new PhpGenerator\Property($name)
            ->setType($this->getPropertyType($propertyType))
            ->setNullable();

        $cast = $this->getPropertyCast($propertyType);
        $property->addHook('get')
            ->setBody($cast ? '$this->get(?, ?)' : '$this->get(?)',
                array_filter([ $name, $cast ]), true);

        if ($isUpdateable) {
            $property->addHook('set')
                ->setBody($cast ? '$this->set(?, $value, ?);' : '$this->set(?, $value);',
                    array_filter([ $name, $cast ]));
        }

        return $property;
    }

    protected function getPropertyType(string $propertyType): ?string
    {
        return match ($propertyType) {
            'Edm.String', 'Edm.Guid', => 'string',
            'Edm.Int32', 'Edm.Int64' => 'int',
            'Edm.Decimal', 'Edm.Double' => 'float',
            'Edm.Date', 'Edm.DateTimeOffset' => Carbon::class,
            'Edm.Boolean' => 'bool',
            'Edm.Stream' => Entity\DataStream::class,
            default => $this->namespacePrefix . 'Enums\\' . ucfirst(substr($propertyType, strlen($this->metadata->getNamespace()) + 1)),
        };
    }
    
    public function generateRepositoryFor(Metadata\EntitySet $entitySet): PhpGenerator\ClassType
    {
        $className = 'Repository';
        $class = new PhpGenerator\ClassType($className)
            ->setExtends(Repository::class);
        
        $class->addMethod('__construct')
            ->setBody("parent::__construct(\$client, entitySetName: '{$entitySet->getName()}', entityClass: \$entityClass);")
            ->setParameters([
                new PhpGenerator\Parameter('client')->setType(Client::class),
                new PhpGenerator\Parameter('entityClass')->setType('string')->setDefaultValue(new PhpGenerator\Literal('Record::class')),
            ]);

        $entityType = $entitySet->getEntityType();
        foreach ($this->generateRepositoryActions($entityType) as $classMember) {
            $class->addMember($classMember);
        }
        
        return $class;
    }

    /**
     * @return PhpGenerator\Method[]
     */
    protected function generateRepositoryActions(Metadata\EntityType $entityType): array
    {
        $classMembers = [];
        $boundActions = $this->metadata->getBoundActionsFor($entityType->getName());
        foreach ($boundActions as $action) {
            $classMembers[] = $this->generateRepositoryActionMethod($action);
        }

        return $classMembers;
    }

    protected function generateRepositoryActionMethod(Metadata\BoundAction $action): PhpGenerator\Method
    {
        $name = $action->getName();
        $prefix = $this->metadata->getNamespace();
        return new PhpGenerator\Method($name)
            ->setBody('$this->callBoundAction(?, $entity, $reloadAfterwards);', [ $prefix . '.' . $name ])
            ->setReturnType('void')
            ->setPublic()
            ->setParameters([
                new PhpGenerator\Parameter('entity')
                    ->setType(Entity::class),
                new PhpGenerator\Parameter('reloadAfterwards')
                    ->setType('bool')
                    ->setDefaultValue(true),
            ]);
    }

    protected function generatePropertiesFor(Metadata\EntityType $entityType): PhpGenerator\ClassType
    {
        $className = 'Properties';
        $class = new PhpGenerator\ClassType($className);

        $properties = $entityType->getProperties();
        foreach ($properties as $name => $property) {
            $class->addConstant($name, $name)->setType('string');
        }

        $navProperties = $entityType->getNavigationProperties();
        foreach ($navProperties as $name => $property) {
            $class->addConstant($name, $name)->setType('string');
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
            $case = !is_numeric($value)
                ? preg_replace('/_x002[a-zA-Z0-9]_/', '', $value) ?: 'Null'
                : 'Value' . $value;
            $enum->addCase($case, $value);
        }

        return $enum;
    }
}