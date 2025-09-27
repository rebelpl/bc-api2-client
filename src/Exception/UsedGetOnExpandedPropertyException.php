<?php
namespace Rebel\BCApi2\Exception;
use Rebel\BCApi2\Exception;

class UsedGetOnExpandedPropertyException extends Exception
{
    public function __construct(string $property)
    {
        parent::__construct("Property '$property' is expanded, use getAsCollection() or getAsRelation() method.");
    }
}