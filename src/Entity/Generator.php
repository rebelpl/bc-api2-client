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

        $properties = $entityType->getProperties();
        foreach ($properties as $name => $property) {
            if (str_ends_with($name, 'Filter') or str_ends_with($name, Metadata::FILTER_SUFFIX)) {
                continue;
            }

            $type = $this->matchPropertTypeToPhpType($property->getType());
            $class->addMember(
                $this->generateRecordPropertyGetMethod($name, $type)
            );
            
            if ($isUpdateable
             && $setMethod = $this->generateRecordPropertySetMethod($name, $type, $property->getType())) {
                $class->addMember($setMethod);
            }
        }

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
            if ($property->isCollection()) {
                $class->addMember(
                    $this->generateRecordPropertyGetMethod($name, Entity\Collection::class)
                        ->addComment("@return Entity\\Collection|{$targetEntityName}[]")
                );
            }
            else {
                $class->addMember(
                    $this->generateRecordPropertyGetMethod($name, $this->namespacePrefix . $targetEntityName)
                );
            }

            $classMap[ $name ] = $targetEntityName;
        }

        if (!empty($classMap)) {
            $class->addProperty('classMap', array_map(function ($value) { return new PhpGenerator\Literal($value . '::class'); }, $classMap))
                ->setProtected();
        }

        return $class;
    }
    
    protected function generateRecordPropertyGetMethod(string $name, string $phpType): PhpGenerator\Method
    {
        $method = new PhpGenerator\Method('get' . ucfirst($name));

        // datetime types
        if ($phpType === \DateTime::class) {
            $method->setReturnType(Carbon::class);
            $method->setBody("return \$this->getAsDateTime('$name');");
            return $method;
        }
        
        // collection
        if ($phpType === Entity\Collection::class) {
            $method->setReturnType(Entity\Collection::class);
            $method->setBody("return \$this->get('$name', 'collection');");
            return $method;
        }
        
        // default
        $method->setReturnType($phpType);
        $method->setBody("return \$this->get('$name');");
        return $method;
    }

    protected function generateRecordPropertySetMethod(string $name, string $phpType, string $propertyType): ?PhpGenerator\Method
    {
        if (($name === 'id') || ($phpType === Entity\Collection::class)) {
            return null;
        }

        $method = new PhpGenerator\Method('set' . ucfirst($name));
        $method->setParameters([
            (new PhpGenerator\Parameter('value'))->setType($phpType),
        ]);
        $method->setReturnType('self');

        // datetime types
        if ($phpType === \DateTime::class) {
            $method->setBody($propertyType === 'Edm.Date'
                ? "\$this->setAsDate('$name', \$value);"
                : "\$this->setAsDateTime('$name', \$value);");
        }
        else {
            $method->setBody("\$this->set('$name', \$value);");
        }
        
        $method->addBody("\nreturn \$this;");
        return $method;
    }

    private function matchPropertTypeToPhpType(string $type): string
    {
        switch ($type) {
            case 'Edm.String':
            case 'Edm.Guid':
            case 'Edm.Stream':
            case 'Edm.Binary':
                return 'string';

            case 'Edm.Int32':
            case 'Edm.Int64':
                return 'int';

            case 'Edm.Decimal':
            case 'Edm.Double':
                return 'float';

            case 'Edm.Date':
            case 'Edm.DateTimeOffset':
                return \DateTime::class;

            case 'Edm.Boolean':
                return 'bool';
        }

        if (str_starts_with($type, $this->metadata->getNamespace())) {
            return 'string';
        }
        
        throw new Exception("Unsupported type '$type'.");
    }

    protected function generateRepositoryFor(Metadata\EntitySet $entitySet): PhpGenerator\ClassType
    {
        $className = 'Repository';
        $class = (new PhpGenerator\ClassType($className))
            ->setExtends(Repository::class);

        $constructor = $class->addMethod('__construct')
            ->setBody("parent::__construct(\$client, '{$entitySet->getName()}', Record::class);");

        $constructor->addParameter('client')
            ->setType(Client::class);

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