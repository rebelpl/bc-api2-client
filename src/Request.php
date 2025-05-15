<?php
namespace Rebel\BCApi2;

use GuzzleHttp\Psr7;

class Request
{
    protected ?string $includePart = null;
    protected array $queryOptions = [];

    public function __construct(
        private readonly string $resourceUrl,
        private readonly ?string $primaryKey = null)
    {

    }

    public function buildUri(): string
    {
        $uri = $this->resourceUrl;
        if ($this->primaryKey) {
            $uri .= sprintf('(%s)', $this->primaryKey);
        }

        if ($this->includePart) {
            $uri .= sprintf('/%s', $this->includePart);
        }

        if (!empty($this->queryOptions)) {
            $uri .= '?' . Psr7\Query::build(
                array_filter($this->queryOptions, function($value) {
                    return !is_null($value);
                }));
        }

        return $uri;
    }

    public function __toString(): string
    {
        return $this->buildUri();
    }
}