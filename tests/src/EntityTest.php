<?php
namespace Rebel\Test\BCApi2;

use PHPUnit\Framework\TestCase;
use Rebel\BCApi2\Entity;
use Rebel\BCApi2\Exception;

class EntityTest extends TestCase
{
    /** @var Entity */
    private $customer;

    /** @var Entity[] */
    private $salesOrders = [];

    public function setUp(): void
    {
        $data = json_decode(file_get_contents('tests/files/customer.json'), true);
        $this->customer = new Entity($data);

        $contents = json_decode(file_get_contents('tests/files/salesOrders.json'), true);
        foreach ($contents['value'] as $data) {
            $this->salesOrders[] = new Entity($data, [ 'customer', 'salesOrderLines', 'pdfDocument' ]);
        }
    }

    public function testGetAsDateAndTime(): void
    {
        $this->assertInstanceOf(\DateTime::class, $this->customer->getAsDateTime('lastModifiedDateTime'));
        foreach ($this->salesOrders as $entity) {
            $this->assertInstanceOf(\DateTime::class, $entity->getAsDate('orderDate'));
            $this->assertGreaterThan(new \DateTime('2020-12-31'), $entity->getAsDateTime('lastModifiedDateTime'));
        }
    }

    public function testGetBool(): void
    {
        $this->assertIsBool($this->customer->get('taxLiable'));
    }

    public function testGetETag(): void
    {
        $this->assertEquals('W/"JzE5OzY3MjczMjkxODU0MzQ2NTQ2MTcxOzAwOyc="', $this->customer->getETag());
    }

    public function testGetNotExistingProperty(): void
    {
        $this->expectException(Exception\PropertyDoesNotExistException::class);
        $this->customer->get('notExistingProperty');
    }

    public function testGetSingleNavProperty(): void
    {
        $salesOrder = $this->salesOrders[0];
        $customer = $salesOrder->getAsRelation('customer');
        $this->assertInstanceOf(Entity::class, $customer);
        $this->assertEquals('Jan Ubezpieczenia S.A.', $customer->get('displayName'));
    }

    public function testGetAsStream(): void
    {
        $salesOrder = $this->salesOrders[0];
        $pdfDocument = $salesOrder->getAsRelation('pdfDocument');
        $this->assertInstanceOf(Entity::class, $pdfDocument);
        $this->assertEquals($salesOrder->getPrimaryKey(), $pdfDocument->get('parentId'));
        
        $content = $pdfDocument->get('pdfDocumentContent');
        $this->assertInstanceOf(Entity\DataStream::class, $content);
        $this->assertEquals(
            'https://api.businesscentral.dynamics.com/v2.0/Production/api/v2.0/companies(3ab5c248-e72b-f011-9a4a-7c1e5275406f)/salesOrders(848028e0-0c2c-f011-9a4a-7ced8d0fb545)/pdfDocument/pdfDocumentContent', 
            $content->getUrl());
    }

    public function testGetCollectionNavProperty(): void
    {
        $salesOrder = $this->salesOrders[0];
        $salesOrderLines = $salesOrder->getAsCollection('salesOrderLines');
        $this->assertInstanceOf(Entity\Collection::class, $salesOrderLines);
        $this->assertCount(1, $salesOrderLines);
    }

    public function testSetDateAndTime(): void
    {
        $salesOrder = $this->salesOrders[0];

        $date = new \DateTime('2025-05-16');
        $salesOrder->set('orderDate', $date);
        $this->assertEquals('2025-05-16', $salesOrder->getAsDate('orderDate')->format('Y-m-d'));

        $dateTime = new \DateTime('2025-12-26 01:02');
        $salesOrder->set('lastModifiedDateTime', $dateTime);
        $this->assertEquals('2025-12-26T01:02:00.000Z', $salesOrder->getAsDateTime('lastModifiedDateTime')->format('Y-m-d\TH:i:s.v\Z'));
    }

    public function testToUpdate(): void
    {
        $this->assertEquals([], $this->customer->toUpdate());
        $this->customer->loadData([
            'taxLiable' => 'true',
            'displayName' => 'John Doe',
        ]);

        $this->assertEquals('John Doe', $this->customer->get('displayName'));
        $this->assertEquals([
            'taxLiable' => true,
            'displayName' => 'John Doe',
        ], $this->customer->toUpdate());
    }

    public function testToUpdateWithExpandedProperties(): void
    {
        // Get a sales order with nested entities
        $salesOrder = $this->salesOrders[0];

        // Verify that initially there are no changes
        $this->assertEquals([], $salesOrder->toUpdate());

        // Modify standard property
        $salesOrder->set('orderDate', '2025-05-16');

        // Modify a property in the nested customer entity
        $customer = $salesOrder->getAsRelation('customer');
        $this->assertInstanceOf(Entity::class, $customer);
        $customer->set('displayName', 'Modified Customer Name');

        // Modify a property in one of the nested salesOrderLines entities
        $salesOrderLines = $salesOrder->getAsCollection('salesOrderLines', 'collection');
        $this->assertCount(1, $salesOrderLines);
        $salesOrderLine = $salesOrderLines[0];
        $this->assertInstanceOf(Entity::class, $salesOrderLine);
        $salesOrderLine->set('description', 'Modified Description');

        // Add new salesOrderLine
        $salesOrderLines[] = new Entity([
            'description' => 'New Line Description',
            'quantity' => 10,
        ]);

        // Check that toUpdate returns the nested changes
        $updateData = $salesOrder->toUpdate(true);
        $this->assertArrayHasKey('orderDate', $updateData);
        $this->assertArrayHasKey('customer', $updateData);
        $this->assertArrayHasKey('displayName', $updateData['customer']);
        $this->assertEquals('Modified Customer Name', $updateData['customer']['displayName']);

        $this->assertArrayHasKey('salesOrderLines', $updateData);
        $this->assertCount(2, $updateData['salesOrderLines']);
        $this->assertArrayHasKey('description', $updateData['salesOrderLines'][0]);
        $this->assertEquals('Modified Description', $updateData['salesOrderLines'][0]['description']);
        $this->assertEquals('New Line Description', $updateData['salesOrderLines'][1]['description']);
        $this->assertEquals(10, $updateData['salesOrderLines'][1]['quantity']);

        // Check that toUpdate does not return nested changes with includeExpandedProperties = false
        $updateData = $salesOrder->toUpdate(false);
        $this->assertArrayHasKey('orderDate', $updateData);
        $this->assertArrayNotHasKey('customer', $updateData);
        $this->assertArrayNotHasKey('salesOrderLines', $updateData);
   }

   public function testToUpdateNewInstance(): void
   {
       $salesOrder = new Entity([
           "externalDocumentNumber" => "TEST-001",
           "customerNumber" => "NA0007",
       ]);

       $salesOrderLines = $salesOrder->getAsCollection('salesOrderLines');
       $salesOrderLines->append(new Entity([
           "sequence" => 10000,
           "itemId" => "b3c285a5-f12b-f011-9a4a-7c1e5275406f",
           "quantity" => 10,
       ]));

       $salesOrderLines[] = new Entity([
           "lineType" => "Item",
           "lineObjectNumber" => "1120",
           "quantity" => 20
       ]);

       $updateData = $salesOrder->toUpdate(true);
       $this->assertTrue($salesOrder->isExpandedProperty('salesOrderLines'));
       $this->assertEquals('TEST-001', $updateData['externalDocumentNumber']);
       $this->assertArrayHasKey('salesOrderLines', $updateData);
       $this->assertCount(2, $updateData['salesOrderLines']);
   }
   
   public function testInstantiatedEntity(): void
   {
       $entity = new Entity([
           'id' => 'f3c1c612-fc83-f011-a6f5-000d3a4b6d9d',
           'name' => 'Test Entity',
           Entity::ODATA_ETAG => 'test-etag',
           'lines' => [
               0 => [
                   'id' => '123-001',
                   'quantity' => 1,
                   'item' => 'itemA',
                   Entity::ODATA_ETAG => 'line-etag',
               ]
           ],
           'customer' => [
               'name' => 'John Doe',
           ]
       ], [ 'lines', 'customer' ]);
       $this->expectException(Exception\UsedGetOnExpandedPropertyException::class);
       $entity->get('lines');
   }
   
   public function testNotExpandedCollectionPropertyThrowsException(): void
   {
       $salesOrder = $this->salesOrders[0];
       $this->expectException(Exception\PropertyIsNotExpandedException::class);
       $salesOrder->getAsCollection('salesInvoiceLines');
   }
   
   public function testNotExpandedSinglePropertyThrowsException(): void
   {
       $salesOrder = $this->salesOrders[0];
       $salesOrder->addToClassMap([
           'test' => Entity::class,
       ]);
       
       $this->expectException(Exception\PropertyIsNotExpandedException::class);
       $salesOrder->get('test');
   }
}
