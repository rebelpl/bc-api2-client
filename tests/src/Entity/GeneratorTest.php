<?php
namespace Rebel\Test\BCApi2\Entity;

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
        $entitySet = $this->metadata->getEntitySet('salesOrders');
        $classType = $this->generator->generateRecordFor($entitySet);
        
        $comment = $classType->getComment();
        $this->assertStringContainsString('@property ?Carbon orderDate', $comment);
        $this->assertStringContainsString('@property-read ?Carbon lastModifiedDateTime', $comment);
    }

    public function testCollectionNavPropertiesAreCorrect(): void
    {
        $entitySet = $this->metadata->getEntitySet('salesOrders');
        $classType = $this->generator->generateRecordFor($entitySet);

        $comment = $classType->getComment();
        $this->assertStringContainsString('@property SalesOrderLine\Record[]|Entity\Collection<SalesOrderLine\Record> salesOrderLines', $comment);
    }

    public function testRelationNavPropertiesAreCorrect(): void
    {
        $entitySet = $this->metadata->getEntitySet('salesOrders');
        $classType = $this->generator->generateRecordFor($entitySet);

        $comment = $classType->getComment();
        $this->assertStringContainsString('@property-read ?Customer\Record customer', $comment);
    }

    public function testEnumPropertiesAreCorrect(): void
    {
        $entitySet = $this->metadata->getEntitySet('salesOrders');
        $classType = $this->generator->generateRecordFor($entitySet);

        $comment = $classType->getComment();
        $this->assertStringContainsString('@property ?string status', $comment);
    }

    public function setReadOnlyProperties(): void
    {
        $entitySet = $this->metadata->getEntitySet('salesOrders');
        $classType = $this->generator->generateRecordFor($entitySet);

        $comment = $classType->getComment();
        $this->assertStringContainsString('@property-read ?string id', $comment);
        $this->assertStringContainsString('@property-read ?Carbon lastModifiedDateTime', $comment);
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