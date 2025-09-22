<?php
namespace Rebel\Test\BCApi2\Entity;

use Nette\PhpGenerator\Method;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use Rebel\BCApi2\Entity;
use Rebel\BCApi2\Entity\Generator;
use Rebel\BCApi2\Metadata;

class GeneratorTest extends TestCase
{
    /** @var Generator */
    private $generator;
    
    /** @var Metadata */
    private $metadata;
    
    protected function setUp(): void
    {
        $xml = simplexml_load_file('tests/files/metadata.xml');
        $this->metadata = Metadata\Factory::fromXml($xml);
        $this->generator = new Generator($this->metadata);
    }
    
    public function testDateAndDateTimePropertiesHaveCorrectSetters(): void
    {
        $entityType = $this->metadata->getEntityType('salesOrder');
        $classType = $this->generator->generateRecordFor($entityType, true);
    public function testDateAndDateTimePropertiesAreCorrect(): void
    {
        $entityType = $this->metadata->getEntityType('salesOrder');
        $classType = $this->generator->generateRecordFor($entityType, true);

        $property = $classType->getProperty('orderDate');
        $this->assertEquals(Carbon::class, $property->getType());
        $this->assertTrue($property->isNullable());

        $setMethod = $classType->getMethod('setOrderDate');
        $this->assertStringContainsString('setAsDate(', $setMethod->getBody());
        $setHook = $property->getHook('set');
        $this->assertStringContainsString('setAsDate(', $setHook->getBody());

        $setMethod = $classType->getMethod('setLastModifiedDateTime');
        $this->assertStringContainsString('setAsDateTime(', $setMethod->getBody());
        
        $param = $setMethod->getParameters()['value'];
        $this->assertEquals(\DateTime::class, $param->getType());
        $this->assertTrue($param->isNullable());
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

        $getMethod = $classType->getMethod('getOrderDate');
        $this->assertStringContainsString('getAsDateTime(', $getMethod->getBody());
        $this->assertTrue($getMethod->isReturnNullable());
        $this->assertEquals(Carbon::class, $getMethod->getReturnType());
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
        
        $this->assertInstanceOf(Method::class, $classType->getMethod('setId'));
        $this->assertNull($classType->getMethod('setId'));
    }
}