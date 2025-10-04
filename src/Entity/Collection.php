<?php
namespace Rebel\BCApi2\Entity;

use Rebel\BCApi2\Entity;

class Collection extends \ArrayObject
{
    public function toArray(): array
    {
        return $this->getArrayCopy();
    }
    
    public function toUpdate(): array
    {
        return array_filter(
            array_map(function ($entity) {
                /** @var Entity $entity */
                return $entity->toUpdate() ?: null;
            }, $this->toArray())
        );
    }
}