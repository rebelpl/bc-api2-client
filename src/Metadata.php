<?php
namespace Rebel\BCApi2;

class Metadata
{
    const  FILTER_SUFFIX = '_FilterOnly';
    
    /** @var string */
    private $namespace;

    /** @var array<string, Metadata\EntitySet> */
    private $entitySets = [];

    /** @var array<string, Metadata\EntityType> */
    private $entityTypes = [];

    /** @var array<string, array<int, string>> */
    private $enumTypes = [];
    
    /** @var array<Metadata\BoundAction> */
    private $boundActions = [];

    public function __construct(string $namespace)
    {
        $this->namespace = $namespace;
    }

    public function addEnumType(string $name, array $members): void
    {
        $this->enumTypes[ $name ] = $members;
    }

    /**
     * @return string[]
     */
    public function getEnumTypes(): array
    {
        return array_keys($this->enumTypes);
    }

    /**
     * @return ?array<int, string>
     */
    public function getEnumTypeMembers(string $name, bool $nameIncludesNamespace = false): ?array
    {
        if ($nameIncludesNamespace) {
            $name = substr($name, strlen($this->namespace) + 1);
        }

        return $this->enumTypes[ $name ] ?? null;
    }

    public function addEntityType(Metadata\EntityType $entityType): void
    {
        $this->entityTypes[ $entityType->getName() ] = $entityType;
    }

    /**
     * @return array<string, Metadata\EntityType[]>
     */
    public function getEntityTypes(): array
    {
        return $this->entityTypes;
    }

    public function getEntityType(string $name, bool $nameIncludesNamespace = false): ?Metadata\EntityType
    {
        if ($nameIncludesNamespace) {
            $name = substr($name, strlen($this->namespace) + 1);
        }

        return $this->entityTypes[ $name ] ?? null;
    }

    public function addEntitySet(Metadata\EntitySet $entitySet): void
    {
        $this->entitySets[ $entitySet->getName() ] = $entitySet;
    }
    
    public function addBoundAction(Metadata\BoundAction $boundAction): void
    {
        $this->boundActions[] = $boundAction;
    }

    /**
     * @return array<Metadata\BoundAction>
     */
    public function getBoundActions(): array
    {
        return $this->boundActions;
    }

    /**
     * @return array<Metadata\BoundAction>
     */
    public function getBoundActionsFor(string $name): array
    {
        return array_filter($this->boundActions, fn($boundAction) => $boundAction->getEntityType()->getName() === $name);
    }

    /**
     * @return array<string, Metadata\EntitySet>
     */
    public function getEntitySets(): array
    {
        return $this->entitySets;
    }

    public function getEntitySet(string $name): ?Metadata\EntitySet
    {
        return $this->entitySets[ $name ] ?? null;
    }

    public function getEntitySetFor(string $name): ?Metadata\EntitySet
    {
        foreach ($this->entitySets as $entitySet) {
            if ($entitySet->getEntityType()->getName() === $name) {
                return $entitySet;
            }
        }

        return null;
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }
}
