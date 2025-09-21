<?php
namespace Rebel\Test\BCApi2\Entity;

use Nette\PhpGenerator\Method;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
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

        $setMethod = $classType->getMethod('setOrderDate');
        $this->assertStringContainsString('setAsDate(', $setMethod->getBody());

        $setMethod = $classType->getMethod('setLastModifiedDateTime');
        $this->assertStringContainsString('setAsDateTime(', $setMethod->getBody());
        
        $param = $setMethod->getParameters()['value'];
        $this->assertEquals(\DateTime::class, $param->getType());
        $this->assertTrue($param->isNullable());
    }

    public function testDateAndDateTimePropertiesHaveCorrectGetters(): void
    {
        $entityType = $this->metadata->getEntityType('salesOrder');
        $classType = $this->generator->generateRecordFor($entityType, true);

        $getMethod = $classType->getMethod('getOrderDate');
        $this->assertStringContainsString('getAsDateTime(', $getMethod->getBody());
        $this->assertTrue($getMethod->isReturnNullable());
        $this->assertEquals(Carbon::class, $getMethod->getReturnType());
    }

    public function setHookDoesNotExistForIDProperty(): void
    {
        $entityType = $this->metadata->getEntityType('salesOrder');
        $classType = $this->generator->generateRecordFor($entityType, true);
        
        $this->assertInstanceOf(Method::class, $classType->getMethod('setId'));
        $this->assertNull($classType->getMethod('setId'));
    }
}