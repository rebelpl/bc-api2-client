<?php
namespace Rebel\BCApi2\Metadata;

readonly class NavigationProperty
{
    public function __construct(
        private string $type,
        private ?string $partner,
        private array $references)
    {
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getPartner(): ?string
    {
        return $this->partner;
    }

    public function getReferences(): array
    {
        return $this->references;
    }

    public function getReferencedProperty($property): ?string
    {
        return $this->references[ $property ] ?? null;
    }

    public function isCollection(): bool
    {
        return str_starts_with($this->type, 'Collection(');
    }

    public function getCollectionType(): ?string
    {
        if (!$this->isCollection()) {
            return null;
        }

        // strlen('Collection(') === 11
        return substr($this->type, 11, -1);
    }
}