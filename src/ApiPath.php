<?php
namespace Rebel\BCApi2;

readonly class ApiPath
{
    public function __construct(
        private string $apiPublisher,
        private string $apiGroup,
        private string $apiVersion = 'v1.0')
    {
    }

    public function __toString(): string
    {
        return $this->apiPublisher
            . '/' . $this->apiGroup
            . '/' . $this->apiVersion;
    }
}