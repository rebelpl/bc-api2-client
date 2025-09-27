<?php
namespace Rebel\BCApi2\Request;

use Rebel\BCApi2\Exception;

/**
 * https://learn.microsoft.com/en-us/dynamics365/business-central/dev-itpro/webservices/use-filter-expressions-in-odata-uris
 * https://docs.oasis-open.org/odata/odata/v4.01/odata-v4.01-part2-url-conventions.html
 */
readonly class Expression
{
    // Comparison operators
    const string
        EQ = 'eq',  // Equal to
        NE = 'ne',  // Not equal to
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
        'in' => self::EQ,
        'ni' => self::NE,
    ];

    private string $field;
    private string $operator;
    private mixed $value;

    public function __construct(string $field, string $operator, mixed $value)
    {
        $this->field = $field;
        $this->operator = self::ALTERNATIVES[ $operator ] ?? $operator;
        
        $this->value = $value;
        if (is_array($this->value)) {
            if (count($this->value) === 0) {
                throw new Exception\InvalidRequestExpressionException(sprintf("Array value used for '%s %s' filter cannot be empty.", $this->field, $this->operator));
            }
            
            if (!in_array($this->operator, [ self::EQ, self::NE, self::STARTSWITH, self::ENDSWITH, self::CONTAINS ])) {
                throw new Exception\InvalidRequestExpressionException(sprintf("Array value cannot be used with '%s %s' filter.", $this->field, $this->operator));
            }
        }
    }

    public function __toString(): string
    {
        if (is_array($this->value)) {
            return match ($this->operator) {
                self::EQ => self::or(array_map(fn($val) => self::equals($this->field, $val), $this->value)),
                self::NE => self::and(array_map(fn($val) => self::notEquals($this->field, $val), $this->value)),

                self::STARTSWITH => self::or(array_map(fn($val) => self::startsWith($this->field, $val), $this->value)),
                self::ENDSWITH   => self::or(array_map(fn($val) => self::endsWith($this->field, $val), $this->value)),
                self::CONTAINS   => self::or(array_map(fn($val) => self::contains($this->field, $val), $this->value)),
            };
        }

        return match ($this->operator) {
            self::STARTSWITH, self::ENDSWITH, self::CONTAINS
                => sprintf('%s(%s, %s)', $this->operator, $this->field, new ODataValue($this->value)),
            default => join(' ', [ $this->field, $this->operator, new ODataValue($this->value) ]), 
        };
    }

    public static function in(string $field, array $values): Expression
    {
        return self::equals($field, $values);
    }

    public static function notIn(string $field, array $values): Expression
    {
        return self::notEquals($field, $values);
    }

    public static function equals(string $field, mixed $value): Expression
    {
        return new Expression($field, self::EQ, $value);
    }

    public static function notEquals(string $field, mixed $value): Expression
    {
        return new Expression($field, self::NE, $value);
    }

    public static function greaterThan(string $field, mixed $value, bool $includeEqual = false): Expression
    {
        return new Expression($field, $includeEqual ? self::GE : self::GT, $value);
    }

    public static function greaterOrEqualThan(string $field, mixed $value): Expression
    {
        return self::greaterThan($field, $value, true);
    }

    public static function lesserThan(string $field, mixed $value, bool $includeEqual = false): Expression
    {
        return new Expression($field, $includeEqual ? self::LE : self::LT, $value);
    }

    public static function lesserOrEqualThan(string $field, mixed $value): Expression
    {
        return self::lesserThan($field, $value, true);
    }

    public static function startsWith(string $field, mixed $value): Expression
    {
        return new Expression($field, self::STARTSWITH, $value);
    }

    public static function endsWith(string $field, mixed $value): Expression
    {
        return new Expression($field, self::ENDSWITH, $value);
    }

    public static function contains(string $field, mixed $value): Expression
    {
        return new Expression($field, self::CONTAINS, $value);
    }

    public static function and(array $expressions): string
    {
        return join(' ' . self::AND . ' ', array_map(function ($key, $value) {
            if ($value instanceof Expression) {
                return $value;
            }

            if (is_int($key)) {
                return $value;
            }

            if (is_array($value)) {
                return Expression::in($key, $value);
            }

            return Expression::equals($key, $value);
        }, array_keys($expressions), $expressions));
    }

    public static function or(array $expressions): string
    {
        return '(' . join(' ' . self::OR . ' ', $expressions) . ')';
    }
}