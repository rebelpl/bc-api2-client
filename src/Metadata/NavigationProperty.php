<?php
namespace Rebel\BCApi2\Metadata;

class NavigationProperty
{
    private $type;
    private $partner;
    private $references;

    public function __construct(
        string $type,
        ?string $partner,
        array $references)
    {
        $this->references = $references;
        $this->partner = $partner;
        $this->type = $type;
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

        return substr($this->type, 11, -1);
    }
}
