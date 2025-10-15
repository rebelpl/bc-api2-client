<?php
namespace Rebel\Test\BCApi2\Entity;

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
    
    public function testDateAndDateTimePropertiesAreCorrect(): void
    {
        $entitySet = $this->metadata->getEntitySet('salesOrders');
        $classType = $this->generator->generateRecordFor($entitySet);
        
        $comment = $classType->getComment();
        $this->assertStringContainsString('@property ?Carbon $orderDate', $comment);
        $this->assertStringContainsString('@property-read ?Carbon $lastModifiedDateTime', $comment);
    }

    public function testCollectionNavPropertiesAreCorrect(): void
    {
        $entitySet = $this->metadata->getEntitySet('salesOrders');
        $classType = $this->generator->generateRecordFor($entitySet);

        $comment = $classType->getComment();
        $this->assertStringContainsString('@property Entity\Collection|SalesOrderLine\Record[] $salesOrderLines', $comment);
    }

    public function testRelationNavPropertiesAreCorrect(): void
    {
        $entitySet = $this->metadata->getEntitySet('salesOrders');
        $classType = $this->generator->generateRecordFor($entitySet);

        $comment = $classType->getComment();
        $this->assertStringContainsString('@property-read ?Customer\Record $customer', $comment);
    }

    public function testEnumPropertiesAreCorrect(): void
    {
        $entitySet = $this->metadata->getEntitySet('salesOrders');
        $classType = $this->generator->generateRecordFor($entitySet);

        $comment = $classType->getComment();
        $this->assertStringContainsString('@property ?string $status', $comment);
    }

    public function setReadOnlyProperties(): void
    {
        $entitySet = $this->metadata->getEntitySet('salesOrders');
        $classType = $this->generator->generateRecordFor($entitySet);

        $comment = $classType->getComment();
        $this->assertStringContainsString('@property-read ?string $id', $comment);
        $this->assertStringContainsString('@property-read ?Carbon $lastModifiedDateTime', $comment);
    }
    
    public function testBoundActionsAreCorrect(): void
    {
        $entitySet = $this->metadata->getEntitySet('salesOrders');
        $classType = $this->generator->generateRepositoryFor($entitySet);
        
        $method = $classType->getMethod('shipAndInvoice');
        $this->assertEquals('void', $method->getReturnType());
        $this->assertStringContainsString("callBoundAction('Microsoft.NAV.shipAndInvoice'", $method->getBody());
    }

    public function testEntityTypeCasesWithRegularKeys(): void
    {
        $enumType = $this->generator->generateEnumTypeFor('jobQueuePriority');
        $cases = $enumType->getCases();
        $this->assertArrayHasKey('High', $cases);

        foreach ($cases as $case) {
            $this->assertEquals($case->getValue(), $case->getName());
        }
    }

    public function testEntityTypeCasesWithSpecialCharacters(): void
    {
        $enumType = $this->generator->generateEnumTypeFor('jobQueueReportOutputType');
        $cases = $enumType->getCases();
        $this->assertArrayHasKey('NoneProcessingonly', $cases);
        
        $case = $cases['NoneProcessingonly'];
        $this->assertEquals('NoneProcessingonly', $case->getName());
        $this->assertEquals('None_x0020__x0028_Processing_x0020_only_x0029_', $case->getValue());
    }

    public function testEntityTypeCasesWithNullValue(): void
    {
        $enumType = $this->generator->generateEnumTypeFor('defaultDimensionParentType');
        $cases = $enumType->getCases();
        $this->assertArrayHasKey('Null', $cases);

        $case = $cases['Null'];
        $this->assertEquals('_x0020_', $case->getValue());
    }

    public function testEntityTypeCasesWithNumericalKeys(): void
    {
        $enumType = $this->generator->generateEnumTypeFor('idysSourceDocumentType');
        $cases = $enumType->getCases();
        $this->assertArrayHasKey('Value0', $cases);
        
        foreach ($cases as $case) {
            $this->assertEquals('Value' . $case->getValue(), $case->getName());
        }
    }
}