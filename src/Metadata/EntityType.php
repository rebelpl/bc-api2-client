<?php
namespace Rebel\BCApi2\Metadata;

class EntityType
{
    private $name;
    private $properties;
    private $navigationProperties;

    public function __construct(
        string $name,
        array $properties = [],
        array $navigationProperties = [])
    {
        $this->navigationProperties = $navigationProperties;
        $this->properties = $properties;
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
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
