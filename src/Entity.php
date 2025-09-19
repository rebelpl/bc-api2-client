<?php
namespace Rebel\BCApi2;

use Rebel\BCApi2\Entity\Collection;
use Carbon\Carbon;

class Entity
{
    const  ODATA_ETAG = '@odata.etag';
    const  ODATA_CONTEXT = '@odata.context';

    protected $original = [];
    protected $primaryKey = 'id';
    protected $classMap = [];
    protected $expanded = [];

    public $context;
    protected $data = [];

    public function __construct(array $data = [], array $expanded = [], ?string $context = null)
    {
        $this->data = $data;
        foreach ($expanded as $name) {
            $this->expanded[ $name ] = null;
        }

        $this->loadData($data);
        $this->context = $context;
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

    public function getPrimaryKey(): ?string
    {
        return $this->data[ $this->primaryKey ] ?? null;
    }

    public function loadData(array $data): void
    {
        foreach ($data as $property => $value) {
            $this->set($property, $value);
        }

        if (!empty($this->data[ Entity::ODATA_ETAG ])) {
            $this->original = $this->data;
        }
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
        if (!$this->arrayIsList($value)) {
            return new $className($value);
        }

        return new Collection(array_map(function ($item) use ($className) {
            return new $className($item);
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

    public function get(string $property, ?string $cast = null)
    {
        if ($this->isExpandedProperty($property)) {
            if ($cast === 'collection') {
                return $this->getAsCollection($property);
            }

            return $this->expanded[ $property ];
        }

        if (!isset($this->data[ $property ])) {
            throw isset($this->classMap[ $property ])
                ? new Exception('Property "' . $property . '" is not expanded.')
                : new Exception('Property "' . $property . '" does not exist.');
        }

        $value = $this->data[ $property ];
        if (is_null($value) || $value === '00000000-0000-0000-0000-000000000000') {
            return null;
        }

        if (in_array($cast, [ 'datetime', 'date' ])) {
            return $this->getAsDateTime($property);
        }

//        if (is_a($cast, \BackedEnum::class, true)) {
//            return $this->getAsEnum($property, $cast);
//        }

        return $value;
    }

    public function getAsCollection(string $property): Collection
    {
        return $this->expanded[ $property ] ?? $this->expanded[ $property ] = new Collection();
    }

    public function getAsEnum(string $property, string $enumClass)
    {
        $value = $this->data[ $property ] ?? null;
        if (is_null($value)) {
            return null;
        }

        return call_user_func([ $enumClass, 'tryFrom' ], $value);
    }

    public function getAsDateTime(string $property): ?Carbon
    {
        $value = $this->data[ $property ] ?? null;
        if (empty($value) || str_starts_with($value, '0001')) {
            return null;
        }

        return Carbon::parse($value);
    }

    public function getAsDate(string $property): ?Carbon
    {
        return $this->getAsDateTime($property);
    }

    public function set($property, $value = null, ?string $cast = null): void
    {
        if (is_array($property)) {
            foreach ($property as $key => $value) {
                $this->set($key, $value);
            }

            return;
        }

        if ($this->isExpandedProperty($property)) {
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
}
