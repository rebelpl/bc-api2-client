<?php
namespace Rebel\BCApi2\Exception;

use Rebel\BCApi2\Entity;
use Rebel\BCApi2\Exception;

class MissingEntityContextException extends Exception
{
    public function __construct(Entity $entity)
    {
        parent::__construct(sprintf("Missing entity context or primary key (context: %s, primaryKey: %s).", $entity->context, $entity->getPrimaryKey()));
    }
}