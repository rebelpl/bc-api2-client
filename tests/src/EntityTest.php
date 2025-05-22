<?php
namespace Rebel\Test\BCApi2;

use PHPUnit\Framework\TestCase;
use Rebel\BCApi2\Entity;

class EntityTest extends TestCase
{
    private Entity $customer;

    /** @var Entity[] */
    private array $salesOrders = [];

    public function setUp(): void
    {
        $data = json_decode(file_get_contents('tests/files/customer.json'), true);
        $this->customer = new Entity($data);

        $contents = json_decode(file_get_contents('tests/files/salesOrders.json'), true);
        foreach ($contents['value'] as $data) {
            $this->salesOrders[] = new Entity($data);
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
        $this->assertEquals('W/"JzE5OzY3MjczMjkxODU0MzQ2NTQ2MTcxOzAwOyc="', $this->customer->etag);
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
        $this->customer
            ->set('taxLiable', 'true')
            ->set('displayName', 'John Doe');

        $this->assertEquals('John Doe', $this->customer->get('displayName'));
        $this->assertEquals([
            'taxLiable' => true,
            'displayName' => 'John Doe',
        ], $this->customer->toUpdate());
    }

    public function testDeepInsert(): void
    {
        // Get a sales order with nested entities
        $salesOrder = $this->salesOrders[0];

        // Verify that initially there are no changes
        $this->assertEquals([], $salesOrder->toUpdate());

        // Modify a property in the nested customer entity
        $customer = $salesOrder->get('customer');
        $this->assertInstanceOf(Entity::class, $customer);
        $customer->set('displayName', 'Modified Customer Name');

        // Modify a property in one of the nested salesOrderLines entities
        $salesOrderLines = $salesOrder->get('salesOrderLines');
        $this->assertNotEmpty($salesOrderLines);
        $salesOrderLine = $salesOrderLines[0];
        $this->assertInstanceOf(Entity::class, $salesOrderLine);
        $salesOrderLine->set('description', 'Modified Description');

        // Check that toUpdate returns the nested changes
        $updateData = $salesOrder->toUpdate();
        $this->assertArrayHasKey('customer', $updateData);
        $this->assertArrayHasKey('displayName', $updateData['customer']);
        $this->assertEquals('Modified Customer Name', $updateData['customer']['displayName']);

        $this->assertArrayHasKey('salesOrderLines', $updateData);
        $this->assertCount(1, $updateData['salesOrderLines']);
        $this->assertArrayHasKey('description', $updateData['salesOrderLines'][0]);
        $this->assertEquals('Modified Description', $updateData['salesOrderLines'][0]['description']);
    }
}
