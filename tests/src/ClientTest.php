<?php
namespace Rebel\Test\BCApi2;

use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use Rebel\BCApi2\ApiPath;
use Rebel\BCApi2\Client;
use GuzzleHttp;
use Psr\Http\Message\RequestInterface;

class ClientTest extends TestCase
{
    private Client $client;
    private bool $accessTokenAvailable = false;

    protected function setUp(): void
    {
//        $logMiddleware = GuzzleHttp\Middleware::mapRequest(function (RequestInterface $request) {
//            // Log the URL
//            echo "Request URL: " . $request->getUri() . "\n";
//
//            // Log the headers
//            echo "Request Headers:\n";
//            foreach ($request->getHeaders() as $name => $values) {
//                echo "$name: " . implode(", ", $values) . "\n";
//            }
//
//            return $request;
//        });
//
//        // Create a handler stack and push the middleware
//        $stack = GuzzleHttp\HandlerStack::create();
//        $stack->push($logMiddleware);

        $config = include 'tests/config.php';
        $this->accessTokenAvailable = !empty($config['access_token']);
        $this->client = new Client(accessToken: $config['access_token'], environment: $config['environment'], apiPath: $config['api_path'], companyId: $config['company_id'], options: [
//            'handler' => $stack,
        ]);
    }

    public function testGetBaseUrl()
    {
        $baseUrl = $this->client->getBaseUrl();
        $this->assertStringStartsWith('https://api.businesscentral.dynamics.com/v2.0', $baseUrl);
        $this->assertStringEndsWith('/', $baseUrl);
        $this->assertStringContainsString('/api/', $baseUrl);

        $apiGroup = new ApiPath(apiPublisher: 'mycompany', apiGroup: 'finance', apiVersion: 'v3.1');
        $client = new Client(accessToken: 'TEST', environment: 'production', apiPath: $apiGroup);
        $this->assertEquals(
            'https://api.businesscentral.dynamics.com/v2.0/production/api/mycompany/finance/v3.1/',
            $client->getBaseUrl());
    }

    public function testGetCompanyUrl()
    {
        $this->assertEquals(
            'companies(mycompany)',
            $this->client->getCompanyUrl('mycompany'));
    }

    public function testFetchMetadata()
    {
        $this->checkAccessToken();

        $contents = $this->client->fetchMetadata();
        $xml = simplexml_load_string($contents);
        $this->assertInstanceOf(\SimpleXMLElement::class, $xml);

        $xml->registerXPathNamespace('edm', 'http://docs.oasis-open.org/odata/ns/edm');
        $enumTypes = $xml->xpath('//edm:Schema/edm:EnumType');
        $this->assertCount(34, $enumTypes);
    }

    public function testGetCompanies()
    {
        $this->checkAccessToken();

        $companies = $this->client->getCompanies();
        foreach ($companies as $company) {
            $this->assertIsString($company->id());
            $this->assertIsString($company->getName());
            $this->assertIsString($company->getDisplayName());
            $this->assertInstanceOf(Carbon::class, $company->getSystemCreatedAt());
        }
    }

    private function checkAccessToken(): void
    {
        if (!$this->accessTokenAvailable) {
            $this->markTestSkipped('The access token is not set up, use export BCAPI2_ACCESS_TOKEN="yoursecrettokenhere" to proceed with the connection tests.');
        }
    }
}