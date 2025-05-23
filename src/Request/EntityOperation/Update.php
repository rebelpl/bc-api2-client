<?php
namespace Rebel\BCApi2\Request\EntityOperation;

use Rebel\BCApi2\Entity;
use Rebel\BCApi2\Request;

class Update extends Request
{
    public function __construct(string $baseUrl, Entity $entity, array $data)
    {
        parent::__construct('PATCH',
            new Request\UriBuilder($baseUrl, $entity->getPrimaryKey())
                ->expand($entity->getExpandedProperties()),
            body: json_encode($data),
            etag: $entity->getETag());
    }
}