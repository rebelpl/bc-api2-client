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
        $this->customer = (new Entity())->loadData($data);

        $contents = json_decode(file_get_contents('tests/files/salesOrders.json'), true);
        foreach ($contents['value'] as $data) {
            $this->salesOrders[] = (new Entity())->loadData($data);
        }
    }

    public function testGetAsDateAndTime(): void
    {
        $this->assertInstanceOf(\DateTime::class, $this->customer->get('lastModifiedDateTime', 'datetime'));
        foreach ($this->salesOrders as $entity) {
            $this->assertInstanceOf(\DateTime::class, $entity->get('orderDate', 'date'));
            $this->assertGreaterThan(new \DateTime('2020-12-31'), $entity->get('lastModifiedDateTime', 'datetime'));
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
        $customer = $salesOrder->get('customer');
        $this->assertInstanceOf(Entity::class, $customer);
        $this->assertEquals('Jan Ubezpieczenia S.A.', $customer->get('displayName'));
    }

    public function testGetAsStream(): void
    {
        $salesOrder = $this->salesOrders[0];
        $pdfDocument = $salesOrder->get('pdfDocument');
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
        $salesOrderLines = $salesOrder->get('salesOrderLines', 'collection');
        $this->assertInstanceOf(Entity\Collection::class, $salesOrderLines);
        $this->assertCount(1, $salesOrderLines);
    }

    public function testSetDateAndTime(): void
    {
        $salesOrder = $this->salesOrders[0];

        $date = new \DateTime('2025-05-16');
        $salesOrder->set('orderDate', $date);
        $this->assertEquals('2025-05-16', $salesOrder->get('orderDate', 'date')->format('Y-m-d'));

        $dateTime = new \DateTime('2025-12-26 01:02');
        $salesOrder->set('lastModifiedDateTime', $dateTime);
        $this->assertEquals('2025-12-26T01:02:00.000Z', $salesOrder->get('lastModifiedDateTime', 'datetime')->format('Y-m-d\TH:i:s.v\Z'));
    }

    public function testToUpdateNewEntity(): void
    {
        $entity = new Entity();
        $this->assertEquals([], $entity->toUpdate());
        $entity->set([
            'blocked' => false,
            'name' => 'John Doe',
            'discountPercent' => 0,
            'creditLimit' => 25000.99,
        ]);

        $this->assertEquals([
            'blocked' => false,
            'name' => 'John Doe',
            'discountPercent' => 0,
            'creditLimit' => 25000.99,
        ], $entity->toUpdate());
    }

    public function testToUpdateLoadedEntity(): void
    {
        $this->assertEquals([], $this->customer->toUpdate());
        $this->customer->set([
            'phoneNumber' => '1234567890',
            'taxLiable' => true,
            'email' => null,
            'creditLimit' => 0,
        ]);

        $this->assertEquals([
            'phoneNumber' => '1234567890',
            'taxLiable' => true,
            'email' => null,
            'creditLimit' => 0,
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
        $customer = $salesOrder->get('customer');
        $this->assertInstanceOf(Entity::class, $customer);
        $customer->set('displayName', 'Modified Customer Name');

        // Modify a property in one of the nested salesOrderLines entities
        $salesOrderLines = $salesOrder->get('salesOrderLines', 'collection');
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
        $updateData = $salesOrder->toUpdate();
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
   }

   public function testToUpdateNewExpandedEntity(): void
   {
       $salesOrder = new Entity([
           "externalDocumentNumber" => "TEST-001",
           "customerNumber" => "NA0007",
       ]);

       $salesOrderLines = $salesOrder->get('salesOrderLines', 'collection');
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

       $updateData = $salesOrder->toUpdate();
       $this->assertTrue($salesOrder->isExpandedProperty('salesOrderLines'));
       $this->assertEquals('TEST-001', $updateData['externalDocumentNumber']);
       $this->assertArrayHasKey('salesOrderLines', $updateData);
       $this->assertCount(2, $updateData['salesOrderLines']);
   }
   
   public function testLoadData(): void
   {
       $entity = (new Entity())->loadData([
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
       ]);
       
       $this->assertInstanceOf(Entity::class, $entity->get('customer'));
       $this->assertInstanceOf(Entity\Collection::class, $entity->get('lines'));
   }
   
   public function testNotExpandedCollectionPropertyThrowsException(): void
   {
       $salesOrder = $this->salesOrders[0];
       $this->expectException(Exception\PropertyIsNotExpandedException::class);
       $salesOrder->get('salesInvoiceLines', 'collection');
   }

    public function testIsset(): void
    {
        $salesOrder = $this->salesOrders[0];
        $this->assertTrue($salesOrder->isset('number'));
        $this->assertFalse($salesOrder->isset('fooBar'));
    }
    
    public function testLoadDataPartially(): void
    {
        $data = json_decode(file_get_contents('tests/files/shipToAddresses.json'), true);
        $this->assertNotEmpty($data['value']);
        $this->assertIsArray($data['value']);
        
        $this->customer->loadData([
            'shipToAddresses' => $data['value']
        ]);
        
        $this->assertInstanceOf(Entity\Collection::class, $this->customer->get('shipToAddresses'));
    }
}