<?php
namespace Request;

use PHPUnit\Framework\TestCase;
use Rebel\BCApi2\Request\ODataValue;
class ODataValueTest extends TestCase
{
    public function testEncodeODataValue()
    {
        $this->assertEquals("'test'", (string)new ODataValue('test'));
        $this->assertEquals("'123'", (string)new ODataValue('123'));
        $this->assertEquals('123', (string)new ODataValue(123));
        $this->assertEquals('99.95', (string)new ODataValue(99.95));
        $this->assertEquals('f3c1c612-fc83-f011-a6f5-000d3a4b6d9d', (string)new ODataValue('f3c1c612-fc83-f011-a6f5-000d3a4b6d9d'));
        $this->assertEquals('2024-01-30T00:00:00.000Z', (string)new ODataValue(new \DateTime('2024-01-30')));
        $this->assertEquals('false', (string)new ODataValue(false));
        $this->assertEquals('true', (string)new ODataValue(true));
        $this->assertEquals('null', (string)new ODataValue(null));
        $this->assertEquals("'testing $'' %'", (string)new ODataValue('testing $\' %'));
    }
}