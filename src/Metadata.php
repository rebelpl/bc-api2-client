<?php
namespace Rebel\BCApi2;

class Metadata
{
    private string $namespace;

    /** @var array<string, Metadata\EntitySet> */
    private array $entitySets = [];

    /** @var array<string, Metadata\EntityType> */
    private array $entityTypes = [];

    /** @var array<string, array<int, string>> */
    private array $enumTypes = [];

    public function __construct(string $namespace)
    {
        $this->namespace = $namespace;
    }

    public function addEnumType(string $enumType, array $members): void
    {
        $this->enumTypes[ $enumType ] = $members;
    }

    /**
     * @return string[]
     */
    public function getEnumTypes(): array
    {
        return array_keys($this->enumTypes);
    }

    /**
     * @return array<int, string>
     */
    public function getEnumTypeMembers(string $enumType): array
    {
        return $this->enumTypes[ $enumType ];
    }

    public function addEntityType(string $name, Metadata\EntityType $entityType): void
    {
        $this->entityTypes[ $name ] = $entityType;
    }

    /**
     * @return array<string, Metadata\EntityType[]>
     */
    public function getEntityTypes(): array
    {
        return $this->entityTypes;
    }

    public function getEntityType(string $name): ?Metadata\EntityType
    {
        return $this->entityTypes[ $name ] ?? null;
    }

    public function addEntitySet(string $name, Metadata\EntitySet $entitySet): void
    {
        $this->entitySets[ $name ] = $entitySet;
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

    public function getEntitySetForType(string $type): ?Metadata\EntitySet
    {
        $entityType = $this->namespace . '.' . $type;
        foreach ($this->entitySets as $entitySet) {
            if ($entitySet->getType() === $entityType) {
                return $entitySet;
            }
        }

        return null;
    }
}
