<?php
namespace Rebel\Test\BCApi2\Entity;

use PHPUnit\Framework\TestCase;
use Rebel\BCApi2\Entity\ApiRoute;

class ApiRouteTest extends TestCase
{
    public function testGetRoute()
    {
        $apiRoute = new ApiRoute([
            'publisher' => 'mycompany',
            'group' => 'sales',
            'version' => 'v2.1'
        ]);

        $this->assertEquals('mycompany', $apiRoute->getPublisher());
        $this->assertEquals('mycompany/sales/v2.1', $apiRoute->getRoute());
    }
}