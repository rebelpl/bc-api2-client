<?php
namespace Rebel\Test\BCApi2;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Rebel\BCApi2\Client;
use SimpleXMLElement;

class ClientTest extends TestCase
{
    protected Client $client;
    protected MockHandler $mockResponse;

    /** @var array<int, array{'request': Request, 'response': Response}> */
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
            companyId: 'test-company-id',
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

    public function testBuildUri(): void
    {
        $this->assertEquals(
            'foo/bar/v1.5/companies',
            $this->client->buildUri('companies', 'foo/bar/v1.5', false));
    }

    public function testGet(): void
    {
        $this->mockResponse->reset();
        $this->mockResponse->append(new Response(Client::HTTP_OK));

        $this->client->get('v1.0/test');

        /** @var array{'request': Request, 'response': Response} $transaction */
        $transaction = end($this->historyContainer);
        $this->assertEquals(
            'https://api.businesscentral.dynamics.com/v2.0/test-env/api/v1.0/test',
            (string)$transaction['request']->getUri());
        $this->assertEquals('GET', $transaction['request']->getMethod());
        $this->assertEmpty((string)$transaction['request']->getBody());
    }

    public function testPost(): void
    {
        $this->mockResponse->reset();
        $this->mockResponse->append(new Response(Client::HTTP_CREATED));

        $this->client->post('v1.0/test(123)', json_encode([
            'name' => 'Test',
        ]));

        /** @var array{'request': Request, 'response': Response} $transaction */
        $transaction = end($this->historyContainer);
        $this->assertEquals(
            'https://api.businesscentral.dynamics.com/v2.0/test-env/api/v1.0/test(123)',
            (string)$transaction['request']->getUri());
        $this->assertEquals('POST', $transaction['request']->getMethod());
        $this->assertNotEmpty((string)$transaction['request']->getBody());
    }

    public function testPatch(): void
    {
        $this->mockResponse->reset();
        $this->mockResponse->append(new Response(Client::HTTP_OK));

        $this->client->patch('v1.0/test(123)', json_encode([
            'name' => 'New Test',
        ]), 'test-etag');

        /** @var array{'request': Request, 'response': Response} $transaction */
        $transaction = end($this->historyContainer);
        $this->assertEquals(
            'https://api.businesscentral.dynamics.com/v2.0/test-env/api/v1.0/test(123)',
            (string)$transaction['request']->getUri());
        $this->assertEquals('PATCH', $transaction['request']->getMethod());
        $this->assertNotEmpty((string)$transaction['request']->getBody());
        $this->assertEquals('test-etag', $transaction['request']->getHeaderLine(Client::HEADER_IFMATCH));
    }

    public function testDelete(): void
    {
        $this->mockResponse->reset();
        $this->mockResponse->append(new Response(Client::HTTP_NO_CONTENT));

        $this->client->delete('v1.0/test(123)', 'test-etag');

        /** @var array{'request': Request, 'response': Response} $transaction */
        $transaction = end($this->historyContainer);
        $this->assertEquals(
            'https://api.businesscentral.dynamics.com/v2.0/test-env/api/v1.0/test(123)',
            (string)$transaction['request']->getUri());
        $this->assertEquals('DELETE', $transaction['request']->getMethod());
        $this->assertEmpty((string)$transaction['request']->getBody());
        $this->assertEquals('test-etag', $transaction['request']->getHeaderLine(Client::HEADER_IFMATCH));
    }

    public function testFetchMetadata(): void
    {
        $this->mockResponse->reset();
        $this->mockResponse->append(new Response(200, [], file_get_contents('tests/files/metadata.xml')));

        $contents = $this->client->fetchMetadata();
        $xml = simplexml_load_string($contents);
        $this->assertInstanceOf(SimpleXMLElement::class, $xml);

        $xml->registerXPathNamespace('edm', 'http://docs.oasis-open.org/odata/ns/edm');
        $enumTypes = $xml->xpath('//edm:Schema/edm:EnumType');
        $this->assertCount(34, $enumTypes);
    }

    public function testGetCompanies(): void
    {
        $this->mockResponse->reset();
        $this->mockResponse->append(new Response(200, [], file_get_contents('tests/files/companies.json')));

        $companies = $this->client->getCompanies();
        foreach ($companies as $company) {
            $this->assertContains($company->getId(), [
                'e802e7d1-5408-f011-9afa-6045bdabb318',
                '3ab5c248-e72b-f011-9a4a-7c1e5275406f',
            ]);

            $this->assertIsString($company->getName());
            $this->assertGreaterThan(4, strlen($company->getName()));
            $this->assertGreaterThan(new \DateTime('01.01.2025'), $company->getSystemCreatedAt());
        }
    }
}