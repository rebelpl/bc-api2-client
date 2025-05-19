<?php
namespace Rebel\BCApi2\Entity;

use Rebel\BCApi2\Request\Expression;

readonly class Company
{
    public function __construct(private array $data)
    {
    }

    public function getId(): string
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

    public function getSystemCreatedAt(): \DateTime
    {
        return \DateTime::createFromFormat(Expression::DATETIME_FORMAT, $this->data['systemCreatedAt']);
    }

    public function getSystemCreatedBy(): string
    {
        return $this->data['systemCreatedBy'];
    }

    public function getSystemModifiedAt(): \DateTime
    {
        return \DateTime::createFromFormat(Expression::DATETIME_FORMAT, $this->data['systemModifiedAt']);
    }

    public function getSystemModifiedBy(): string
    {
        return $this->data['systemModifiedBy'];
    }
}