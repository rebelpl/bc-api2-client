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
            isset($data['error'])
                ? sprintf('%s: %s', $data['error']['code'], $data['error']['message'])
                : sprintf('%s: %s', $response->getStatusCode(), $response->getReasonPhrase()), 
            $response->getStatusCode());
    }
}