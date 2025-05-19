<?php
namespace Rebel\BCApi2;

use OutOfBoundsException;
use Rebel\BCApi2\Entity\Collection;
use Rebel\BCApi2\Request\Expression;

class Entity
{
    const string ODATA_ETAG = '@odata.etag';
    const string ODATA_CONTEXT = '@odata.context';

    protected array $original = [];
    protected string $primaryKey = 'id';
    protected array $classMap = [];
    protected array $casts = [];

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

    public function get(string $property, ?string $castType = null): mixed
    {
        $value = $this->data[ $property ];
        if (is_null($value)) {
            return null;
        }

        $castType = $castType ?? $this->castTypeFor($property);
        if (is_a($castType, \BackedEnum::class, true)) {
            return call_user_func([ $castType, 'tryFrom' ], $value);
        }

        return match ($castType) {
            'date' => $this->getAsDateTime($property, Expression::DATE_FORMAT),
            'datetime' => $this->getAsDateTime($property, Expression::DATETIME_FORMAT),
            default => $value,
        };
    }

    private function castTypeFor(string $property): ?string
    {
        if (str_ends_with($property, 'Date')) {
            return 'date';
        }

        if (str_ends_with($property, 'DateTime')
         || str_ends_with($property, 'ModifiedAt')
         || str_ends_with($property, 'CreatedAt')) {
            return 'datetime';
        }

        return null;
    }

    public function getAsDateTime(string $property, string $format = Expression::DATETIME_FORMAT): ?\DateTime
    {
        $value = $this->data[ $property ] ?? null;
        if (empty($value) || str_starts_with($value, '0001')) {
            return null;
        }

        return \DateTime::createFromFormat($format, $value);
    }

    public function set($property, mixed $value = null): self
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
            $this->data[ $property ] = $value->format('H:i:s.v') === '00:00:00.000'
                ? $value->format(Expression::DATE_FORMAT)
                : $value->format(Expression::DATETIME_FORMAT);
            return $this;
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

    public function toUpdate(): array
    {
        return array_filter($this->data, function ($key) {
            return $this->data[ $key ] !== $this->original[ $key ];
        }, ARRAY_FILTER_USE_KEY);
    }
}