<?php
namespace Rebel\BCApi2\Request;

use Rebel\BCApi2\Exception;
use Rebel\BCApi2\Request;

class Batch
{
    /**
     * @param array<string, Request> $requests
     */
    public function __construct(
        private array $requests = [])
    {
    }

    public function add(string $key, Request $request): self
    {
        if (isset($this->requests[ $key ])) {
            throw new Exception("Request '$key' already exists in the batch.");
        }

        $this->requests[ $key ] = $request;
        return $this;
    }

    public function empty(): bool
    {
        return empty($this->requests);
    }

    public function count(): int
    {
        return count($this->requests);
    }

    public function toArray(): array
    {
        return array_map(function (Request $request, string $key) {
            return array_filter([
                'method' => $request->getMethod(),
                'url' => (string)$request->getUri(),
                'headers' => $request->getHeaderLines(),
                'body' => json_decode($request->getBody()->getContents()),
                'id' => (string)$key,
            ], fn($value) => !is_null($value));
        }, $this->requests, array_keys($this->requests));
    }

    public function toJson(): string
    {
        return json_encode([ 'requests' => $this->toArray() ], JSON_PRETTY_PRINT);
    }

    public function getRequest(): Request
    {
        return new Request('POST', '$batch',
            body: $this->toJson());
    }
}