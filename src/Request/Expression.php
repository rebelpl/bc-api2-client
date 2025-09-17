<?php
namespace Rebel\BCApi2\Request;

/**
 * https://learn.microsoft.com/en-us/dynamics365/business-central/dev-itpro/webservices/use-filter-expressions-in-odata-uris
 * https://docs.oasis-open.org/odata/odata/v4.01/odata-v4.01-part2-url-conventions.html
 */
readonly class Expression
{
    const string DATETIME_FORMAT = 'Y-m-d\TH:i:s.v\Z';
    const string DATE_FORMAT = 'Y-m-d';

    // Comparison operators
    const string
        EQ = 'eq',  // Equal to
        NE = 'ne',  // Not equal to
        IN = 'in',  // In
        NI = 'ni',  // Not in
        GT = 'gt',  // Greater than
        GE = 'ge',  // Greater than or equal to
        LT = 'lt',  // Lesser than
        LE = 'le';  // Lesser than or equal to

    // String functions
    const string
        CONTAINS = 'contains',     // Contains a substring
        STARTSWITH = 'startswith', // Starts with a substring
        ENDSWITH = 'endswith';     // Ends with a substring

    // Logical operators
    const string
        AND = 'and',
        OR = 'or',
        NOT = 'not';

    // Arithmetic operators
    const string
        ADD = 'add',
        SUB = 'sub',
        MUL = 'mul',
        DIV = 'div',
        MOD = 'mod';

    // Collection operators
    const string
        ANY = 'any',      // Any element in a collection satisfies a condition
        ALL = 'all';      // All elements in a collection satisfy a condition

    const array ALTERNATIVES = [
        '=' => self::EQ,
        '<>' => self::NE,
        '!=' => self::NE,
        '>' => self::GT,
        '>=' => self::GE,
        '<' => self::LT,
        '=<' => self::LE,
        '<=' => self::LE,
    ];

    private string $field;
    private string $operator;
    private mixed $value;

    public function __construct(string $field, string $operator, mixed $value)
    {
        $this->field = $field;
        $this->operator = self::ALTERNATIVES[ $operator ] ?? $operator;
        $this->value = $value;
    }

    public function __toString(): string
    {
        if (is_array($this->value)) {
            return match ($this->operator) {
                self::IN => self::or(array_map(fn($val) => self::equals($this->field, $val), $this->value)),
                self::NI => '(' . self::and(array_map(fn($val) => self::notEquals($this->field, $val), $this->value)) . ')',
                default => throw new \Exception("Array values can be only used with 'in' and 'ni' operators."),
            };
        }
        
        return join(' ', [ $this->field, $this->operator, $this->encodeODataValue($this->value) ]);
    }

    public function encodeODataValue(mixed $value): string {
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
        return new Expression($field, self::IN, $values);
    }

    public static function notIn(string $field, array $values): Expression
    {
        return new Expression($field, self::IN, $values);
    }

    public static function equals(string $field, mixed $value): Expression
    {
        return new Expression($field, self::EQ, $value);
    }

    public static function notEquals(string $field, mixed $value): Expression
    {
        return new Expression($field, self::NE, $value);
    }

    public static function greaterThan(string $field, mixed $value): Expression
    {
        return new Expression($field, self::GT, $value);
    }
    
    public static function lesserThan(string $field, mixed $value): Expression
    {
        return new Expression($field, self::GT, $value);
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