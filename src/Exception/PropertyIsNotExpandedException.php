<?php
namespace Rebel\BCApi2\Exception;
use Rebel\BCApi2\Exception;

class PropertyIsNotExpandedException extends Exception
{
    public function __construct(string $property)
    {
        parent::__construct("Property '$property' is not expanded.");
    }
}