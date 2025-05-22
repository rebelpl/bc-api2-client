<?php
namespace Rebel\BCApi2\Request;

use Rebel\BCApi2\Request;

class Batch
{
    /**
     * @param Request[] $requests
     */
    public function __construct(
        private array $requests = [])
    {
    }

    public function add(Request $request): self
    {
        $this->requests[] = $request;
        return $this;
    }

    public function toArray(): array
    {
        return array_map(function (Request $request, mixed $key) {
            return [
                'method' => $request->getMethod(),
                'url' => (string)$request->getUri(),
                'headers' => $request->getHeaders(),
                'body' => $request->getBody(),
                'id' => (string)$key,
            ];
        }, $this->requests, array_keys($this->requests));
    }

    public function toJson(): string
    {
        return json_encode([ 'requests' => $this->toArray()], JSON_PRETTY_PRINT);
    }

    public function getRequest(): Request
    {
        return new Request('POST', '$batch',
            body: $this->toJson());
    }
}