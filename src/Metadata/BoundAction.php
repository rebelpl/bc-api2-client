<?php
namespace Rebel\BCApi2\Metadata;

readonly class BoundAction
{
    public function __construct(
        private string $name,
        private EntityType $entityType)
    {
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