<?php
namespace Rebel\BCApi2\Entity;

use Rebel\BCApi2\Client;
use Rebel\BCApi2\Entity;
use Rebel\BCApi2\Exception;
use Rebel\BCApi2\Request;
use Rebel\BCApi2\Request\Batch;

/**
 * @template T of Entity
 */
readonly class Repository
{
    private string $baseUrl;

    /**
     * @param class-string<T> $entityClass
     */
    public function __construct(
        protected Client $client,
        string $entitySetName,
        protected string $entityClass = Entity::class,
        bool             $isCompanyResource = true)
    {
        if (!class_exists($this->entityClass)) {
            throw new Exception("Class '{$this->entityClass}' does not exist.");
        }

        $this->baseUrl = $isCompanyResource
            ? $this->client->getCompanyPath() . '/' . $entitySetName
            : $entitySetName;
    }

    public function getEntityClass(): string
    {
        return $this->entityClass;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * @return T[]
     */
    public function findAll($orderBy = null): array
    {
        return $this->findBy([], $orderBy);
    }

    /**
     * @return T[]
     */
    public function findBy(array $criteria, $orderBy = null, ?int $size = null, ?int $skip = null, array $expanded = []): array
    {
        $request = new Request('GET',
            new Request\UriBuilder($this->baseUrl)
                ->where($criteria)
                ->orderBy($orderBy)
                ->top($size)
                ->skip($skip)
                ->expand($expanded));

        $response = $this->client->call($request);
        if ($response->getStatusCode() !== Client::HTTP_OK) {
            throw new Exception\InvalidResponseException($response);
        }

        $entities = [];
        $data = json_decode($response->getBody(), true);
        foreach ($data['value'] as $result) {
            $entities[] = $this->hydrate($result, $expanded, $data[ Entity::ODATA_CONTEXT ]);
        }

        return $entities;
    }

    /**
     * @return T
     */
    public function get(string $primaryKey, array $expanded = []): ?Entity
    {
        $request = new Request('GET',
            new Request\UriBuilder($this->baseUrl, $primaryKey)
                ->expand($expanded));

        $response = $this->client->call($request);
        if ($response->getStatusCode() === Client::HTTP_NOT_FOUND) {
            return null;
        }

        if ($response->getStatusCode() !== Client::HTTP_OK) {
            throw new Exception\InvalidResponseException($response);
        }

        $data = json_decode($response->getBody(), true);
        return $this->hydrate($data, $expanded);
    }

    /**
     * @return T
     */
    public function find(string $primaryKey, array $expanded = []): ?Entity
    {
        return $this->get($primaryKey, $expanded);
    }

    /**
     * @param T $entity
     */
    public function delete(Entity $entity): void
    {
        if (empty($entity->getETag())) {
            throw new \InvalidArgumentException('Record ETag is missing (entity not yet persisted?).');
        }

        if (empty($entity->getPrimaryKey())) {
            throw new \InvalidArgumentException('Record PrimaryKey is missing (entity not yet persisted?).');
        }

        $request = Request\Factory::deleteEntity($this->baseUrl, $entity);
        $response = $this->client->call($request);
        if ($response->getStatusCode() !== Client::HTTP_NO_CONTENT) {
            throw new Exception\InvalidResponseException($response);
        }

        $entity->setETag(null);
    }

    public function save(Entity $entity): void
    {
        $entity->getETag()
            ? $this->update($entity)
            : $this->create($entity);
    }

    public function create(Entity $entity): void
    {
        if (!empty($entity->getETag())) {
            throw new \InvalidArgumentException('Record already persisted.');
        }

        $data = $entity->toUpdate(true);
        $request = Request\Factory::createEntity($this->baseUrl, $entity, $data);

        $response = $this->client->call($request);
        if ($response->getStatusCode() !== Client::HTTP_CREATED) {
            throw new Exception\InvalidResponseException($response);
        }

        $data = json_decode($response->getBody(), true);
        $entity->loadData($data);
    }

    public function update(Entity $entity): void
    {
        if (empty($entity->getETag())) {
            throw new \InvalidArgumentException('Record not yet persisted.');
        }

        $data = $entity->toUpdate(true);
        if (empty($data)) {
            return;
        }

        $request = Request\Factory::updateEntity($this->baseUrl, $entity, $data);
        # echo $request->getBody()->getContents(); exit();

        $response = $this->client->call($request);
        if ($response->getStatusCode() !== Client::HTTP_OK) {
            throw new Exception\InvalidResponseException($response);
        }

        $data = json_decode($response->getBody(), true);
        if (!isset($data['responses'])) {
            $entity->loadData($data);
            return;
        }

        foreach ($data['responses'] as $response) {
            $body = $response['body'];
            if (!empty($body['error'])) {
                throw new Exception(
                    $body['error']['message'],
                    $body['error']['code']);
            }

            if ($response['id'] === '$read') {
                $entity->loadData($body);
            }
        }
    }

    public function batchSave(array $entities): void
    {
        $batch = new Batch();
        foreach ($entities as $key => $entity) {
            $data = $entity->toUpdate();
            if (empty($data)) {
                continue;
            }

            $request = Request\Factory::saveEntity($this->baseUrl, $entity, $data);
            $batch->add($key, $request);
        }

        if ($batch->empty()) {
            return;
        }

        $response = $this->client->call($batch->getRequest());
        if ($response->getStatusCode() !== Client::HTTP_OK) {
            throw new Exception\InvalidResponseException($response);
        }

        $data = json_decode($response->getBody(), true);
        foreach ($data['responses'] as $response) {
            $entity = $entities[ $response['id'] ];
            $entity->loadData($response['body']);
        }
    }

    private function hydrate(array $data, array $expanded = [], ?string $context = null): Entity
    {
        return new $this->entityClass($data, array_is_list($expanded) ? $expanded : array_keys($expanded), $context);
    }
}