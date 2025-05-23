<?php
namespace Rebel\BCApi2\Request\EntityOperation;

use Rebel\BCApi2\Entity;
use Rebel\BCApi2\Request;

class Delete extends Request
{
    public function __construct(string $baseUrl, Entity $entity)
    {
        parent::__construct('DELETE',
            new Request\UriBuilder($baseUrl, $entity->getPrimaryKey()),
            etag: $entity->getETag());
    }
}