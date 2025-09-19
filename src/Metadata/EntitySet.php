<?php
namespace Rebel\BCApi2\Metadata;

class EntitySet
{
    const ODATA_CAPABILITIES = 'Org.OData.Capabilities.V1';
    private $name;
    private $entityType;
    private $capabilities;

    public function __construct(
        string $name,
        EntityType $entityType,
        array $capabilities = [])
    {
        $this->capabilities = $capabilities;
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

    public function isDeletable(): bool
    {
        $key = self::ODATA_CAPABILITIES . '.DeleteRestrictions.Deletable';
        return $this->capabilities[ $key ] ?? true;
    }

    public function isInsertable(): bool
    {
        $key = self::ODATA_CAPABILITIES . '.InsertRestrictions.Insertable';
        return $this->capabilities[ $key ] ?? true;
    }

    public function isUpdatable(): bool
    {
        $key = self::ODATA_CAPABILITIES . '.UpdateRestrictions.Updatable';
        return $this->capabilities[ $key ] ?? true;
    }

    public function isSortable(): bool
    {
        $key = self::ODATA_CAPABILITIES . '.SortRestrictions.Sortable';
        return $this->capabilities[ $key ] ?? true;
    }

    public function getCapabilities(): array
    {
        return $this->capabilities;
    }
}
