<?php
namespace Rebel\Test\BCApi2\Request;

use PHPUnit\Framework\TestCase;
use Rebel\BCApi2\Request\UriBuilder;
use Rebel\BCApi2\Request\Expression;

class UriBuilderTest extends TestCase
{
    public function testBuildUri(): void
    {
        $request = new UriBuilder('companies');
        $this->assertEquals('companies', $request->buildUri());

        $request = new UriBuilder('salesInvoices');
        $this->assertEquals('salesInvoices', $request->buildUri());

        $request = new UriBuilder('salesInvoices', '28091159-8974-ed11-9989-6045bd169deb');
        $this->assertEquals('salesInvoices(28091159-8974-ed11-9989-6045bd169deb)', $request->buildUri());

        $request = new UriBuilder('testEntity')->get('TEST-123');
        $this->assertEquals("testEntity('TEST-123')", (string)$request);

        $request = new UriBuilder('items')
            ->select([ 'number', 'displayName' ])
            ->top(5)
            ->skip(3)
            ->count()
            ->where([
                'type' => [ 'Inventory', 'Service' ],
                new Expression('lastModifiedDateTime', '>', new \DateTime('2021-01-15')),
            ])
            ->orderBy('number', 'desc');

        $expected = 'items'
            .'?$select=number,displayName'
            .'&$top=5'
            .'&$skip=3'
            .'&$count=true'
            .'&$filter='
            .'(type eq \'Inventory\' or type eq \'Service\')'
            .' and '
            .'lastModifiedDateTime gt 2021-01-15T00:00:00.000Z'
            .'&$orderby=number desc';
        $this->assertEquals($expected, urldecode($request));
    }
}