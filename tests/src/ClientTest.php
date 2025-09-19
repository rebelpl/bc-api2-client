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
    /** @var Client */
    protected $client;
    
    /** @var MockHandler */
    protected $mockResponse;

    /** @var array<int, array{'request': RequestInterface, 'response': ResponseInterface}> */
    protected $historyContainer = [];

    protected function setUp(): void
    {
        $this->mockResponse = new MockHandler();
        $stack = HandlerStack::create($this->mockResponse);

        $this->historyContainer = [];
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

    public function testGetBaseUrl()
    {
        $baseUrl = $this->client->getBaseUrl();
        $this->assertStringStartsWith('https://api.businesscentral.dynamics.com/v2.0', $baseUrl);
        $this->assertStringEndsWith('/api/', $baseUrl);

        $client = new Client('TEST', 'production');
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
        $this->mockResponse->append(new Response(Client::HTTP_OK));
        $request = new Request('GET', 'companies(xxxx)/salesOrders?$top=5');
        $this->client->call($request);

        $lastRequest = $this->getLastRequest();
        $this->assertEquals(
            'https://api.businesscentral.dynamics.com/v2.0/test-env/api/' .
            'foo/bar/v1.5/companies(xxxx)/salesOrders?$top=5',
            (string)$lastRequest->getUri());
    }

    public function testGet(): void
    {
        $this->mockResponse->append(new Response(Client::HTTP_OK));
        $this->client->get('/test');

        $lastRequest = $this->getLastRequest();
        $this->assertEquals(
            'https://api.businesscentral.dynamics.com/v2.0/test-env/api/foo/bar/v1.5/test',
            (string)$lastRequest->getUri());
        $this->assertEquals('GET', $lastRequest->getMethod());
        $this->assertEmpty((string)$lastRequest->getBody());
    }

    public function testPost(): void
    {
        $this->mockResponse->append(new Response(Client::HTTP_CREATED));
        $this->client->post('test(123)', json_encode([
            'name' => 'Test Name',
        ]));

        $lastRequest = $this->getLastRequest();
        $this->assertEquals(
            'https://api.businesscentral.dynamics.com/v2.0/test-env/api/foo/bar/v1.5/test(123)',
            (string)$lastRequest->getUri());
        $this->assertEquals('POST', $lastRequest->getMethod());
        $this->assertStringContainsString('Test Name', (string)$lastRequest->getBody());
        $this->assertEmpty($lastRequest->getHeaderLine(Request::HEADER_ETAG));
    }

    public function testPatch(): void
    {
        $this->mockResponse->append(new Response(Client::HTTP_OK));
        $this->client->patch('test(123)', json_encode([
            'name' => 'New Test',
        ]), 'test-etag');

        $lastRequest = $this->getLastRequest();
        $this->assertEquals(
            'https://api.businesscentral.dynamics.com/v2.0/test-env/api/foo/bar/v1.5/test(123)',
            (string)$lastRequest->getUri());
        $this->assertEquals('PATCH', $lastRequest->getMethod());
        $this->assertNotEmpty((string)$lastRequest->getBody());
        $this->assertEquals('test-etag', $lastRequest->getHeaderLine(Client::HEADER_IFMATCH));
    }

    public function testDelete(): void
    {
        $this->mockResponse->append(new Response(Client::HTTP_NO_CONTENT));
        $this->client->delete('test(123)', 'test-etag');

        $lastRequest = $this->getLastRequest();
        $this->assertEquals(
            'https://api.businesscentral.dynamics.com/v2.0/test-env/api/foo/bar/v1.5/test(123)',
            (string)$lastRequest->getUri());
        $this->assertEquals('DELETE', $lastRequest->getMethod());
        $this->assertEmpty((string)$lastRequest->getBody());
        $this->assertEquals('test-etag', $lastRequest->getHeaderLine(Client::HEADER_IFMATCH));
    }

    public function testFetchMetadata(): void
    {
        $this->mockResponse->append(new Response(200, []));
        $this->client->fetchMetadata();

        $lastRequest = $this->getLastRequest();
        $this->assertEquals(
            'https://api.businesscentral.dynamics.com/v2.0/test-env/api/foo/bar/v1.5/$metadata',
            (string)$lastRequest->getUri());
    }

    public function testGetCompanies(): void
    {
        $this->mockResponse->append(new Response(200, [], file_get_contents('tests/files/companies.json')));
        $companies = $this->client->getCompanies();
        foreach ($companies as $company) {
            $this->assertInstanceOf(Company::class, $company);
            $this->assertContains($company->getId(), [
                'e802e7d1-5408-f011-9afa-6045bdabb318',
                '3ab5c248-e72b-f011-9a4a-7c1e5275406f',
            ]);

            $this->assertIsString($company->getName());
            $this->assertGreaterThan(4, strlen($company->getName()));
            $this->assertGreaterThan(new \DateTime('01.01.2025'), $company->getSystemCreatedAt());
        }

        $lastRequest = $this->getLastRequest();
        $this->assertEquals(
            'https://api.businesscentral.dynamics.com/v2.0/test-env/api/foo/bar/v1.5/companies',
            (string)$lastRequest->getUri());
    }
}