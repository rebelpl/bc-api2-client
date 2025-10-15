<?php
namespace Rebel\BCApi2;

use Psr\Http\Message\StreamInterface;
use Rebel\BCApi2\Entity\Collection;
use Carbon\Carbon;
use Rebel\BCApi2\Entity\DataStream;
use Rebel\BCApi2\Request\Expression;

class Entity
{
    const ODATA_ETAG = '@odata.etag';
    const ODATA_CONTEXT = '@odata.context';
    const ODATA_MEDIA_READLINK = '@odata.mediaReadLink';
    const ODATA_MEDIA_EDITLINK = '@odata.mediaEditLink';
    const NULL_GUID = '00000000-0000-0000-0000-000000000000';

    protected $primaryKey;
    
    protected $data = [];
    protected $original = [];
    protected $casts = [];

    public $context;
    protected $expanded = [];

    public function __construct(
        array $data = [],
        array $expanded = [])
    {
        $this->expanded = $expanded;
        $this->set($data);
    }
    
    public function hasExpandedProperties(): bool
    {
        return !empty($this->expanded);
    }

    public function getExpandedProperties(): array
    {
        return $this->expanded;
    }

    public function isExpandedProperty(string $property): bool
    {
        return in_array($property, $this->expanded) || array_key_exists($property, $this->expanded);
    }
    
    public function getETag(): ?string
    {
        return !empty($this->data[ Entity::ODATA_ETAG ])
            ? urldecode($this->data[ Entity::ODATA_ETAG ])
            : null;
    }

    public function setETag(?string $value): void
    {
        $this->data[ Entity::ODATA_ETAG ] = $value;
    }

    public function setPrimaryKey(?string $value): void
    {
        $this->data[ $this->primaryKey ] = $value;
    }

    public function getPrimaryKey()
    {
        return $this->data[ $this->primaryKey ] ?? null;
    }

    public function loadData(array $data, ?string $context = null): self
    {
        if (!empty($data[ Entity::ODATA_ETAG ])) {
            $this->context = $context;
        }
        
        foreach ($data as $property => $value) {
            if (!isset($this->primaryKey) && (strpos($property, '@odata') !== 0)) {
                $this->primaryKey = $property;
            }
            
            if ($property === self::ODATA_CONTEXT) {
                continue;
            }
            
            $this->load($property, $value);
        }

        if (!empty($data[ Entity::ODATA_ETAG ])) {
            $this->original = $this->data;
        }
        
        return $this;
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
    
    private function load(string $property, $value): void
    {
        if (!is_array($value)) {
            if (substr($property, -strlen(self::ODATA_MEDIA_READLINK)) === self::ODATA_MEDIA_READLINK) {
                $property = substr($property, 0, -strlen(self::ODATA_MEDIA_READLINK));
                $this->data[ $property ] = new DataStream($value);
                return;
            }

            if (substr($property, -strlen(self::ODATA_MEDIA_EDITLINK)) === self::ODATA_MEDIA_EDITLINK) {
                $property = substr($property, 0, -strlen(self::ODATA_MEDIA_EDITLINK));
                $this->data[ $property ] = new DataStream($value);
                return;
            }
        
            $this->data[ $property ] = $value;
            return;
        }
        
        if (!$this->isExpandedProperty($property)) {
            $this->expanded[] = $property;
        }
        
        $context = $this->getExpandedContext($property, false);
        if (!$this->arrayIsList($value)) {
            $className = $this->casts[ $property ] ?? Entity::class;
            $this->data[ $property ] = (new $className())->loadData($value, $context);
            return;
        }

        $cast = $this->casts[ $property ] ?? [ Entity::class ];
        if (!is_array($cast)) {
            $cast = [ $cast ];
        }
        
        $className = $cast[0];
        $this->data[ $property ] = new Collection(array_map(function ($item) use ($className, $context) {
            return (new $className())->loadData($item, $context);
        }, $value));
    }

    public function __get(string $property)
    {
        return $this->get($property);
    }

    public function __set(string $property, $value)
    {
        $this->set($property, $value);
    }

    public function __isset(string $property): bool
    {
        return $this->isset($property);
    }

    public function isset(string $property): bool
    {
        return isset($this->data[ $property ]);
    }
    
    public function get(string $property, $cast = null)
    {
        $cast = $cast ?? $this->casts[ $property ] ?? null;
        if (is_array($cast) || $cast === 'collection') {
            return $this->getAsCollection($property);
        }

        if (!array_key_exists($property, $this->data)) {
            throw new Exception\PropertyDoesNotExistException($property);
        }
        
        switch ($cast) {
            case 'date':
            case 'datetime':
                return $this->getAsDateTime($property);
            
            case 'guid': return $this->getAsGuid($property);
            default: return $this->data[ $property ];
        }
    }

    private function getAsCollection(string $property): Collection
    {
        if (!isset($this->data[ $property ])) {
            if (isset($this->data[ Entity::ODATA_ETAG ])) {
                throw new Exception\PropertyIsNotExpandedException($property);
            }
            
            $this->expanded[] = $property;
            $this->data[ $property ] = new Collection();
        }

        return $this->data[ $property ];
    }
    
    private function getAsDateTime(string $property): ?Carbon
    {
        $value = $this->data[ $property ] ?? null;
        if (empty($value) || (strpos($value, '0001') === 0)) {
            return null;
        }

        return Carbon::parse($value);
    }

    private function getAsGuid(string $property): ?string
    {
        $value = $this->data[ $property ] ?? null;
        if (empty($value) || $value === self::NULL_GUID) {
            return null;
        }
        
        return $value;
    }

    public function set($property, $value = null, $cast = null): self
    {
        if (is_array($property)) {
            foreach ($property as $name => $value) {
                $this->set($name, $value);
            }
            
            return $this;
        }

        if (is_null($value)) {
            $this->data[ $property ] = null;
            return $this;
        }
        
        $cast = $cast ?? $this->casts[ $property ] ?? null;
        if ($value instanceof \DateTime) {
            $this->setFromDateTime($property, $value, $cast === 'date');
            return $this;
        }
        
        $this->data[ $property ] = $value;
        return $this;
    }

    private function setFromDateTime(string $property, ?\DateTime $value, bool $dateOnly): void
    {
        if (!$value instanceof Carbon) {
            $value = Carbon::make($value);
        }

        $this->data[ $property ] = $dateOnly
            ? $value->toDateString()
            : $value->toIso8601ZuluString();
    }

    public function toUpdate(): array
    {
        $changes = [];
        foreach ($this->data as $property => $value) {
            if ($value instanceof Entity || $value instanceof Collection) {
                if ($expandedChanges = $value->toUpdate()) {
                    $changes[ $property ] = $expandedChanges;
                }
                
                continue;
            }

            $original = $this->original[ $property ] ?? null;
            if ($value !== $original) {
                $changes[ $property ] = $value;
            }
        }
        
        return $changes;
    }
    
    public function getContext(bool $throwExceptionIfMissing = true): ?Request\UriBuilder
    {
        if (empty($this->context) || empty($this->getPrimaryKey())) {
            if ($throwExceptionIfMissing) {
                throw new Exception\MissingEntityContextException($this);
            }
            
            return null;
        }

        return new Request\UriBuilder($this->context, $this->getPrimaryKey());
    }
    
    public function getExpandedContext(string $name, bool $throwExceptionIfMissing = true): ?string
    {
        $context = $this->getContext($throwExceptionIfMissing);
        return $context
            ? $context->include($name)
            : null;
    }
    
    public function fetchAsStream(string $name, Client $client): StreamInterface
    {
        $url = $this->getExpandedContext($name);
        $response = $client->get($url);
        if ($response->getStatusCode() !== Client::HTTP_OK) {
            throw new Exception\InvalidResponseException($response);
        }

        return $response->getBody();
    }
    
    private function filterToString(array $criteria): string
    {
        if (count($criteria) === 0) {
            return '';
        }
        
        return '($filter=' . Expression::and($criteria) . ')';
    }
    
    public function expandWith(string $property, Client $client, array $criteria = []): self
    {
        $url = $this->getExpandedContext($property . $this->filterToString($criteria));
        $response = $client->get($url);
        
        if ($response->getStatusCode() !== Client::HTTP_OK) {
            throw new Exception\InvalidResponseException($response);
        }

        $data = json_decode($response->getBody(), true);
        $this->load($property, !empty($data['value']) && is_array($data['value']) 
            ? $data['value'] : $data);
        return $this;
    }
}