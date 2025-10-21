<?php
namespace Rebel\Test\BCApi2;

use PHPUnit\Framework\TestCase;
use Rebel\BCApi2\Metadata;

class MetadataTest extends TestCase
{
    private Metadata $metadata;

    public function setUp(): void
    {
        $xml = simplexml_load_file('tests/files/metadata.xml');
        $this->metadata = Metadata\Factory::fromXml($xml);
    }
    public function testEnumTypes()
    {
        $this->assertCount(35, $this->metadata->getEnumTypes());
        $this->assertContains('Inventory', $this->metadata->getEnumTypeMembers('itemType'));
        $this->assertEquals([
            1 => 'Customer',
            2 => 'Item',
            3 => 'Vendor',
            4 => 'Employee',
            5 => 'Contact',
        ], $this->metadata->getEnumTypeMembers('pictureEntityParentType'));
    }

    public function testEntityTypes()
    {
        $this->assertCount(87, $this->metadata->getEntityTypes());
        $entityType = $this->metadata->getEntityType('Microsoft.NAV.item', true);
        $this->assertEquals('item', $entityType->getName());
        $this->assertEquals('id', $entityType->getPrimaryKey());

        $this->assertCount(22, $entityType->getProperties());
        $this->assertCount(8, $entityType->getNavigationProperties());

        $this->assertArrayHasKey('number', $entityType->getProperties());
        $this->assertTrue($entityType->hasProperty('displayName'));

        $property = $entityType->getProperty('id');
        $this->assertEquals('Edm.Guid', $property->getType());
        $this->assertFalse($property->isNullable());

        $property = $entityType->getProperty('unitPrice');
        $this->assertEquals('Edm.Decimal', $property->getType());
        $this->assertTrue($property->isNullable());

        $property = $entityType->getProperty('baseUnitOfMeasureCode');
        $this->assertEquals('Edm.String', $property->getType());
        $this->assertEquals(10, $property->getMaxLength());

        $this->assertTrue($entityType->hasNavigationProperty('itemCategory'));

        $navigationProperty = $entityType->getNavigationProperty('inventoryPostingGroup');
        $this->assertFalse($navigationProperty->isCollection());
        $this->assertEquals([
            'inventoryPostingGroupId' => 'id',
        ], $navigationProperty->getReferences());
        $this->assertEquals('Microsoft.NAV.inventoryPostingGroup', $navigationProperty->getType());

        $entityType = $this->metadata->getEntityType('salesOrder', false);
        $property = $entityType->getProperty('orderDate');
        $this->assertEquals('Edm.Date', $property->getType());
        
        $entityType = $this->metadata->getEntityType('subscriptions');
        $this->assertEquals('subscriptionId', $entityType->getPrimaryKey());
    }

    public function testEntitySetCapabilities()
    {
        $this->assertCount(87, $this->metadata->getEntitySets());
        $this->assertInstanceOf(Metadata\EntitySet::class, $this->metadata->getEntitySetFor('item'));

        $entitySet = $this->metadata->getEntitySet('subscriptions');
        $this->assertTrue($entitySet->isDeletable());
        $this->assertTrue($entitySet->isInsertable());
        $this->assertTrue($entitySet->isUpdatable());
        $this->assertFalse($entitySet->isSortable());

        $entitySet = $this->metadata->getEntitySetFor('inventoryPostingGroup');
        $this->assertEquals('inventoryPostingGroups', $entitySet->getName());
    }
    
    public function testBoundActionsFor()
    {
        $this->assertCount(21, $this->metadata->getBoundActions());
        $this->assertCount(6, $this->metadata->getBoundActionsFor('salesInvoice'));
    }
}