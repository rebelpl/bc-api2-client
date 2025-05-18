<?php
namespace Rebel\BCApi2;

use Rebel\BCApi2\Entity\Collection;

class Entity
{
    const ODATA_ETAG = '@odata.etag';
    const ODATA_CONTEXT = '@odata.context';

    protected array $original = [];
    protected string $primaryKey = 'id';
    protected array $classMap = [];

    /** @var Entity[][] */
    protected array $included = [];

    public function __construct(protected array $data = [], protected ?string $context = null)
    {
        $this->loadData($data);
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
            if ($property === self::ODATA_ETAG) {
                $this->data[ $property ] = $value;
                $this->original = $data;
            }
            elseif ($property === self::ODATA_CONTEXT) {
                $this->context = $value;
            }
            elseif (is_array($value)) {
                $this->data[ $property ] = $this->hydrate($property, $value);
            }
            else {
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

    public function get(string $key)
    {
        return $this->data[ $key ] ?? null;
    }

    public function set($key, $value = null): self
    {
        if (is_array($key)) {
            foreach ($key as $field => $value) {
                $this->set($field, $value);
            }

            return $this;
        }

        if ($value instanceof \DateTime) {
            $this->data[ $key ] = $value->format('H:i:s.v') === '00:00:00.000'
                ? $value->format(Expression::DATE_FORMAT)
                : $value->format(Expression::DATETIME_FORMAT);
            return $this;
        }

        $this->data[ $key ] = $value;
        return $this;
    }

    public function getAsDateTime(string $key): ?\DateTime
    {
        $value = $this->get($key);
        if (empty($value) || str_starts_with($value, '0001')) {
            return null;
        }

        return \DateTime::createFromFormat(Expression::DATETIME_FORMAT, $value);
    }

    public function getAsDate(string $key): ?\DateTime
    {
        $value = $this->get($key);
        if (empty($value) || str_starts_with($value, '0001')) {
            return null;
        }

        return \DateTime::createFromFormat(Expression::DATE_FORMAT, $value);
    }

    public function toArray(): array
    {
        return $this->data;
    }

    public function toUpdate(): array
    {
        return array_filter($this->data, function ($key) {
            return $this->data[ $key ] !== $this->original[ $key ];
        }, ARRAY_FILTER_USE_KEY);
    }
}