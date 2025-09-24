<?php
namespace Rebel\Test\BCApi2\Request;

use PHPUnit\Framework\TestCase;
use Rebel\BCApi2\Exception\InvalidRequestExpression;
use Rebel\BCApi2\Request\Expression;

class ExpressionTest extends TestCase
{
    public function testSimpleExpressions()
    {
        $expression = new Expression('amount', '>', 512.99);
        $this->assertEquals("amount gt 512.99", (string)$expression);

        $expression = new Expression('number', Expression::EQ, 'ZS-TEST');
        $this->assertEquals("number eq 'ZS-TEST'", (string)$expression);

        $expression = new Expression('customerName', Expression::NE, "Test User's Name");
        $this->assertEquals("customerName ne 'Test User''s Name'", (string)$expression);

        $expression = new Expression('postingDate', Expression::GT, new \DateTime('2024-01-30'));
        $this->assertEquals("postingDate gt 2024-01-30T00:00:00.000Z", (string)$expression);

        $datetime = \DateTime::createFromFormat(Expression::DATETIME_FORMAT, '2025-01-20T12:12:14.917Z');
        $expression = new Expression('postingDate', Expression::GT, $datetime);
        $this->assertEquals("postingDate gt 2025-01-20T12:12:14.917Z", (string)$expression);

        $expression = new Expression('pricesIncludeTax', Expression::EQ, true);
        $this->assertEquals("pricesIncludeTax eq true", (string)$expression);
    }

    public function testStaticConstructors()
    {
        $expression = Expression::greaterOrEqualThan('amount', 512.99);
        $this->assertEquals("amount ge 512.99", (string)$expression);

        $expression = Expression::lesserThan('amount', 512.99);
        $this->assertEquals("amount lt 512.99", (string)$expression);
        
        $expression = Expression::in('customerNo', [ 'CU-TEST', 'CU-ANOTHER' ]);
        $this->assertEquals("(customerNo eq 'CU-TEST' or customerNo eq 'CU-ANOTHER')", (string)$expression);

        $expression = Expression::notIn('orderNo', [ 'ZS-TEST', 'ZS-ANOTHER' ]);
        $this->assertEquals("orderNo ne 'ZS-TEST' and orderNo ne 'ZS-ANOTHER'", (string)$expression);

        $expression = Expression::startsWith('description', [ 'foo', 'bar' ]);
        $this->assertEquals("(startswith(description, 'foo') or startswith(description, 'bar'))", (string)$expression);
    }

    public function testComplexExpressions()
    {
        $expression = Expression::and([
            Expression::or([
                new Expression('a', 'eq',  'b'),
                new Expression('a', 'eq',  'c')
            ]),
            Expression::equals('foo', 'bar'),
        ]); 
        
        $this->assertEquals("(a eq 'b' or a eq 'c') and foo eq 'bar'", $expression);
    }
    
    public function testEmptyInExpressionThrowsException()
    {
        $this->expectException(InvalidRequestExpression::class);
        Expression::in('foo', []);
    }

    public function testArrayValueGreaterThanExpressionThrowsException()
    {
        $this->expectException(InvalidRequestExpression::class);
        Expression::greaterThan('foo', []);
    }
}