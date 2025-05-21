<?php
namespace Rebel\BCApi2;

use Rebel\BCApi2\Entity\Collection;
use Carbon\Carbon;


class Entity
{
    const string ODATA_ETAG = '@odata.etag';
    const string ODATA_CONTEXT = '@odata.context';

    protected array $original = [];
    protected string $primaryKey = 'id';
    protected array $classMap = [];

    public ?string $etag {
        get => isset($this->data[ Entity::ODATA_ETAG ]) ? urldecode($this->data[ Entity::ODATA_ETAG ]) : null;
        set => $this->set(Entity::ODATA_ETAG, $value);
    }

    public readonly ?string $context;

    public function __construct(protected array $data = [], ?string $context = null)
    {
        $this->loadData($data);
        $this->context = $context;
    }

    protected function getClassnameFor(string $property): string
    {
        return $this->classMap[ $property ] ?? static::class;
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

    public function getPrimaryKey(): ?string
    {
        return $this->get($this->primaryKey);
    }

    public function loadData(array $data): void
    {
        foreach ($data as $property => $value) {
            if (is_array($value)) {
                $this->data[ $property ] = $this->hydrate($property, $value);
            }
            else {
                if ($property === self::ODATA_ETAG) {
                    $this->original = $data;
                }

                $this->set($property, $value);
            }
        }
    }

    private function hydrate(string $property, array $value): mixed
    {
        $className = $this->getClassnameFor($property);
        if (!array_is_list($value)) {
            return new $className($value);
        }

        $collection = new Collection();
        foreach ($value as $item) {
            $collection->append(new $className($item));
        }

        return $collection;
    }

    public function get(string $property, ?string $cast = null): mixed
    {
        $value = $this->data[ $property ];
        if (is_null($value)) {
            return null;
        }

        if (in_array($cast, [ 'datetime', 'date' ])) {
            return $this->getAsDateTime($property);
        }

        if (is_a($cast, \BackedEnum::class, true)) {
            return $this->getAsEnum($property, $cast);
        }

        return $value;
    }

    public function getAsEnum(string $property, string $enumClass): mixed
    {
        $value = $this->data[ $property ];
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

    public function set($property, mixed $value = null, ?string $cast = null): self
    {
        if (is_array($property)) {
            foreach ($property as $key => $value) {
                $this->set($key, $value);
            }

            return $this;
        }

        if (is_null($value)) {
            $this->data[ $property ] = null;
            return $this;
        }

        if ($value instanceof \DateTime) {
            return $cast === 'date'
                ? $this->setAsDate($property, $value)
                : $this->setAsDateTime($property, $value);
        }

        if ($value instanceof \BackedEnum) {
            $this->data[ $property ] = $value->value;
            return $this;
        }

        if ($value instanceof \UnitEnum) {
            $this->data[ $property ] = $value->name;
            return $this;
        }

        $this->data[ $property ] = $value;
        return $this;
    }

    public function setAsDateTime(string $property, ?\DateTime $value): self
    {
        if (is_null($value)) {
            $this->data[ $property ] = null;
            return $this;
        }

        if (!$value instanceof Carbon) {
            $value = Carbon::make($value);
        }

        $this->data[ $property ] = $value->toIso8601ZuluString();
        return $this;
    }

    public function setAsDate(string $property, ?\DateTime $value): self
    {
        if (is_null($value)) {
            $this->data[ $property ] = null;
            return $this;
        }

        if (!$value instanceof Carbon) {
            $value = Carbon::make($value);
        }

        $this->data[ $property ] = $value->toDateString();
        return $this;
    }

    public function toUpdate(): array
    {
        return array_filter($this->data, function ($key) {
            return $this->data[ $key ] !== $this->original[ $key ];
        }, ARRAY_FILTER_USE_KEY);
    }
}