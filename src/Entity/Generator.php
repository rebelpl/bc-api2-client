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

class Generator
{
    const EXCLUDED_ENTITYSETS = [
        'entityDefinitions',
        'companies',
        'subscriptions',
        'externaleventsubscriptions',
        'externalbusinesseventdefinitions',
        'apicategoryroutes'
    ];

    private $namespacePrefix;
    
    /** @var Metadata */
    private $metadata;

    public function __construct(
        Metadata $metadata,
        string   $namespacePrefix = 'Rebel\\BCApi2\\Entity\\')
    {
        $this->metadata = $metadata;
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
            Client::class => null,
            Entity::class => null,
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
        $class = (new PhpGenerator\ClassType($className))
            ->setExtends(Entity::class);

        foreach ($this->generateRecordProperties($entityType, $isUpdateable) as $classMembers) {
            foreach ($classMembers as $member) {
                $class->addMember($member);
            }
        }

        foreach ($this->generateRecordNavProperties($entityType) as $member) {
            $class->addMember($member);
        }
        
        foreach ($this->generateRecordActions($entityType) as $classMember) {
            $class->addMember($classMember);
        }
        
        return $class;
    }

    /**
     * @return PhpGenerator\Method[]
     */
    protected function generateRecordActions(EntityType $entityType): array
    {
        $classMembers = [];
        $boundActions = $this->metadata->getBoundActionsFor($entityType->getName());
        foreach ($boundActions as $action) {
            $classMembers[] = $this->generateRecordActionMethod($action);
        }
        
        return $classMembers;
    }
    
    protected function generateRecordActionMethod(Metadata\BoundAction $action): PhpGenerator\Method
    {
        $name = $action->getName();
        $prefix = $this->metadata->getNamespace();
        return (new PhpGenerator\Method('do' . ucfirst($name)))
            ->setBody("\$this->doAction('$prefix.$name', \$client);")
            ->setReturnType('void')
            ->setParameters([
                (new PhpGenerator\Parameter('client'))
                    ->setType(Client::class)
            ]);
    }
    
    /**
     * @return (PhpGenerator\Property|PhpGenerator\Method)[]
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
    
    protected function generateRecordClassMap(array $classMap): PhpGenerator\Property
    {
        return (new PhpGenerator\Property('classMap'))
            ->setValue(array_map(function ($value) { return new PhpGenerator\Literal($value . '::class'); }, $classMap))
            ->setProtected();
    }
    
    protected function generateRecordNavPropertySingle(string $name, string $targetEntityName): PhpGenerator\Method
    {
        return (new PhpGenerator\Method('get' . ucfirst($name)))
            ->setReturnType($this->namespacePrefix . $targetEntityName)
            ->setBody("return \$this->get('$name');")
            ->setReturnNullable(true);
    }

    protected function generateRecordNavPropertyCollection(string $name, string $targetEntityName): PhpGenerator\Method
    {
        return (new PhpGenerator\Method('get' . ucfirst($name)))
            ->setReturnType(Entity\Collection::class)
            ->setBody("return \$this->getAsCollection('$name');")
            ->setReturnNullable(false)
            ->addComment("@return Entity\\Collection|{$targetEntityName}[]");
    }

    /**
     * @return PhpGenerator\Method[][]
     */
    protected function generateRecordProperties(EntityType $entityType, bool $isUpdateable): array
    {
        $classMembers = [];
        $properties = $entityType->getProperties();
        foreach ($properties as $name => $property) {
            $classMembers[] = $this->generateRecordMethods($name, $property->getType(), $isUpdateable);
        }
        
        return array_filter($classMembers);
    }

    /**
     * @return PhpGenerator\Method[]
     */
    protected function generateRecordMethods(string $name, string $propertyType, bool $isUpdateable): array
    {
        if ((substr($name, -strlen('Filter')) === 'Filter') or
            (substr($name, -strlen(Metadata::FILTER_SUFFIX)) === Metadata::FILTER_SUFFIX)) {
            return [];
        }
        
        switch ($propertyType) {
            case 'Edm.String':
            case 'Edm.Guid':
            case 'Edm.Boolean':
            case 'Edm.Int32':
            case 'Edm.Int64':
            case 'Edm.Decimal':
            case 'Edm.Double':
                return $this->generateRecordMethodsSimple($name, $propertyType, $isUpdateable);

            case 'Edm.Date':
            case 'Edm.DateTimeOffset':
                return $this->generateRecordMethodsDateTime($name, $propertyType, $isUpdateable);
                
            case 'Edm.Stream':
                return $this->generateRecordMethodsStream($name);
                
            default: return $this->generateRecordMethodsEnum($name, $propertyType, $isUpdateable);
        }
    }

    /**
     * @return PhpGenerator\Method[]
     */
    protected function generateRecordMethodsDateTime(string $name, string $propertyType, bool $isUpdateable): array
    {
        // getter
        $methods[] = (new PhpGenerator\Method('get' . ucfirst($name)))
            ->setReturnType(Carbon::class)
            ->setBody($propertyType === 'Edm.Date'
                ? "return \$this->getAsDate('$name');"
                : "return \$this->getAsDateTime('$name');")
            ->setReturnNullable(true);

        // setter
        if ($isUpdateable) {
            $method = (new PhpGenerator\Method('set' . ucfirst($name)))
                ->setReturnType('self')
                ->setBody($propertyType === 'Edm.Date'
                    ? "\$this->setAsDate('$name', \$value);"
                    : "\$this->setAsDateTime('$name', \$value);")
                ->addBody("\nreturn \$this;");

            $method->setParameters([
                (new PhpGenerator\Parameter('value'))
                    ->setType(\DateTime::class)
                    ->setNullable(true),
            ]);

            $methods[] = $method;
        }

        return $methods;        
    }

    /**
     * @return PhpGenerator\Method[]
     */
    protected function generateRecordMethodsEnum(string $name, string $propertyType, bool $isUpdateable): array 
    {
        if (strpos($propertyType, $this->metadata->getNamespace()) !== 0) {
            throw new Exception("Property type '$propertyType' not found in metadata.");
        }
        
        return $this->generateRecordMethodsWithPhpType($name, 'string', $isUpdateable);
    }

    /**
     * @return PhpGenerator\Method[]
     */
    protected function generateRecordMethodsStream(string $name): array
    {
        return $this->generateRecordMethodsWithPhpType($name, DataStream::class, false, false); 
    }

    /**
     * @return PhpGenerator\Method[]
     */
    protected function generateRecordMethodsSimple(string $name, string $propertyType, bool $isUpdateable): array
    {
        // id is always read-only
        if ($name === 'id') {
            $isUpdateable = false;
        }

        $phpType = $this->matchPropertTypeToPhpType($propertyType);
        return $this->generateRecordMethodsWithPhpType($name, $phpType, $isUpdateable);
    }

    /**
     * @return PhpGenerator\Method[]
     */
    protected function generateRecordMethodsWithPhpType(string $name, string $phpType, bool $isUpdateable, bool $isNullable = true): array
    {
        // getter
        $methods[] = (new PhpGenerator\Method('get' . ucfirst($name)))
            ->setReturnType($phpType)
            ->setBody("return \$this->get('$name');")
            ->setReturnNullable($isNullable);

        // setter
        if ($isUpdateable) {
            $method = (new PhpGenerator\Method('set' . ucfirst($name)))
                ->setReturnType('self')
                ->setBody("\$this->set('$name', \$value);")
                ->addBody("\nreturn \$this;");

            $method->setParameters([
                (new PhpGenerator\Parameter('value'))
                    ->setType($phpType)
                    ->setNullable($isNullable),
            ]);

            $methods[] = $method;
        }
        
        return $methods;
    }

    private function matchPropertTypeToPhpType(string $propertyType): string
    {
        switch ($propertyType) {
            case 'Edm.String':
            case 'Edm.Guid':
                return 'string';

            case 'Edm.Int32':
            case 'Edm.Int64':
                return 'int';

            case 'Edm.Decimal':
            case 'Edm.Double':
                return 'float';

            case 'Edm.Boolean':
                return 'bool';

            default: throw new Exception("Property type '$propertyType' not matched to PHP type.");
        }
    }

    protected function generateRepositoryFor(Metadata\EntitySet $entitySet): PhpGenerator\ClassType
    {
        $className = 'Repository';
        $class = (new PhpGenerator\ClassType($className))
            ->setExtends(Repository::class);

        $class->addMethod('__construct')
            ->setBody("parent::__construct(\$client, entitySetName: '{$entitySet->getName()}', entityClass: \$entityClass);")
            ->setParameters([
                new PhpGenerator\Parameter('client')->setType(Client::class),
                new PhpGenerator\Parameter('entityClass')->setType('string')->setDefaultValue(new PhpGenerator\Literal('Record::class')),
            ]);

        return $class;
    }

    protected function generatePropertiesFor(Metadata\EntityType $entityType): PhpGenerator\ClassType
    {
        $className = 'Properties';
        $class = new PhpGenerator\ClassType($className);

        $properties = $entityType->getProperties();
        foreach ($properties as $name => $property) {
            $class->addConstant($name, $name);
        }

        $navProperties = $entityType->getNavigationProperties();
        foreach ($navProperties as $name => $property) {
            $class->addConstant($name, $name);
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

    public function generateEnumTypeFor(string $name): PhpGenerator\ClassType
    {
        $enumMembers = $this->metadata->getEnumTypeMembers($name);
        if (is_null($enumMembers)) {
            throw new Exception("Enum type '$name' not found in metadata.");
        }

        $className = ucfirst($name);
        $enum = new PhpGenerator\ClassType($className);

        foreach ($enumMembers as $value) {
            $case = preg_replace('/_x002[a-zA-Z0-9]_/', '', $value) ?: 'Null';
            $enum->addConstant($case, $value);
        }

        return $enum;
    }
}
