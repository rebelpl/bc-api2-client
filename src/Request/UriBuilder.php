<?php
namespace Rebel\BCApi2\Request;

use GuzzleHttp\Psr7;
use Rebel\BCApi2\Exception;

class UriBuilder
{
    private $resourceUrl;
    protected $includePart = null;
    protected $queryOptions = [];
    private $primaryKey;

    public function __construct(
        string $resourceUrl,
        ?string $primaryKey = null)
    {
        $this->primaryKey = $primaryKey;
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
        if (empty($properties)) {
            return $this;
        }
        
        $this->queryOptions['$expand'] = is_array($properties)
            ? $this->expandStringFromArray($properties)
            : (string)$properties;
        return $this;
    }
    
    private function expandStringFromArray(array $array): string
    {
        if ($this->arrayIsList($array)) {
            return join(',', $array);
        }

        // todo: $top, $skip, etc. - introduce something like ExpandBuilder
        $expand = [];
        foreach ($array as $key => $value) {
            if (is_int($key)) {
                $expand[] = $value;
            }
            elseif (is_string($key) && is_array($value)) {
                $expand[] = $key . '($filter=' . $this->filterStringFromCriteria($value) . ')';
            }
            else {
                throw new Exception(sprintf('Invalid expand key: %s.', $key));
            }
        }
        
        return join(',', $expand);
    }

    private function arrayIsList(array $array): bool
    {
        $i = 0;
        foreach ($array as $key => $value) {
            if ($key !== $i++) {
                return false;
            }
        }

        return true;
    }
    
    public function top(?int $top): self
    {
        $this->queryOptions['$top'] = $top;
        return $this;
    }

    public function where(array $criteria): self
    {
        $filter = $this->filterStringFromCriteria($criteria);
        return $this->filter($filter);
    }
    
    private function filterStringFromCriteria(array $criteria): string
    {
        return Expression::and(array_map(function ($key, $value) {
            if ($value instanceof Expression) {
                return $value;
            }

            if (is_int($key)) {
                return $value;
            }

            if (is_array($value)) {
                return Expression::in($key, $value);
            }
            
            return Expression::equals($key, $value);
        }, array_keys($criteria), $criteria));
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
            $uri .= sprintf('(%s)', new ODataValue($this->primaryKey));
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