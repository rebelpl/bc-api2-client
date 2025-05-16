<?php
namespace Rebel\Test\BCApi2;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Rebel\BCApi2\Client;
use Rebel\BCApi2\Entity;
use Rebel\BCApi2\Exception;
use Rebel\BCApi2\Repository;
use GuzzleHttp\Middleware;

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
            companyId: 'test-company-id',
            options: [
                'handler' => $stack,
            ]
        );
    }

    public function testEntityClassDoesNotExist()
    {
        $this->expectException(Exception::class);
        new Repository($this->client, 'salesInvoice', 'v2.0', 'NotExistingClassName');
    }

    public function testEntityClassDefault()
    {
        $repository = new Repository($this->client, 'salesInvoices');
        $this->assertEquals(Entity::class, $repository->getEntityClass());
    }

    public function testGetSalesOrders()
    {
        $this->mockResponse->reset();
        $this->mockResponse->append(new Response(200, [], file_get_contents('tests/files/salesOrders.json')));

        $repository = new Repository($this->client, 'salesOrders');
        $result = $repository->findBy([], null, 3, 2);
        $this->assertCount(3, $result);
        $this->assertCount(1, $this->historyContainer);

        $transaction = end($this->historyContainer);
        $this->assertEquals(
            'https://api.businesscentral.dynamics.com/v2.0/test-env/api/v2.0/companies(test-company-id)/salesOrders?%24top=3&%24skip=2',
            (string)$transaction['request']->getUri());

        foreach ($result as $entity) {
            $this->assertInstanceOf(Entity::class, $entity);
            $this->assertNotEmpty($entity->get('number'));
            $this->assertInstanceOf(\DateTime::class, $entity->getAsDate('orderDate'));

            $lastModifiedDateTime = $entity->getAsDateTime('lastModifiedDateTime');
            $this->assertGreaterThan(new \DateTime('2020-12-31'), $lastModifiedDateTime);
        }
    }
}