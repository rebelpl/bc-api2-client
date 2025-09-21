<?php
namespace Rebel\BCApi2\Request;

/**
 * https://learn.microsoft.com/en-us/dynamics365/business-central/dev-itpro/webservices/use-filter-expressions-in-odata-uris
 * https://docs.oasis-open.org/odata/odata/v4.01/odata-v4.01-part2-url-conventions.html
 */
class Expression
{
    const DATETIME_FORMAT = 'Y-m-d\TH:i:s.v\Z';
    const DATE_FORMAT = 'Y-m-d';

    // Comparison operators
    const 
        EQ = 'eq',  // Equal to
        NE = 'ne',  // Not equal to
        GT = 'gt',  // Greater than
        GE = 'ge',  // Greater than or equal to
        LT = 'lt',  // Lesser than
        LE = 'le';  // Lesser than or equal to

    // String functions
    const 
        CONTAINS = 'contains',     // Contains a substring
        STARTSWITH = 'startswith', // Starts with a substring
        ENDSWITH = 'endswith';     // Ends with a substring

    // Logical operators
    const 
        AND = 'and',
        OR = 'or',
        NOT = 'not';

    // Arithmetic operators
    const 
        ADD = 'add',
        SUB = 'sub',
        MUL = 'mul',
        DIV = 'div',
        MOD = 'mod';

    // Collection operators
    const 
        ANY = 'any',      // Any element in a collection satisfies a condition
        ALL = 'all';      // All elements in a collection satisfy a condition

    const ALTERNATIVES = [
        '=' => self::EQ,
        '<>' => self::NE,
        '!=' => self::NE,
        '>' => self::GT,
        '>=' => self::GE,
        '<' => self::LT,
        '=<' => self::LE,
        '<=' => self::LE,
    ];

    private $field;
    private $operator;
    private $value;

    public function __construct(string $field, string $operator, $value)
    {
        $this->field = $field;
        $this->operator = self::ALTERNATIVES[ $operator ] ?? $operator;
        $this->value = $value;
    }

    public function __toString(): string
    {
        if (is_array($this->value)) {
            switch ($this->operator) {
                case self::EQ:
                    return self::or(array_map(function ($value) {
                        return self::equals($this->field, $value);
                    }, $this->value));
                    
                case self::NE:
                    return self::and(array_map(function ($value) {
                            return self::notEquals($this->field, $value);
                        }, $this->value));

                case self::STARTSWITH:
                    return self::or(array_map(function ($value) {
                        return self::startsWith($this->field, $value);
                    }, $this->value));

                case self::ENDSWITH:
                    return self::or(array_map(function ($value) {
                        return self::endsWith($this->field, $value);
                    }, $this->value));

                case self::CONTAINS:
                    return self::or(array_map(function ($value) {
                        return self::contains($this->field, $value);
                    }, $this->value));

                default:
                    return '';
            }
        }

        switch ($this->operator) {
            case self::STARTSWITH:
            case self::ENDSWITH:
            case self::CONTAINS:
                return sprintf('%s(%s, %s)', $this->operator, $this->field, $this->encodeODataValue($this->value));
            
            default:
                return join(' ', [ $this->field, $this->operator, $this->encodeODataValue($this->value) ]);
        }
    }

    public function encodeODataValue($value): string {
        if (is_string($value)) {
            // Escape single quotes and wrap the value in single quotes
            $value = str_replace("'", "''", $value);
            return "'$value'";
        } elseif (is_numeric($value)) {
            // Return numeric values as is
            return (string)$value;
        } elseif (is_bool($value)) {
            // Convert boolean to lowercase string
            return $value ? 'true' : 'false';
        } elseif (is_null($value)) {
            // Handle null values
            return 'null';
        } elseif ($value instanceof \DateTime) {
            // Format DateTime objects as OData-compatible strings
            return $value->format(self::DATETIME_FORMAT);
        } elseif ($value instanceof Expression) {
            // Return as is
            return (string)$value;
        } else {
            // Fallback for other types (e.g., arrays or objects)
            return "'" . addslashes((string) $value) . "'";
        }
    }

    public static function in(string $field, array $values): Expression
    {
        return self::equals($field, $values);
    }

    public static function notIn(string $field, array $values): Expression
    {
        return self::notEquals($field, $values);
    }

    public static function equals(string $field, $value): Expression
    {
        return new Expression($field, self::EQ, $value);
    }

    public static function notEquals(string $field, $value): Expression
    {
        return new Expression($field, self::NE, $value);
    }

    public static function greaterThan(string $field, $value, bool $includeEqual = false): Expression
    {
        return new Expression($field, $includeEqual ? self::GE : self::GT, $value);
    }

    public static function greaterOrEqualThan(string $field, $value): Expression
    {
        return self::greaterThan($field, $value, true);
    }
    
    public static function lesserThan(string $field, $value, bool $includeEqual = false): Expression
    {
        return new Expression($field, $includeEqual ? self::LE : self::LT, $value);
    }

    public static function lesserOrEqualThan(string $field, $value): Expression
    {
        return self::lesserThan($field, $value, true);
    }

    public static function startsWith(string $field, $value): Expression
    {
        return new Expression($field, self::STARTSWITH, $value);
    }

    public static function endsWith(string $field, $value): Expression
    {
        return new Expression($field, self::ENDSWITH, $value);
    }

    public static function contains(string $field, $value): Expression
    {
        return new Expression($field, self::CONTAINS, $value);
    }

    public static function and(array $expressions): string
    {
        return join(' ' . self::AND . ' ', $expressions);
    }

    public static function or(array $expressions): string
    {
        return '(' . join(' ' . self::OR . ' ', $expressions) . ')';
    }
}