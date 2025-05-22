<?php
namespace Rebel\Test\BCApi2\Request;

use PHPUnit\Framework\TestCase;
use Rebel\BCApi2\Request;
use Rebel\BCApi2\Request\Batch;

class BatchTest extends TestCase
{
    public function testToArray()
    {
        $batch = new Batch();
        $batch->add(new Request('PATCH',
            'companies(test)/salesOrders(001)/salesOrderLines(5e6a1aff)',
            body: json_encode([
                'quantity' => 10,
            ]), etag: 'test-etag'
        ));

        $batch->add(new Request('POST',
            'companies(test)/salesOrders(001)/salesOrderLines',
            body: json_encode([
                'documentId' => "a417d159-d936-f011-9a4a-7c1e5271c536",
                'itemId' => "b8c285a5-f12b-f011-9a4a-7c1e5275406f",
                'quantity' => 5
            ])
        ));

        $array = $batch->toArray();
        foreach ($array as $key => $value) {
            $this->assertEquals($key, $value['id']);
        }
    }
}