<?php
namespace Rebel\BCApi2\Metadata;

class BoundAction
{
    private $name;
    private $entityType;

    public function __construct(
        string $name,
        EntityType $entityType)
    {
        $this->entityType = $entityType;
        $this->name = $name;
    }
    
    public function getName(): string
    {
        return $this->name;
    }

    public function getEntityType(): EntityType
    {
        return $this->entityType;
    }
}