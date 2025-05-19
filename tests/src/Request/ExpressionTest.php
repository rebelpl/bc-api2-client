<?php
namespace Rebel\Test\BCApi2\Request;

use PHPUnit\Framework\TestCase;
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
}