<?php
namespace Rebel\BCApi2\Exception;

use Rebel\BCApi2\Exception;
use GuzzleHttp\Psr7;

class InvalidResponseException extends Exception
{
    public function __construct(Psr7\Response $response)
    {
        $data = json_decode($response->getBody(), true);
        isset($data['error'])
            ? parent::__construct($data['error']['message'], $response->getStatusCode())
            : parent::__construct($response->getBody(), $response->getStatusCode());
    }
}