<?php
namespace Rebel\BCApi2\Request;

use Rebel\BCApi2\Entity;
use Rebel\BCApi2\Request;

class Factory
{
    public static function createEntity(string $baseUrl, Entity $entity, array $data): Request
    {
        return new Request('POST',
            new Request\UriBuilder($baseUrl)
                ->expand($entity->getExpandedProperties()),
            body: json_encode($data));
    }

    public static function updateEntity(string $baseUrl, Entity $entity, array $data): Request
    {
        return new Request('PATCH',
            new Request\UriBuilder($baseUrl, $entity->getPrimaryKey())
                ->expand($entity->getExpandedProperties()),
            body: json_encode($data),
            etag: $entity->getETag());
    }

    public static function saveEntity(string $baseUrl, Entity $entity, array $data): Request
    {
        return $entity->getETag()
            ? self::updateEntity($baseUrl, $entity, $data)
            : self::createEntity($baseUrl, $entity, $data);
    }

    public static function deleteEntity(string $baseUrl, Entity $entity): Request
    {
        return new Request('DELETE',
            new Request\UriBuilder($baseUrl, $entity->getPrimaryKey()),
            etag: $entity->getETag());
    }
}