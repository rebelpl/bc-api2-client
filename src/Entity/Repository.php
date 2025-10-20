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
class Repository
{
    protected readonly string $baseUrl;
    protected array $expandedByDefault = [];
    protected array $defaultFilters = [];

    /**
     * @param class-string<T> $entityClass
     */
    public function __construct(
        protected readonly Client $client,
        string $entitySetName,
        protected readonly string $entityClass = Entity::class,
        bool             $isCompanyResource = true)
    {
        if (!class_exists($this->entityClass)) {
            throw new Exception("Class '$this->entityClass' does not exist.");
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

    public function setExpandedByDefault(array $expanded): static
    {
        $this->expandedByDefault = $expanded;
        return $this;
    }

    public function setDefaultFilters(array $filters): static
    {
        $this->defaultFilters = $filters;
        return $this;
    }

    /**
     * @return ?T
     */
    public function findOneBy(array $filters, array $expanded = []): ?Entity
    {
        $result = $this->findBy($filters, size: 1, expanded: $expanded);
        return !empty($result) ? $result[0] : null;
    }

    /**
     * @return T[]
     */
    public function findAll($orderBy = null, array $expanded = []): array
    {
        return $this->findBy([], $orderBy, expanded: $expanded);
    }

    /**
     * @return T[]
     */
    public function findBy(array $filters, $orderBy = null, ?int $size = null, ?int $skip = null, array $expanded = []): array
    {
        $filters = array_merge($this->defaultFilters, $filters);
        $expanded = array_merge($this->expandedByDefault, $expanded);
        $request = new Request('GET',
            new Request\UriBuilder($this->baseUrl)
                ->where($filters)
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
        if (!isset($data['value'])) {
            throw new Exception\InvalidResponseException($response);
        }

        foreach ($data['value'] as $result) {
            $entities[] = $this->hydrate($result, $expanded);
        }

        return $entities;
    }

    /**
     * @return ?T
     */
    public function get(string $primaryKey, array $expanded = []): ?Entity
    {
        $expanded = array_merge($this->expandedByDefault, $expanded);
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

    public function reload(Entity $entity): void
    {
        $request = new Request('GET',
            new Request\UriBuilder($this->baseUrl, $entity->getPrimaryKey())
                ->expand($entity->getExpandedProperties()));

        $response = $this->client->call($request);
        if ($response->getStatusCode() === Client::HTTP_NOT_FOUND) {
            throw new Exception\InvalidResponseException($response);
        }

        if ($response->getStatusCode() !== Client::HTTP_OK) {
            throw new Exception\InvalidResponseException($response);
        }

        $data = json_decode($response->getBody(), true);
        $entity->loadData($data, $this->baseUrl);
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

        $data = $entity->toUpdate();
        $request = Request\Factory::createEntity($this->baseUrl, $entity, $data);
        # echo $request->getBody()->getContents(); exit();

        $response = $this->client->call($request);
        if ($response->getStatusCode() !== Client::HTTP_CREATED) {
            throw new Exception\InvalidResponseException($response);
        }

        $data = json_decode($response->getBody(), true);
        $entity->loadData($data, $this->baseUrl);
    }

    public function update(Entity $entity, bool $forceEmptyUpdate = false): void
    {
        if (empty($entity->getETag())) {
            throw new \InvalidArgumentException('Record not yet persisted.');
        }

        $data = $entity->toUpdate();
        if (empty($data) && !$forceEmptyUpdate) {
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
            $entity->loadData($data, $this->baseUrl);
            return;
        }

        foreach ($data['responses'] as $response) {
            $body = $response['body'];
            if (!empty($body['error'])) {
                throw new Exception(
                    $body['error']['code'] . ': ' .$body['error']['message'],
                    $response['status']);
            }

            if ($response['id'] === '$read') {
                $entity->loadData($body, $this->baseUrl);
            }
        }
    }

    /**
     * @param Entity[] $entities
     */
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
            $entity->loadData($response['body'], $this->baseUrl);
        }
    }

    private function hydrate(array $data, array $expanded): Entity
    {
        /** @var Entity $entity */
        $entity = new $this->entityClass(expanded: $expanded);
        return $entity->loadData($data, $this->baseUrl);
    }

    /**
     * @todo $batch call
     */
    public function callBoundAction(string $action, Entity $entity, $reloadAfterwards = true): void
    {
        $url = $entity->getExpandedContext($action);
        if (!$reloadAfterwards) {
            $response = $this->client->post($url, '');
            if ($response->getStatusCode() !== Client::HTTP_OK) {
                throw new Exception\InvalidResponseException($response);
            }

            return;
        }

        $batch = new Batch([
            '$action' => new Request('POST', $url),
            '$read'   => new Request('GET',
                new Request\UriBuilder($this->baseUrl, $entity->getPrimaryKey())
                    ->expand($entity->getExpandedProperties())),
        ]);

        // VALIDATE BATCH CONTENTS!
        $response = $this->client->call($batch->getRequest());
        if ($response->getStatusCode() !== Client::HTTP_OK) {
            throw new Exception\InvalidResponseException($response);
        }

        // duplicate with update()
        $data = json_decode($response->getBody(), true);
        foreach ($data['responses'] as $response) {
            $body = $response['body'];
            if (!empty($body['error'])) {
                throw new Exception(
                    $body['error']['code'] . ': ' .$body['error']['message'],
                    $response['status']);
            }

            if ($response['id'] === '$read') {
                $entity->loadData($body, $this->baseUrl);
            }
        }
    }
}