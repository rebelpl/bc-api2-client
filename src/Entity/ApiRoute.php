<?php
namespace Rebel\BCApi2\Entity;

class ApiRoute
{
    public string $publisher {
        get => $this->data['publisher'];
    }

    public string $group {
        get => $this->data['group'];
    }

    public string $version {
        get => $this->data['version'];
    }

    public string $route {
        get => $this->data['route']
            ?? ($this->publisher .'/' . $this->group . '/' . $this->version);
    }

    public function __construct(private readonly array $data)
    {
    }

    public function __toString(): string
    {
        return $this->route;
    }
}