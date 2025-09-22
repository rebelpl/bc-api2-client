<?php
namespace Rebel\BCApi2\Entity;

use Rebel\BCApi2\Client;
use Rebel\BCApi2\Exception;

readonly class DataStream
{
    public function __construct(
        private string $url)
    {
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function downloadWith(Client $client): string
    {
        $response = $client->get($this->url);
        return $response->getBody()->getContents();
    }

    public function uploadWith(Client $client, string $data, ?string $etag = null): void
    {
        $response = $client->patch($this->url, $data, $etag);
        if ($response->getStatusCode() !== Client::HTTP_NO_CONTENT) {
            throw new Exception\InvalidResponseException($response);
        }
    }
}