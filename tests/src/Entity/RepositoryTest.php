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
    protected Client $client;
    protected MockHandler $mockResponse;

    /** @var array<int, array{'request': Request, 'response': Response}> */
    protected array $historyContainer = [];

    protected function setUp(): void
    {
        $this->mockResponse = new MockHandler();
        $stack = HandlerStack::create($this->mockResponse);

        $history = Middleware::history($this->historyContainer);
        $stack->push($history);

        $this->client = new Client(
            accessToken: 'test-token',
            environment: 'test-env',
            apiRoute:    'foo/bar/v1.5',
            companyId:   'test-company-id',
            options: [
                'handler' => $stack,
            ]
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
        $this->mockResponse->append(new Response(200, [], file_get_contents('tests/files/salesOrders.json')));

        $repository = new Repository($this->client, 'salesOrders');
        $result = $repository->findBy([], size: 3, skip: 2, expanded: [ 'salesOrderLines', 'customer' ]);
        $this->assertCount(3, $result);
        $this->assertCount(1, $this->historyContainer);

        $lastRequest = $this->getLastRequest();
        $this->assertEquals('https://api.businesscentral.dynamics.com/v2.0/test-env/api/'.
                'foo/bar/v1.5/companies(test-company-id)/salesOrders'.
                '?%24top=3'.
                '&%24skip=2'.
                '&%24expand=salesOrderLines%2Ccustomer',
            (string)$lastRequest->getUri());

        foreach ($result as $salesOrder) {
            $this->assertInstanceOf(Entity::class, $salesOrder);
            $this->assertNotEmpty($salesOrder->get('number'));
            $this->assertInstanceOf(\DateTime::class, $salesOrder->getAsDate('orderDate'));

            $lastModifiedDateTime = $salesOrder->getAsDateTime('lastModifiedDateTime');
            $this->assertGreaterThan(new \DateTime('2020-12-31'), $lastModifiedDateTime);

            $this->assertGreaterThan(0, count($salesOrder->get('salesOrderLines')));
            foreach ($salesOrder->get('salesOrderLines') as $salesOrderLine) {
                $this->assertInstanceOf(Entity::class, $salesOrderLine);
                $this->assertNotEmpty($salesOrderLine->get('description'));
                $this->assertGreaterThan(0, $salesOrderLine->get('quantity'));
            }

            $customer = $salesOrder->get('customer');
            $this->assertInstanceOf(Entity::class, $customer);
            $this->assertNotEmpty($customer->get('displayName'));
        }
    }
}