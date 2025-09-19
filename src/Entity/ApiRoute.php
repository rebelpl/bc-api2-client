<?php
namespace Rebel\BCApi2\Entity;

class ApiRoute
{
    private $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function __toString(): string
    {
        return $this->getRoute();
    }
    
    public function getPublisher(): string
    {
        return $this->data['publisher'];
    }

    public function getGroup(): string
    {
        return $this->data['group'];
    }

    public function getVersion(): string
    {
        return $this->data['version'];
    }

    public function getRoute(): string
    {
        return $this->data['route']
            ?? ($this->getPublisher() . '/' . $this->getGroup() . '/' . $this->getVersion());
    }
}