<?php
namespace Rebel\Test\BCApi2\Entity;

use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use Rebel\BCApi2\Entity;
use Rebel\BCApi2\Entity\Generator;
use Rebel\BCApi2\Metadata;

class GeneratorTest extends TestCase
{
    private Generator $generator;
    private Metadata $metadata;
    
    protected function setUp(): void
    {
        $xml = simplexml_load_file('tests/files/metadata.xml');
        $this->metadata = Metadata\Factory::fromXml($xml);
        $this->generator = new Generator($this->metadata);
    }
    
    public function testDateAndDateTimePropertiesAreCorrect(): void
    {
        $entityType = $this->metadata->getEntityType('salesOrder');
        $classType = $this->generator->generateRecordFor($entityType, true);

        $property = $classType->getProperty('orderDate');
        $this->assertEquals(Carbon::class, $property->getType());
        $this->assertTrue($property->isNullable());

        $setHook = $property->getHook('set');
        $this->assertStringContainsString('setAsDate(', $setHook->getBody());

        $property = $classType->getProperty('lastModifiedDateTime');
        $setHook = $property->getHook('set');
        $this->assertStringContainsString('setAsDateTime(', $setHook->getBody());
        
        $property = $classType->getProperty('orderDate');
        $getHook = $property->getHook('get');
        $this->assertStringContainsString('getAsDate(', $getHook->getBody());
    }

    public function testCollectionNavPropertiesAreCorrect(): void
    {
        $entityType = $this->metadata->getEntityType('salesOrder');
        $classType = $this->generator->generateRecordFor($entityType, true);

        $property = $classType->getProperty('salesOrderLines');
        $this->assertEquals(Entity\Collection::class, $property->getType());
        $this->assertFalse($property->isNullable());
        
        $getHook = $property->getHook('get');
        $this->assertStringContainsString('getAsCollection(', $getHook->getBody());
    }

    public function testSingleNavPropertiesAreCorrect(): void
    {
        $entityType = $this->metadata->getEntityType('salesOrder');
        $classType = $this->generator->generateRecordFor($entityType, true);
        
        $property = $classType->getProperty('customer');
        $this->assertEquals('Rebel\\BCApi2\\Entity\\Customer\\Record', $property->getType());
        $this->assertTrue($property->isNullable());

        $getHook = $property->getHook('get');
        $this->assertStringContainsString('get(', $getHook->getBody());
    }

    public function testEnumPropertiesAreCorrect(): void
    {
        $entityType = $this->metadata->getEntityType('salesOrder');
        $classType = $this->generator->generateRecordFor($entityType, true);

        $property = $classType->getProperty('status');
        $this->assertEquals('Rebel\\BCApi2\\Entity\\Enums\\SalesOrderEntityBufferStatus', $property->getType());
        $this->assertTrue($property->isNullable());

        $getHook = $property->getHook('get');
        $this->assertStringContainsString('getAsEnum(', $getHook->getBody());
    }

    public function setHookDoesNotExistForIDProperty(): void
    {
        $entityType = $this->metadata->getEntityType('salesOrder');
        $classType = $this->generator->generateRecordFor($entityType, true);
        
        $property = $classType->getProperty('id');
        $setHook = $property->getHook('set');
        $this->assertNull($setHook);
    }
    
    public function testBoundActionsAreCorrect(): void
    {
        $entityType = $this->metadata->getEntityType('salesOrder');
        $classType = $this->generator->generateRecordFor($entityType, true);
        
        $method = $classType->getMethod('doShipAndInvoice');
        $this->assertEquals('void', $method->getReturnType());
        $this->assertStringContainsString("doAction('Microsoft.NAV.shipAndInvoice'", $method->getBody());
    }
}