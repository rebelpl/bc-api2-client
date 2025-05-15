<?php
namespace Rebel\Test\BCApi2;

use PHPUnit\Framework\TestCase;
use Rebel\BCApi2\ApiPath;

class ApiPathTest extends TestCase
{
    public function testToString()
    {
        $apiGroup = new ApiPath(apiPublisher: 'mycompany', apiGroup: 'sales', apiVersion: 'v2.1');
        $this->assertEquals('/mycompany/sales/v2.1', (string)$apiGroup);
    }
}