<?php
namespace Rebel\BCApi2\Request;

use GuzzleHttp\Psr7;

class UriBuilder
{
    private readonly string $resourceUrl;
    protected ?string $includePart = null;
    protected array $queryOptions = [];

    public function __construct(
        string $resourceUrl,
        private ?string $primaryKey = null)
    {
        $this->resourceUrl = trim($resourceUrl, '/');
    }

    public function get($primaryKey): self
    {
        $this->primaryKey = $primaryKey;
        return $this;
    }

    public function select($properties): self
    {
        $this->queryOptions['$select'] = is_array($properties)
            ? join(',', $properties)
            : (string)$properties;
        return $this;
    }

    public function expand($properties): self
    {
        if (!empty($properties)) {
            $this->queryOptions['$expand'] = is_array($properties)
                ? join(',', $properties)
                : (string)$properties;
        }

        return $this;
    }

    public function top(?int $top): self
    {
        $this->queryOptions['$top'] = $top;
        return $this;
    }

    public function where(array $criteria): self
    {
        $map = array_map(function ($key, $value) {
            if ($value instanceof Expression) {
                return $value;
            }

            if (is_array($value)) {
                return new Expression($value[0], $value[1], $value[2]);
            }

            if (is_int($key)) {
                return $value;
            }

            return new Expression($key, '=', $value);
        }, array_keys($criteria), $criteria);

        $filter = join(' ' . Expression::AND . ' ', $map);
        return $this->filter($filter);
    }

    public function filter(string $filter): self
    {
        $this->queryOptions['$filter'] = $filter ?: null;
        return $this;
    }

    public function skip(?int $skip): self
    {
        $this->queryOptions['$skip'] = $skip;
        return $this;
    }

    public function count(): self
    {
        $this->queryOptions['$count'] = 'true';
        return $this;
    }

    public function orderBy($field, $direction = null): self
    {
        if (is_array($field)) {
            $map = array_map(function ($key, $value) {
                if (is_array($value)) {
                    return $value[0] . ' ' . $value[1];
                }

                if (is_int($key)) {
                    return $value;
                }

                return $key . ' ' . $value;
            }, array_keys($field), $field);
            $this->queryOptions['$orderby'] = join(',', $map);
            return $this;
        }

        $this->queryOptions['$orderby'] = $field;
        if (!is_null($direction)) {
            $this->queryOptions['$orderby'] .= ' ' . $direction;
        }

        return $this;
    }

    public function include(string $includePart): self
    {
        $this->includePart = trim($includePart, '/');
        return $this;
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
                array_filter($this->queryOptions));
        }

        return $uri;
    }

    public function __toString(): string
    {
        return $this->buildUri();
    }
}