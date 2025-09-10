<?php
namespace Rebel\Test\BCApi2\Entity;

use PHPUnit\Framework\TestCase;
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
    
    public function testDateAndDateTimePropertiesHaveCorrectSetters(): void
    {
        $entityType = $this->metadata->getEntityType('salesOrder');
        $classType = $this->generator->generateRecordFor($entityType, true);

        $property = $classType->getProperty('orderDate');
        $setHook = $property->getHook('set');
        $this->assertStringContainsString('setAsDate(', $setHook->getBody());

        $property = $classType->getProperty('lastModifiedDateTime');
        $setHook = $property->getHook('set');
        $this->assertStringContainsString('setAsDateTime(', $setHook->getBody());

    }
    
    public function setHookDoesNotExistForIDProperty(): void
    {
        $entityType = $this->metadata->getEntityType('salesOrder');
        $classType = $this->generator->generateRecordFor($entityType, true);
        
        $property = $classType->getProperty('id');
        $setHook = $property->getHook('set');
        $this->assertNull($setHook);
    }
}