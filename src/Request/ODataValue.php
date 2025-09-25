<?php
namespace Rebel\BCApi2\Request;

class ODataValue
{
    const DATETIME_FORMAT = 'Y-m-d\TH:i:s.v\Z';
    const DATE_FORMAT = 'Y-m-d';
    private $value;

    public function __construct($value)
    {
        $this->value = $value;
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
        switch (true) {
            case $this->isGuid(): return $this->value;
            case is_string($this->value): return "'" . str_replace("'", "''", $this->value) . "'";
            case $this->value instanceof Expression:
            case is_float($this->value):
            case is_int($this->value):
                return (string)$this->value;
            case is_bool($this->value): return $this->value ? 'true' : 'false';
            case is_null($this->value): return 'null';
            case ($this->value instanceof \DateTime): return $this->value->format(self::DATETIME_FORMAT);
            default: return "'" . addslashes((string) $this->value) . "'";
        }
    }
}