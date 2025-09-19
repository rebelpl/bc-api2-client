<?php
namespace Rebel\BCApi2\Metadata;

class Property
{
    private $type;
    private $nullable;
    private $maxLength;

    public function __construct(
        string $type,
        bool $nullable = true,
        ?int $maxLength = null)
    {
        $this->maxLength = $maxLength;
        $this->nullable = $nullable;
        $this->type = $type;
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
