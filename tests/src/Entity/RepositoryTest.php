<?php
namespace Rebel\Test\BCApi2\Entity;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Rebel\BCApi2\Client;
use Rebel\BCApi2\Entity;
use Rebel\BCApi2\Entity\Repository;
use Rebel\BCApi2\Exception;

class RepositoryTest extends TestCase
{
    /** @var Client */
    protected $client;
    
    /** @var MockHandler */
    protected $mockResponse;

    /** @var array<int, array{'request': Request, 'response': Response}> */
    protected $historyContainer = [];

    protected function setUp(): void
    {
        $this->mockResponse = new MockHandler();
        $stack = HandlerStack::create($this->mockResponse);

        $history = Middleware::history($this->historyContainer);
        $stack->push($history);

        $this->client = new Client(
            'test-token',
            'test-env',
            'foo/bar/v1.5',
            'test-company-id',
            [ 'handler' => $stack, ]
        );
    }

    private function getLastRequest(): RequestInterface
    {
        $transaction = end($this->historyContainer);
        return $transaction['request'];
    }

    public function testEntityClassDoesNotExist()
    {
        $this->expectException(Exception::class);
        new Repository($this->client, 'salesInvoice', 'NotExistingClassName');
    }

    public function testEntityClassDefault()
    {
        $repository = new Repository($this->client, 'salesInvoices');
        $this->assertEquals(Entity::class, $repository->getEntityClass());
    }

    public function testGetBaseUrl()
    {
        $repository = new Repository($this->client, 'salesInvoices');
        $this->assertEquals('companies(test-company-id)/salesInvoices', $repository->getBaseUrl());
    }

    public function testGetSalesOrders()
    {
        $this->mockResponse->append(new Response(200, [],
            file_get_contents('tests/files/salesOrders.json')));

        $repository = new Repository($this->client, 'salesOrders');
        $result = $repository->findBy([], null, 3, 2, [ 'salesOrderLines', 'customer' ]);
        $this->assertCount(3, $result);
        $this->assertCount(1, $this->historyContainer);

        $lastRequest = $this->getLastRequest();
        $this->assertEquals('/v2.0/test-env/api/' .
            'foo/bar/v1.5/companies(test-company-id)/salesOrders',
            $lastRequest->getUri()->getPath());

        $this->assertEquals('$top=3' .
            '&$skip=2' .
            '&$expand=salesOrderLines,customer',
            urldecode($lastRequest->getUri()->getQuery()));

        foreach ($result as $salesOrder) {
            $this->assertInstanceOf(Entity::class, $salesOrder);
            $this->assertNotEmpty($salesOrder->get('number'));
            $this->assertInstanceOf(\DateTime::class, $salesOrder->get('orderDate', 'date'));

            $lastModifiedDateTime = $salesOrder->get('lastModifiedDateTime', 'datetime');
            $this->assertGreaterThan(new \DateTime('2020-12-31'), $lastModifiedDateTime);
            $this->assertEquals(sprintf('companies(test-company-id)/salesOrders(%s)', $salesOrder->get('id')), (string)$salesOrder->getContext());

            $this->assertGreaterThan(0, count($salesOrder->get('salesOrderLines', 'collection')));
            foreach ($salesOrder->get('salesOrderLines', 'collection') as $salesOrderLine) {
                $this->assertInstanceOf(Entity::class, $salesOrderLine);
                $this->assertNotEmpty($salesOrderLine->get('description'));
                $this->assertGreaterThan(0, $salesOrderLine->get('quantity'));
                $this->assertEquals(sprintf('companies(test-company-id)/salesOrders(%s)/salesOrderLines(%s)', $salesOrder->get('id'), $salesOrderLine->get('id')), (string)$salesOrderLine->getContext());
            }

            $customer = $salesOrder->get('customer');
            $this->assertInstanceOf(Entity::class, $customer);
            $this->assertNotEmpty($customer->get('displayName'));
            $this->assertEquals(sprintf('companies(test-company-id)/salesOrders(%s)/customer(%s)', $salesOrder->get('id'), $customer->get('id')), (string)$customer->getContext());
        }
    }

    public function testCustomersWithFilteredShipToAddresses(): void
    {
        $this->mockResponse->append(new Response(200, [],
            file_get_contents('tests/files/customers.json')));

        $repository = new Repository($this->client, 'customers');
        $result = $repository->findBy([ 'gln' => '12345' ], null, null, null, [
            'shipToAddresses' => [ 'code' => '196238' ],
        ]);
        
        $this->assertCount(1, $result);
        $this->assertCount(1, $this->historyContainer);

        $lastRequest = $this->getLastRequest();
        $this->assertEquals('$filter=gln eq \'12345\'' .
            '&$expand=shipToAddresses($filter=code eq \'196238\')',
            urldecode($lastRequest->getUri()->getQuery()));

        $customer = $result[0];
        $this->assertInstanceOf(Entity::class, $customer);
        
        $addresses = $customer->get('shipToAddresses', 'collection');
        $this->assertCount(1, $addresses);
        $this->assertInstanceOf(Entity::class, $addresses[0]);
        $this->assertEquals('196238', $addresses[0]->get('code'));
    }
    
    public function testFindOneBy()
    {
        $this->mockResponse->append(new Response(200, [],
            file_get_contents('tests/files/customers.json')));

        $repository = new Repository($this->client, 'customers');
        $result = $repository->findOneBy([]);
        $this->assertInstanceOf(Entity::class, $result);

        $lastRequest = $this->getLastRequest();
        $this->assertEquals('$top=1',
            urldecode($lastRequest->getUri()->getQuery()));
    }
    
    public function testFindByDate()
    {
        $this->mockResponse->append(new Response(200, [],
            file_get_contents('tests/files/salesOrders.json')));
        
        $repository = new Repository($this->client, 'salesOrders');
        $repository->findBy([ 'documentDate' => new \DateTime('2020-12-31') ]);
        $lastRequest = $this->getLastRequest();
        $this->assertEquals('$filter=documentDate eq 2020-12-31T00:00:00.000Z',
            urldecode($lastRequest->getUri()->getQuery()));
    }
}