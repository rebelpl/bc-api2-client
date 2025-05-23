<?php
namespace Rebel\BCApi2\Request\EntityOperation;

use Rebel\BCApi2\Entity;
use Rebel\BCApi2\Request;

class Create extends Request
{
    public function __construct(string $baseUrl, Entity $entity, array $data)
    {
        parent::__construct('POST',
            new Request\UriBuilder($baseUrl)
                ->expand($entity->getExpandedProperties()),
            body: json_encode($data));
    }
}