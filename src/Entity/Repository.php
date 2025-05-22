<?php
namespace Rebel\BCApi2\Entity;

use Rebel\BCApi2\Client;
use Rebel\BCApi2\Entity;
use Rebel\BCApi2\Exception;
use Rebel\BCApi2\Request;
use Rebel\BCApi2\Request\Batch;

readonly class Repository
{
    private string $baseUrl;

    public function __construct(
        protected Client $client,
        protected string $entitySetName,
        protected string $entityClass = Entity::class,
        bool             $isCompanyResource = true)
    {
        if (!class_exists($this->entityClass)) {
            throw new Exception("Class '{$this->entityClass}' does not exist.");
        }

        $this->baseUrl = $isCompanyResource
            ? $this->client->getCompanyPath() . '/' . $this->entitySetName
            : $this->entitySetName;
    }

    public function getEntityClass(): string
    {
        return $this->entityClass;
    }

    /**
     * @return Entity[]
     * @throws Exception
     */
    public function findAll($orderBy = null): array
    {
        return $this->findBy([], $orderBy);
    }

    public function findBy(array $criteria, $orderBy = null, ?int $size = null, ?int $skip = null, array $expand = []): array
    {
        $request = new Request('GET',
            new Request\UriBuilder($this->baseUrl)
                ->where($criteria)
                ->orderBy($orderBy)
                ->top($size)
                ->skip($skip)
                ->expand($expand));

        $response = $this->client->call($request);
        if ($response->getStatusCode() !== Client::HTTP_OK) {
            throw new Exception(
                $response->getBody(),
                $response->getStatusCode());
        }

        $entities = [];
        $data = json_decode($response->getBody(), true);
        foreach ($data['value'] as $result) {
            $entities[] = $this->hydrate($result, $data[ Entity::ODATA_CONTEXT ]);
        }

        return $entities;
    }

    public function get(string $primaryKey, array $expand = []): ?Entity
    {
        $request = new Request('GET',
            new Request\UriBuilder($this->entitySetName, $primaryKey)
                ->expand($expand));

        $response = $this->client->call($request);
        if ($response->getStatusCode() === Client::HTTP_NOT_FOUND) {
            return null;
        }

        if ($response->getStatusCode() !== Client::HTTP_OK) {
            throw new Exception(
                $response->getBody(),
                $response->getStatusCode());
        }

        $data = json_decode($response->getBody(), true);
        return $this->hydrate($data);
    }

    public function find(string $primaryKey, array $expand = []): ?Entity
    {
        return $this->get($primaryKey, $expand);
    }

    public function delete(Entity $entity): void
    {
        if (empty($entity->getETag())) {
            throw new \InvalidArgumentException('Record ETag is missing (entity not yet persisted?).');
        }

        if (empty($entity->getPrimaryKey())) {
            throw new \InvalidArgumentException('Record PrimaryKey is missing (entity not yet persisted?).');
        }

        $request = new Request('DELETE',
            new Request\UriBuilder($this->entitySetName, $entity->getPrimaryKey()),
            etag: $entity->getETag());

        $response = $this->client->call($request);
        if ($response->getStatusCode() !== Client::HTTP_NO_CONTENT) {
            throw new Exception(
                $response->getBody(),
                $response->getStatusCode());
        }

        $entity->setETag(null);
    }

    public function save(Entity $entity, array $expand = []): void
    {
        $entity->getETag()
            ? $this->update($entity, $expand)
            : $this->create($entity, $expand);
    }

    public function create(Entity $entity, array $expand = []): void
    {
        if (!empty($entity->getETag())) {
            throw new \InvalidArgumentException('Record already persisted.');
        }

        $data = $entity->toUpdate();
        $request = new Request('POST',
            new Request\UriBuilder($this->entitySetName)->expand($expand),
            body: json_encode($data));

        $response = $this->client->call($request);
        if ($response->getStatusCode() !== Client::HTTP_CREATED) {
            throw new Exception(
                $response->getBody(),
                $response->getStatusCode());
        }

        $data = json_decode($response->getBody(), true);
        $entity->loadData($data);
    }

    public function update(Entity $entity, array $expand = []): void
    {
        if (empty($entity->getETag())) {
            throw new \InvalidArgumentException('Record not yet persisted.');
        }

        $data = $entity->toUpdate();
        if (empty($data)) {
            return;
        }

        $request = new Request('PATCH',
            new Request\UriBuilder($this->entitySetName, $entity->getPrimaryKey())->expand($expand),
            body: json_encode($data),
            etag: $entity->getETag());

        $response = $this->client->call($request);
        if ($response->getStatusCode() !== Client::HTTP_OK) {
            throw new Exception(
                $response->getBody(),
                $response->getStatusCode());
        }

        $data = json_decode($response->getBody(), true);
        $entity->loadData($data);
    }

    public function batch(array $entities, array $expand = []): void
    {
        $requests = [];
        foreach ($entities as $key => $entity) {
            $data = $entity->toUpdate();
            if (empty($data)) {
                continue;
            }

            if ($entity->getETag()) {
                $requests[ $key ] = new Request('PATCH',
                    new Request\UriBuilder($this->entitySetName, $entity->getPrimaryKey())->expand($expand),
                    body: json_encode($data),
                    etag: $entity->getETag());
            }
            else {
                $requests[ $key ] = new Request('POST',
                    new Request\UriBuilder($this->entitySetName)->expand($expand),
                    body: json_encode($data));
            }
        }

        if (empty($requests)) {
            return;
        }

        $batch = new Batch($requests);
        $response = $this->client->call($batch->getRequest());
        if ($response->getStatusCode() !== Client::HTTP_OK) {
            throw new Exception(
                $response->getBody(),
                $response->getStatusCode());
        }

        $data = json_decode($response->getBody(), true);
        foreach ($data['responses'] as $response) {
            $entity = $entities[ $response['id'] ];
            $entity->loadData($response['body']);
        }
    }

    private function hydrate(array $data, ?string $context = null): Entity
    {
        return new $this->entityClass($data, $context);
    }
}