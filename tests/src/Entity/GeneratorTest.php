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
        $entitySet = $this->metadata->getEntitySet('salesOrders');
        $classType = $this->generator->generateRecordFor($entitySet);

        $property = $classType->getProperty('orderDate');
        $this->assertEquals(Carbon::class, $property->getType());
        $this->assertTrue($property->isNullable());

        $getHook = $property->getHook('get');
        $this->assertStringContainsString('get(\'orderDate\', \'date\')', $getHook->getBody());

        $setHook = $property->getHook('set');
        $this->assertStringContainsString('set(\'orderDate\', $value, \'date\');', $setHook->getBody());

        $property = $classType->getProperty('lastModifiedDateTime');
        $getHook = $property->getHook('get');
        $this->assertStringContainsString('get(\'lastModifiedDateTime\', \'datetime\')', $getHook->getBody());
    }

    public function testCollectionNavPropertiesAreCorrect(): void
    {
        $entitySet = $this->metadata->getEntitySet('salesOrders');
        $classType = $this->generator->generateRecordFor($entitySet);

        $property = $classType->getProperty('salesOrderLines');
        $this->assertEquals(Entity\Collection::class, $property->getType());
        $this->assertFalse($property->isNullable());
        
        $getHook = $property->getHook('get');
        $this->assertStringContainsString('get(\'salesOrderLines\', \'collection\')', $getHook->getBody());
    }

    public function testRelationNavPropertiesAreCorrect(): void
    {
        $entitySet = $this->metadata->getEntitySet('salesOrders');
        $classType = $this->generator->generateRecordFor($entitySet);
        
        $property = $classType->getProperty('customer');
        $this->assertEquals('Rebel\\BCApi2\\Entity\\Customer\\Record', $property->getType());
        $this->assertTrue($property->isNullable());

        $getHook = $property->getHook('get');
        $this->assertStringContainsString('get(\'customer\')', $getHook->getBody());
    }

    public function testEnumPropertiesAreCorrect(): void
    {
        $entitySet = $this->metadata->getEntitySet('salesOrders');
        $classType = $this->generator->generateRecordFor($entitySet);

        $property = $classType->getProperty('status');
        $this->assertEquals('Rebel\\BCApi2\\Entity\\Enums\\SalesOrderEntityBufferStatus', $property->getType());
        $this->assertTrue($property->isNullable());

        $getHook = $property->getHook('get');
        $this->assertStringContainsString('get(\'status\', Enums\\SalesOrderEntityBufferStatus::class)', $getHook->getBody());
    }

    public function setHookDoesNotExistForIDProperty(): void
    {
        $entitySet = $this->metadata->getEntitySet('salesOrders');
        $classType = $this->generator->generateRecordFor($entitySet);
        
        $property = $classType->getProperty('id');
        $setHook = $property->getHook('set');
        $this->assertNull($setHook);
    }
    
    public function testBoundActionsAreCorrect(): void
    {
        $entitySet = $this->metadata->getEntitySet('salesOrders');
        $classType = $this->generator->generateRepositoryFor($entitySet);
        
        $method = $classType->getMethod('shipAndInvoice');
        $this->assertEquals('void', $method->getReturnType());
        $this->assertStringContainsString("callBoundAction('Microsoft.NAV.shipAndInvoice'", $method->getBody());
    }
}