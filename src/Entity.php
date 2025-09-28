<?php
namespace Rebel\BCApi2;

use Psr\Http\Message\StreamInterface;
use Rebel\BCApi2\Entity\Collection;
use Rebel\BCApi2\Entity\DataStream;
use Rebel\BCApi2\Request\Expression;
use Carbon\Carbon;

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
    protected $expanded = [];
    protected $classMap = [];

    public $context;

    public function __construct(
        array $data = [],
        array $expanded = [],
        ?string $context = null)
    {
        foreach ($expanded as $name) {
            $this->expanded[ $name ] = null;
        }

        $this->loadData($data, $context);
    }
    
    public function addToClassMap(array $classMap): self
    {
        $this->classMap = array_merge($this->classMap, $classMap);
        return $this;
    }

    public function hasExpandedProperties(): bool
    {
        return !empty($this->expanded);
    }

    public function getExpandedProperties(): array
    {
        return array_keys($this->expanded);
    }

    public function isExpandedProperty(string $property): bool
    {
        return array_key_exists($property, $this->expanded);
    }
    
    protected function getClassnameFor(string $property): string
    {
        return $this->classMap[ $property ] ?? self::class;
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

    public function loadData(array $data, ?string $context = null): void
    {
        if (!empty($data[ Entity::ODATA_ETAG ])) {
            $this->context = $context;
        }
        
        foreach ($data as $property => $value) {
            if (!isset($this->primaryKey) && (strpos($property, '@odata') !== 0)) {
                $this->primaryKey = $property;
            }
            
            if (substr($property, -strlen(self::ODATA_MEDIA_READLINK)) === self::ODATA_MEDIA_READLINK) {
                $property = substr($property, 0, -strlen(self::ODATA_MEDIA_READLINK));
                $this->setAsStream($property, $value);
                continue;
            }

            if (substr($property, -strlen(self::ODATA_MEDIA_EDITLINK)) === self::ODATA_MEDIA_EDITLINK) {
                $property = substr($property, 0, -strlen(self::ODATA_MEDIA_EDITLINK));
                $this->setAsStream($property, $value);
                continue;
            }

            if ($property === self::ODATA_CONTEXT) {
                continue;
            }
            
            $this->set($property, $value);
        }

        if (!empty($data[ Entity::ODATA_ETAG ])) {
            $this->original = $this->data;
        }
    }

    private function setAsStream(string $property, string $value): void
    {
        $this->data[ $property ] = new DataStream($value);
    }

    private function hydrate(string $property, $value): ?object
    {
        if (is_null($value)) {
            return null;
        }

        if ($value instanceof Collection || $value instanceof Entity) {
            return $value;
        }

        $className = $this->getClassnameFor($property);
        $context = $this->getExpandedContext($property, false);
        if (!$this->arrayIsList($value)) {
            return new $className($value, [], $context);
        }

        return new Collection(array_map(function ($item) use ($className, $context) {
            return new $className($item, [], $context);
        }, $value));
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

    public function get(string $property)
    {
        if (!isset($this->data[ $property ])) {
            if ($this->isExpandedProperty($property)) {
                throw new Exception\UsedGetOnExpandedPropertyException($property);
            }
            
            throw isset($this->classMap[ $property ])
                ? new Exception\PropertyIsNotExpandedException($property)
                : new Exception\PropertyDoesNotExistException($property);
        }

        $value = $this->data[ $property ];
        if (is_null($value) || $value === self::NULL_GUID) {
            return null;
        }

        return $value;
    }
    
    public function getAsRelation(string $property)
    {
        if (!isset($this->expanded[ $property ])) {
            if (isset($this->data[ Entity::ODATA_ETAG ])) {
                throw new Exception\PropertyIsNotExpandedException($property);
            }

            $this->expanded[ $property ] = null;
        }

        return $this->expanded[ $property ];
    }

    /**
     * @return Collection<Entity>
     */
    public function getAsCollection(string $property): Collection
    {
        if (!isset($this->expanded[ $property ])) {
            if (isset($this->data[ Entity::ODATA_ETAG ])) {
                throw new Exception\PropertyIsNotExpandedException($property);
            }

            $this->expanded[ $property ] = new Collection();
        }
        
        return $this->expanded[ $property ];
    }
    
    public function getAsDateTime(string $property): ?Carbon
    {
        $value = $this->data[ $property ] ?? null;
        if (empty($value) || (strpos($value, '0001') === 0)) {
            return null;
        }

        return Carbon::parse($value);
    }

    public function getAsDate(string $property): ?Carbon
    {
        // it's only an alias
        return $this->getAsDateTime($property);
    }

    public function set(string $property, $value = null, ?string $cast = null): void
    {
        if ($this->isExpandedProperty($property) || isset($this->classMap[ $property ])) {
            $this->expanded[ $property ] = $this->hydrate($property, $value);
            return;
        }

        if (is_null($value)) {
            $this->data[ $property ] = null;
            return;
        }

        if ($value instanceof \DateTime) {
            $cast === 'date'
                ? $this->setAsDate($property, $value)
                : $this->setAsDateTime($property, $value);
            return;
        }

        $this->data[ $property ] = $value;
    }

    public function setAsDateTime(string $property, ?\DateTime $value): void
    {
        if (is_null($value)) {
            $this->data[ $property ] = null;
            return;
        }

        if (!$value instanceof Carbon) {
            $value = Carbon::make($value);
        }

        $this->data[ $property ] = $value->toIso8601ZuluString();
    }

    public function setAsDate(string $property, ?\DateTime $value): void
    {
        if (is_null($value)) {
            $this->data[ $property ] = null;
            return;
        }

        if (!$value instanceof Carbon) {
            $value = Carbon::make($value);
        }

        $this->data[ $property ] = $value->toDateString();
    }

    public function toUpdate(bool $includeExpandedProperties = false): array
    {
        $changes = array_filter($this->data, function ($key) {
            return $this->data[ $key ] !== ($this->original[ $key ] ?? null);
        }, ARRAY_FILTER_USE_KEY);

        if ($includeExpandedProperties) {
            foreach ($this->expanded as $key => $value) {
                if ($value instanceof \Traversable) {
                    $collectionChanges = [];
                    foreach ($value as $i => $entity) {
                        if ($entity instanceof Entity) {
                            $entityChanges = $entity->toUpdate();
                            if (!empty($entityChanges)) {
                                $collectionChanges[ $i ] = $entityChanges;
                            }
                        }
                    }

                    if (!empty($collectionChanges)) {
                        $changes[ $key ] = $collectionChanges;
                    }
                }
                elseif ($value instanceof Entity) {
                    $entityChanges = $value->toUpdate();
                    if (!empty($entityChanges)) {
                        $changes[ $key ] = $entityChanges;
                    }
                }
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
    
    public function doAction(string $name, Client $client): self
    {
        $url = $this->getExpandedContext($name);
        $response = $client->post($url, '');
        if ($response->getStatusCode() !== Client::HTTP_OK) {
            throw new Exception\InvalidResponseException($response);
        }
        
        return $this;
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
        if (isset($data['value']) && is_array($data['value'])) {
            $this->expanded[ $property ] = $this->hydrate($property, $data['value']);
            return $this;
        }

        $this->expanded[ $property ] = $this->hydrate($property, $data);
        return $this;
    }
}
