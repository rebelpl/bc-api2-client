<?php
namespace Rebel\BCApi2\Entity;

use Rebel\BCApi2\Entity;

class Company extends Entity
{
    public function getId(): string
    {
        return $this->get('id');
    }

    public function getName(): string
    {
        return $this->get('name');
    }

    public function getDisplayName(): string
    {
        return $this->get('displayName');
    }

    public function getSystemVersion(): string
    {
        return $this->get('systemVersion');
    }

    public function getSystemCreatedAt(): \DateTime {
        return $this->getAsDateTime('systemCreatedAt', 'datetime');
    }

    public function getSystemCreatedBy(): string
    {
        return $this->get('systemCreatedBy');
    }

    public function getSystemModifiedAt(): \DateTime
    {
        return $this->getAsDateTime('systemModifiedAt', 'datetime');
    }

    public function getSystemModifiedBy(): string
    {
        return $this->get('systemModifiedBy');
    }
}