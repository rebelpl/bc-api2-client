<?php
namespace Rebel\BCApi2\Metadata;

readonly class Property
{
    public function __construct(
        private string $type,
        private bool $nullable = true,
        private ?int $maxLength = null)
    {
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function isNullable(): bool
    {
        return $this->nullable;
    }

    public function getMaxLength(): ?int
    {
        return $this->maxLength;
    }
}
