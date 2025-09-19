<?php
namespace Rebel\BCApi2;

use GuzzleHttp\Psr7;

class Request extends Psr7\Request
{
    const  HEADER_ETAG = 'If-Match';
    public $id;

    public function __construct(
        string  $method,
        string  $resource,
        ?string $body = null,
        ?string $etag = null)
    {
        parent::__construct($method, $resource, array_filter([
            self::HEADER_ETAG => $etag,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ]), $body);
        $this->id = $id ?? uniqid();
    }

    public function getHeaderLines(): array
    {
        return array_map(function ($value) {
            return implode(', ', $value);
        }, $this->getHeaders());
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