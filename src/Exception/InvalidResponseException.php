<?php
namespace Rebel\BCApi2\Exception;

use Rebel\BCApi2\Exception;
use GuzzleHttp\Psr7;

class InvalidResponseException extends Exception
{
    public function __construct(Psr7\Response $response)
    {
        $data = json_decode($response->getBody(), true);
        parent::__construct(
            isset($data['error']) ? $data['error']['message'] : 'Unknown error.', 
            $response->getStatusCode());
    }
}