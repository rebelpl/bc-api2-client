<?php
namespace Rebel\BCApi2;

use GuzzleHttp;
use GuzzleHttp\Psr7;
use Psr\Http\Client\ClientInterface;
use Rebel\BCApi2\Entity\ApiRoute;
use Rebel\BCApi2\Entity\Company;
use Rebel\BCApi2\Entity\Repository;

class Client
{
    const  BASE_URL = 'https://api.businesscentral.dynamics.com/v2.0';

    const
        HTTP_OK = 200,
        HTTP_CREATED = 201,
        HTTP_NO_CONTENT = 204,
        HTTP_NOT_FOUND = 404;
    
    const  DEFAULT_API_ROUTE = 'v2.0';

    const  HEADER_IFMATCH = 'If-Match';
    
    /** @var ClientInterface  */
    protected $client;
    
    /** @var string string */
    private $baseUrl;
    private $apiRoute;

    protected $defaultHeaders = [];
    private $accessToken;
    private $companyId;

    /**
     * Available $options:
     * - baseUrl: API endpoint, defaults to https://api.businesscentral.dynamics.com/v2.0
     * - httpClient: instance of Psr\Http\Client\ClientInterface
     */
    public function __construct(
        string  $accessToken,
        string  $environment,
        ?string $apiRoute = null,
        ?string $companyId = null,
        array $options = [])
    {
        $this->companyId = $companyId;
        $this->accessToken = $accessToken;
        $this->client = $options['httpClient'] ?? new GuzzleHttp\Client($options);
        $this->baseUrl = rtrim($options['baseUrl'] ?? self::BASE_URL, '/')
            . "/$environment/api/";
        $this->apiRoute = trim($apiRoute ?: self::DEFAULT_API_ROUTE, '/');

        $this->defaultHeaders = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    public function getHttpClient(): ClientInterface
    {
        return $this->client;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function getCompanyPath(): string
    {
        if (empty($this->companyId)) {
            throw new Exception('You cannot call company resources without valid companyId. Run getCompanies() to obtain a list of companies.');
        }

        return "/companies($this->companyId)";
    }

    public function buildUri(string $uri): Psr7\Uri
    {
        if (strpos($uri, $this->baseUrl) === 0) {
            return new Psr7\Uri($uri);
        }
        
        return new Psr7\Uri($this->baseUrl . $this->apiRoute . '/' . ltrim($uri, '/'));
    }

    public function get(string $uri): ?Psr7\Response
    {
        return $this->run('GET', $uri);
    }

    public function post(string $uri, string $body): ?Psr7\Response
    {
        return $this->run('POST', $uri, $body);
    }

    public function patch(string $uri, string $body, string $etag): ?Psr7\Response
    {
        return $this->run('PATCH', $uri, $body, $etag);
    }

    public function delete(string $uri, string $etag): ?Psr7\Response
    {
        return $this->run('DELETE', $uri, null, $etag);
    }

    public function run(string $method, string $uri, ?string $body = null, ?string $etag = null): ?Psr7\Response
    {
        $request = new Request($method, $uri, $body, $etag);
        return $this->call($request);
    }

    public function call(Psr7\Request $request): ?Psr7\Response
    {
        # echo $request->getUri() . "\n";
        $request = $request
            ->withUri($this->buildUri($request->getUri()))
            ->withHeader('Authorization', 'Bearer ' . $this->accessToken);

        return $this->client->sendRequest($request);
    }

    /**
     * @return Entity\Company[]
     * @throws Exception
     */
    public function getCompanies(): array
    {
        return (new Repository($this,
            'companies',
            Company::class,
            false))
            ->findAll();
    }

    /**
     * @return Entity\ApiRoute[]
     * @throws Exception
     */
    public function getApiRoutes(): array
    {
        return (new Repository($this,
            'apicategoryroutes',
            ApiRoute::class,
            false))
            ->findAll();
    }

    /**
     * @throws Exception
     */
    public function getMetadata(): Metadata
    {
        $metadata = $this->fetchMetadata();
        return Metadata\Factory::fromString($metadata);
    }

    /**
     * @throws Exception
     */
    public function fetchMetadata(): string
    {
        $response = $this->get('$metadata');
        if ($response->getStatusCode() !== self::HTTP_OK) {
            throw new Exception(
                $response->getBody(),
                $response->getStatusCode());
        }

        return $response->getBody()->getContents();
    }
}