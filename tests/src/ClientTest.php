<?php
namespace Rebel\Test\BCApi2;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Rebel\BCApi2\Client;
use Rebel\BCApi2\Entity\Company;
use Rebel\BCApi2\Request;

class ClientTest extends TestCase
{
    protected Client $client;
    protected MockHandler $mockResponse;

    /** @var array<int, array{'request': RequestInterface, 'response': ResponseInterface}> */
    protected array $historyContainer = [];

    protected function setUp(): void
    {
        $this->mockResponse = new MockHandler();
        $stack = HandlerStack::create($this->mockResponse);

        $this->historyContainer = [];
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

    public function testGetBaseUrl()
    {
        $baseUrl = $this->client->getBaseUrl();
        $this->assertStringStartsWith('https://api.businesscentral.dynamics.com/v2.0', $baseUrl);
        $this->assertStringEndsWith('/api/', $baseUrl);

        $client = new Client(accessToken: 'TEST', environment: 'production');
        $this->assertEquals(
            'https://api.businesscentral.dynamics.com/v2.0/production/api/',
            $client->getBaseUrl());
    }

    public function testGetCompanyUrl()
    {
        $this->assertEquals('companies(test-company-id)', $this->client->getCompanyPath());
    }

    public function testBuildUri(): void
    {
        $this->assertEquals(
            'https://api.businesscentral.dynamics.com/v2.0/test-env/api/foo/bar/v1.5/companies',
            (string)$this->client->buildUri('companies'));
    }

    public function testCall()
    {
        $this->mockResponse->reset();
        $this->mockResponse->append(new Response(Client::HTTP_OK));

        $request = new Request('GET', 'companies(xxxx)/salesOrders?$top=5');
        $this->client->call($request);

        /** @var array{'request': RequestInterface, 'response': Response} $transaction */
        $transaction = end($this->historyContainer);
        $this->assertEquals(
            'https://api.businesscentral.dynamics.com/v2.0/test-env/api/' .
            'foo/bar/v1.5/companies(xxxx)/salesOrders?$top=5',
            (string)$transaction['request']->getUri());
    }

    public function testGet(): void
    {
        $this->mockResponse->reset();
        $this->mockResponse->append(new Response(Client::HTTP_OK));

        $this->client->get('/test');

        /** @var array{'request': Request, 'response': Response} $transaction */
        $transaction = end($this->historyContainer);
        $this->assertEquals(
            'https://api.businesscentral.dynamics.com/v2.0/test-env/api/foo/bar/v1.5/test',
            (string)$transaction['request']->getUri());
        $this->assertEquals('GET', $transaction['request']->getMethod());
        $this->assertEmpty((string)$transaction['request']->getBody());
    }

    public function testPost(): void
    {
        $this->mockResponse->reset();
        $this->mockResponse->append(new Response(Client::HTTP_CREATED));

        $this->client->post('test(123)', json_encode([
            'name' => 'Test Name',
        ]));

        /** @var array{'request': RequestInterface, 'response': Response} $transaction */
        $transaction = end($this->historyContainer);
        $this->assertEquals(
            'https://api.businesscentral.dynamics.com/v2.0/test-env/api/foo/bar/v1.5/test(123)',
            (string)$transaction['request']->getUri());
        $this->assertEquals('POST', $transaction['request']->getMethod());
        $this->assertStringContainsString('Test Name', (string)$transaction['request']->getBody());
        $this->assertEmpty($transaction['request']->getHeaderLine(Request::HEADER_ETAG));
    }

    public function testPatch(): void
    {
        $this->mockResponse->reset();
        $this->mockResponse->append(new Response(Client::HTTP_OK));

        $this->client->patch('test(123)', json_encode([
            'name' => 'New Test',
        ]), 'test-etag');

        /** @var array{'request': RequestInterface, 'response': Response} $transaction */
        $transaction = end($this->historyContainer);
        $this->assertEquals(
            'https://api.businesscentral.dynamics.com/v2.0/test-env/api/foo/bar/v1.5/test(123)',
            (string)$transaction['request']->getUri());
        $this->assertEquals('PATCH', $transaction['request']->getMethod());
        $this->assertNotEmpty((string)$transaction['request']->getBody());
        $this->assertEquals('test-etag', $transaction['request']->getHeaderLine(Client::HEADER_IFMATCH));
    }

    public function testDelete(): void
    {
        $this->mockResponse->reset();
        $this->mockResponse->append(new Response(Client::HTTP_NO_CONTENT));

        $this->client->delete('test(123)', 'test-etag');

        /** @var array{'request': RequestInterface, 'response': Response} $transaction */
        $transaction = end($this->historyContainer);
        $this->assertEquals(
            'https://api.businesscentral.dynamics.com/v2.0/test-env/api/foo/bar/v1.5/test(123)',
            (string)$transaction['request']->getUri());
        $this->assertEquals('DELETE', $transaction['request']->getMethod());
        $this->assertEmpty((string)$transaction['request']->getBody());
        $this->assertEquals('test-etag', $transaction['request']->getHeaderLine(Client::HEADER_IFMATCH));
    }

    public function testFetchMetadata(): void
    {
        $this->mockResponse->reset();
        $this->mockResponse->append(new Response(200, []));

        $this->client->fetchMetadata();

        /** @var array{'request': RequestInterface, 'response': Response} $transaction */
        $transaction = end($this->historyContainer);
        $this->assertEquals(
            'https://api.businesscentral.dynamics.com/v2.0/test-env/api/foo/bar/v1.5/$metadata',
            (string)$transaction['request']->getUri());
    }

    public function testGetCompanies(): void
    {
        $this->mockResponse->reset();
        $this->mockResponse->append(new Response(200, [], file_get_contents('tests/files/companies.json')));

        $companies = $this->client->getCompanies();
        foreach ($companies as $company) {
            $this->assertInstanceOf(Company::class, $company);
            $this->assertContains($company->id, [
                'e802e7d1-5408-f011-9afa-6045bdabb318',
                '3ab5c248-e72b-f011-9a4a-7c1e5275406f',
            ]);

            $this->assertIsString($company->name);
            $this->assertGreaterThan(4, strlen($company->name));
            $this->assertGreaterThan(new \DateTime('01.01.2025'), $company->systemCreatedAt);
        }

        /** @var array{'request': RequestInterface, 'response': Response} $transaction */
        $transaction = end($this->historyContainer);
        $this->assertEquals(
            'https://api.businesscentral.dynamics.com/v2.0/test-env/api/foo/bar/v1.5/companies',
            (string)$transaction['request']->getUri());
    }
}