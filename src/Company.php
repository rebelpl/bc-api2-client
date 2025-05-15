<?php
namespace Rebel\BCApi2;

use Carbon\Carbon;

readonly class Company
{
    public function __construct(private array $data)
    {
    }

    public function id(): string
    {
        return $this->data['id'];
    }

    public function getName(): string
    {
        return $this->data['name'];
    }

    public function getDisplayName(): string
    {
        return $this->data['displayName'];
    }

    public function getSystemVersion(): string
    {
        return $this->data['systemVersion'];
    }

    public function getSystemCreatedAt(): Carbon
    {
        return new Carbon($this->data['systemCreatedAt']);
    }

    public function getSystemCreatedBy(): string
    {
        return $this->data['systemCreatedBy'];
    }

    public function getSystemModifiedAt(): Carbon
    {
        return new Carbon($this->data['systemModifiedAt']);
    }

    public function getSystemModifiedBy(): string
    {
        return $this->data['systemModifiedBy'];
    }
}