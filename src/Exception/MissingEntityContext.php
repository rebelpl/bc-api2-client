<?php
namespace Rebel\BCApi2\Exception;

use Rebel\BCApi2\Entity;
use Rebel\BCApi2\Exception;

class MissingEntityContext extends Exception
{
    public function __construct(Entity $entity)
    {
        parent::__construct("Missing entity context or primary key.");
    }
}