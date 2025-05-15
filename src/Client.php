<?php
namespace Rebel\BCApi2;

use GuzzleHttp;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7;

class Client
{
    const
        HTTP_OK = 200,
        HTTP_CREATED = 201,
        HTTP_NO_CONTENT = 204,
        HTTP_NOT_FOUND = 404;

    const HEADER_IFMATCH = 'If-Match';
    const BASE_URL = 'https://api.businesscentral.dynamics.com/v2.0';

    protected GuzzleHttp\Client $client;

    public function __construct(
        private readonly string  $accessToken,
        private readonly string  $environment,
        private readonly string  $apiPath = '/v2.0',
        private readonly ?string $companyId = null,
        array $options = [])
    {
        $this->initHttpClient($options);
    }

    private function initHttpClient(array $options): void
    {
        $this->client = new GuzzleHttp\Client(array_merge([
            'base_uri' => $this->getBaseUrl(),
            'headers' => [
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]
        ], $options));
    }

    public function getHttpClient(): GuzzleHttp\Client
    {
        return $this->client;
    }

    public function getBaseUrl(): string
    {
        return
            self::BASE_URL
            . '/' . $this->environment
            . '/api'
            . $this->apiPath
            . '/';
    }

    public function getCompanyUrl(string $companyId): string
    {
        return "companies({$companyId})";
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
        $options = [];
        if (!empty($body)) {
            $options['body'] = $body;
        }

        if (!empty($etag)) {
            $options['headers'][ self::HEADER_IFMATCH ] = $etag;
        }

        if ($this->companyId) {
            $uri = $this->getCompanyUrl($this->companyId) . "/{$uri}";
        }

        return $this->getResponse($method, $uri, $options);
    }

    protected function getResponse(string $method, string $uri, array $options = []): ?Psr7\Response
    {
        try {
            $response = $this->client->request($method, $uri, $options);
        }
        catch (ClientException $e) {
            return $e->getResponse();

        } catch (GuzzleException $e) {
            return null;

        }

        return $response;
    }

    /**
     * @return Company[]
     * @throws Exception
     */
    public function getCompanies(): array
    {
        $response = $this->getResponse('GET', 'companies');
        if ($response->getStatusCode() !== self::HTTP_OK) {
            throw new Exception(
                $response->getBody(),
                $response->getStatusCode());
        }

        $entities = [];
        $data = json_decode($response->getBody(), true);
        foreach ($data['value'] as $result) {
            $entities[] = new Company($result);
        }

        return $entities;
    }

    /**
     * @throws Exception
     */
    public function fetchMetadata(): string
    {
        $response = $this->getResponse('GET', '$metadata');
        if ($response->getStatusCode() !== self::HTTP_OK) {
            throw new Exception(
                $response->getBody(),
                $response->getStatusCode());
        }

        return $response->getBody()->getContents();
    }

    /**
     * @throws Exception
     */
    public function getMetadata(): Metadata
    {
        $metadata = $this->fetchMetadata();
        return Metadata\Factory::fromString($metadata);
    }
}