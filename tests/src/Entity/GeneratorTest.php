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
    
    public function testDateAndDateTimePropertiesAreCorrect(): void
    {
        $entityType = $this->metadata->getEntityType('salesOrder');
        $classType = $this->generator->generateRecordFor($entityType, true);

        $getMethod = $classType->getMethod('getOrderDate');
        $this->assertStringContainsString('getAsDate(', $getMethod->getBody());
        $this->assertEquals(Carbon::class, $getMethod->getReturnType());
        $this->assertTrue($getMethod->isReturnNullable());

        $setMethod = $classType->getMethod('setOrderDate');
        $this->assertStringContainsString('setAsDate(', $setMethod->getBody());

        $setMethod = $classType->getMethod('setLastModifiedDateTime');
        $this->assertStringContainsString('setAsDateTime(', $setMethod->getBody());
        
        $param = $setMethod->getParameters()['value'];
        $this->assertEquals(\DateTime::class, $param->getType());
        $this->assertTrue($param->isNullable());
    }

    public function testCollectionNavPropertiesAreCorrect(): void
    {
        $entityType = $this->metadata->getEntityType('salesOrder');
        $classType = $this->generator->generateRecordFor($entityType, true);

        $getMethod = $classType->getMethod('getSalesOrderLines');
        $this->assertEquals(Entity\Collection::class, $getMethod->getReturnType());
        $this->assertFalse($getMethod->isReturnNullable());

        $this->assertStringContainsString('getAsCollection(', $getMethod->getBody());
    }

    public function testSingleNavPropertiesAreCorrect(): void
    {
        $entityType = $this->metadata->getEntityType('salesOrder');
        $classType = $this->generator->generateRecordFor($entityType, true);

        $getMethod = $classType->getMethod('getCustomer');
        $this->assertEquals('Rebel\\BCApi2\\Entity\\Customer\\Record', $getMethod->getReturnType());
        $this->assertTrue($getMethod->isReturnNullable());
        $this->assertStringContainsString('get(', $getMethod->getBody());
    }

    public function testEnumPropertiesAreCorrect(): void
    {
        $entityType = $this->metadata->getEntityType('salesOrder');
        $classType = $this->generator->generateRecordFor($entityType, true);

        $getMethod = $classType->getMethod('getStatus');
        $this->assertEquals('string', $getMethod->getReturnType());
        $this->assertTrue($getMethod->isReturnNullable());
        $this->assertStringContainsString('get(', $getMethod->getBody());
    }

    public function setMethodDoesNotExistForIDProperty(): void
    {
        $entityType = $this->metadata->getEntityType('salesOrder');
        $classType = $this->generator->generateRecordFor($entityType, true);
        $this->assertNull($classType->getMethod('setId'));
    }
}