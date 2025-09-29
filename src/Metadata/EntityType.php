<?php
namespace Rebel\BCApi2\Metadata;

readonly class EntityType
{
    public function __construct(
        private string $name,
        private string $primaryKey,
        private array $properties = [],
        private array $navigationProperties = [])
    {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPrimaryKey(): string
    {
        return $this->primaryKey;
    }

    /**
     * @return array<string, Property>
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    public function hasProperty(string $name): bool
    {
        return isset($this->properties[ $name ]);
    }

    public function getProperty(string $name): Property
    {
        return $this->properties[ $name ];
    }

    /**
     * @return array<string, NavigationProperty>
     */
    public function getNavigationProperties(): array
    {
        return $this->navigationProperties;
    }

    public function hasNavigationProperty(string $name): bool
    {
        return isset($this->navigationProperties[ $name ]);
    }

    public function getNavigationProperty(string $name): NavigationProperty
    {
        return $this->navigationProperties[ $name ];
    }

}
