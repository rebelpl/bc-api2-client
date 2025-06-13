<?php
namespace Rebel\Test\BCApi2\Request;

use PHPUnit\Framework\TestCase;
use Rebel\BCApi2\Entity;
use Rebel\BCApi2\Request\Factory;

class FactoryTest extends TestCase
{
    public function testCreate()
    {
        $data = [
            'name' => 'Test Entity',
        ];

        $request = Factory::createEntity('/test', null, $data);
        $this->assertEquals('POST', $request->getMethod());
        $this->assertEquals(json_encode($data), $request->getBody()->getContents());
        $this->assertEmpty($request->getHeaderLine('If-Match'));
    }

    public function testSimpleUpdate()
    {
        $entity = new Entity([
            'id' => '123',
            'name' => 'Test Entity',
            Entity::ODATA_ETAG => 'test-etag',
        ]);

        $data = [
            'name' => 'Another name',
        ];

        $request = Factory::updateEntity('/test', $entity, $data);
        $this->assertEquals('PATCH', $request->getMethod());
        $this->assertEquals('test(123)', (string)$request->getUri());
        $this->assertEquals(json_encode($data), $request->getBody()->getContents());
        $this->assertEquals('test-etag', $request->getHeaderLine('If-Match'));
    }

    public function testExtendedUpdate()
    {
        $entity = new Entity([
            'id' => '123',
            'name' => 'Test Entity',
            Entity::ODATA_ETAG => 'test-etag',
            'lines' => [
                0 => [
                    'id' => '123-001',
                    'quantity' => 1,
                    'item' => 'itemA',
                    Entity::ODATA_ETAG => 'line-etag',
                ]
            ],
            'customer' => [
                'name' => 'John Doe',
            ]
        ], [ 'lines', 'customer' ]);

        $data = [
            'name' => 'Another Name',
            'lines' => [
                0 => [ 'quantity' => 2 ],
                1 => [ 'quantity' => 5, 'item' => 'itemB' ],
            ],
            'customer' => [
                'name' => 'Jane Doe',
            ]
        ];

        $request = Factory::updateEntity('/test', $entity, $data);
        $body = json_decode($request->getBody()->getContents(), true);

        $this->assertEquals('POST', $request->getMethod());
        $this->assertEquals('$batch', $request->getUri());

        $requests = [];
        foreach ($body['requests'] as $request) {
            $id = $request['id'];
            $requests[ $id ] = $request;
        }

        $this->assertEquals('$update', array_key_first($requests));
        $this->assertEquals('$read', array_key_last($requests));

        $this->assertArrayHasKey('lines/0',  $requests);
        $this->assertArrayHasKey('lines/1',  $requests);
        $this->assertArrayHasKey('customer',  $requests);

        $request = $requests['lines/0'];
        $this->assertEquals('PATCH', $request['method']);
        $this->assertEquals('test(123)/lines(123-001)', $request['url']);
        $this->assertEquals('line-etag', $request['headers']['If-Match']);

        $request = $requests['lines/1'];
        $this->assertEquals('POST', $request['method']);
        $this->assertEquals('test(123)/lines', $request['url']);
        $this->assertEquals('itemB', $request['body']['item']);

        $request = $requests['customer'];
        $this->assertEquals('PATCH', $request['method']);
        $this->assertEquals('test(123)/customer', $request['url']);
        $this->assertEquals('Jane Doe', $request['body']['name']);

        $request = $requests['$update'];
        $this->assertEquals('PATCH', $request['method']);
        $this->assertEquals('test(123)', $request['url']);
        $this->assertEquals('Another Name', $request['body']['name']);

        $request = $requests['$read'];
        $this->assertEquals('GET', $request['method']);
        $this->assertEquals('test(123)?%24expand=lines%2Ccustomer', $request['url']);
        $this->assertArrayNotHasKey('body', $request);
    }
}