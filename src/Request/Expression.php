<?php
namespace Rebel\BCApi2\Request;

use Rebel\BCApi2\Exception\InvalidRequestExpression;

/**
 * https://learn.microsoft.com/en-us/dynamics365/business-central/dev-itpro/webservices/use-filter-expressions-in-odata-uris
 * https://docs.oasis-open.org/odata/odata/v4.01/odata-v4.01-part2-url-conventions.html
 */
class Expression
{
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
        if (is_array($this->value)) {
            if (count($this->value) === 0) {
                throw new InvalidRequestExpression(sprintf("Array value used for '%s %s' filter cannot be empty.", $this->field, $this->operator));
            }
            
            if (!in_array($this->operator, [ self::EQ, self::NE, self::STARTSWITH, self::ENDSWITH, self::CONTAINS ])) {
                throw new InvalidRequestExpression(sprintf("Array value cannot be used with '%s %s' filter.", $this->field, $this->operator));
            }
        }
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
            }
        }

        switch ($this->operator) {
            case self::STARTSWITH:
            case self::ENDSWITH:
            case self::CONTAINS:
                return sprintf('%s(%s, %s)', $this->operator, $this->field, new ODataValue($this->value));
            
            default:
                return join(' ', [ $this->field, $this->operator, new ODataValue($this->value) ]);
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