<?php
namespace Rebel\BCApi2;

readonly class Repository
{
    public function __construct(
        protected Client $client,
        protected string $entityName,
        protected string $apiPath = 'v2.0',
        protected string $entityClass = Entity::class)
    {
        if (!class_exists($this->entityClass)) {
            throw new Exception("Class '{$this->entityClass}' does not exist.");
        }
    }

    public function getEntityClass(): string
    {
        return $this->entityClass;
    }

    public function findBy(array $criteria, $orderBy = null, ?int $size = null, ?int $skip = null, array $expand = []): array
    {
        $request = (new Request($this->entityName))
            ->where($criteria)
            ->orderBy($orderBy)
            ->top($size)
            ->skip($skip)
            ->expand($expand);

        $uri = $this->client->buildUri($request, $this->apiPath);
        $response = $this->client->get($uri);

        if ($response->getStatusCode() !== Client::HTTP_OK) {
            throw new Exception(
                $response->getBody(),
                $response->getStatusCode());
        }

        $entities = [];
        $data = json_decode($response->getBody(), true);
        foreach ($data['value'] as $result) {
            $entities[] = $this->hydrate($result, Entity::ODATA_CONTEXT);
        }

        return $entities;
    }

    public function get(string $primaryKey, array $expand = []): ?Entity
    {
        $request = (new Request($this->entityName, $primaryKey))
            ->expand($expand);

        $uri = $this->client->buildUri($request, $this->apiPath);
        $response = $this->client->get($uri);

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

        $request = new Request($this->entityName, $entity->getPrimaryKey());
        $uri = $this->client->buildUri($request, $this->apiPath);
        $response = $this->client->delete($uri, $entity->getETag());

        if ($response->getStatusCode() !== Client::HTTP_NO_CONTENT) {
            throw new Exception(
                $response->getBody(),
                $response->getStatusCode());
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

        $request = new Request($this->entityName);
        $uri = $this->client->buildUri($request, $this->apiPath);

        $data = $entity->toUpdate();
        $response = $this->client->post($uri, json_encode($data));

        if ($response->getStatusCode() !== Client::HTTP_CREATED) {
            throw new Exception(
                $response->getBody(),
                $response->getStatusCode());
        }

        $data = json_decode($response->getBody(), true);
        $entity->loadData($data);
    }

    public function update(Entity $entity): void
    {
        if (empty($entity->getETag())) {
            throw new \InvalidArgumentException('Record not yet persisted.');
        }

        $request = new Request($this->entityName, $entity->getPrimaryKey());
        $uri = $this->client->buildUri($request, $this->apiPath);

        $data = $entity->toUpdate();
        if (!empty($data)) {
            $response = $this->client->patch($uri, json_encode($data), $entity->getETag());
            if ($response->getStatusCode() !== Client::HTTP_OK) {
                throw new Exception(
                    $response->getBody(),
                    $response->getStatusCode());
            }

            $data = json_decode($response->getBody(), true);
            $entity->loadData($data);
        }
    }

    private function hydrate(array $data, ?string $context = null): Entity
    {
        return new $this->entityClass($data, $context);
    }
}