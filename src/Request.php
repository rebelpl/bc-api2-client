<?php
namespace Rebel\BCApi2;

use GuzzleHttp\Psr7;

class Request extends Psr7\Request
{
    const string HEADER_ETAG = 'If-Match';
    public string $id;

    public function __construct(
        string  $method,
        string  $resource,
        ?string $body = null,
        ?string $etag = null)
    {
        parent::__construct($method, $resource, [
            self::HEADER_ETAG => $etag,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ], $body);
        $this->id = $id ?? uniqid();
    }

    public function toArray(): array
    {
        return [
            'method' => $this->getMethod(),
            'url' => (string)$this->getUri(),
            'headers' => $this->getHeaders(),
            'body' => $this->getBody(),
        ];
    }
}