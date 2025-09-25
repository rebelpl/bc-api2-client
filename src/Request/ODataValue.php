<?php
namespace Rebel\BCApi2\Request;

readonly class ODataValue
{
    const string DATETIME_FORMAT = 'Y-m-d\TH:i:s.v\Z';
    const string DATE_FORMAT = 'Y-m-d';
    
    public function __construct(
        private mixed $value)
    {
    }

    public function isGuid(): bool
    {
        if (!is_string($this->value)) {
            return false;
        }

        // f3c1c612-fc83-f011-a6f5-000d3a4b6d9d
        if (strlen($this->value) != 36) {
            return false;
        }

        return preg_match("/^[a-f\d]{8}(-[a-f\d]{4}){4}[a-f\d]{8}$/i", $this->value) === 1;
    }
    
    public function __toString(): string
    {
        return match (true) {
            $this->isGuid() => $this->value,
            is_string($this->value) => "'" . str_replace("'", "''", $this->value) . "'",
            $this->value instanceof Expression, is_float($this->value), is_int($this->value) => (string)$this->value,
            is_bool($this->value) => $this->value ? 'true' : 'false',
            is_null($this->value) => 'null',
            $this->value instanceof \DateTime => $this->value->format(self::DATETIME_FORMAT),
            default => "'" . addslashes((string)$this->value) . "'",
        };
    }
}