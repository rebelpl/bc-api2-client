<?php
namespace Rebel\Test\BCApi2\Request;

use PHPUnit\Framework\TestCase;
use Rebel\BCApi2\Request\UriBuilder;
use Rebel\BCApi2\Request\Expression;

class UriBuilderTest extends TestCase
{
    public function testBuildUri(): void
    {
        $uri = new UriBuilder('companies');
        $this->assertEquals('companies', $uri->buildUri());

        $uri = new UriBuilder('salesInvoices');
        $this->assertEquals('salesInvoices', $uri->buildUri());

        $uri = new UriBuilder('salesInvoices', '28091159-8974-ed11-9989-6045bd169deb');
        $this->assertEquals('salesInvoices(28091159-8974-ed11-9989-6045bd169deb)', $uri->buildUri());

        $uri = new UriBuilder('testEntity')->get('TEST-123');
        $this->assertEquals("testEntity('TEST-123')", (string)$uri);

        $uri = new UriBuilder('items')
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
        $this->assertEquals($expected, urldecode($uri));
    }

    public function testExpandWithStringArgument(): void
    {
        $uri = new UriBuilder('items')
            ->expand('references,availability');
        $this->assertEquals('items?$expand=references,availability', urldecode($uri));
    }

    public function testExpandWithSimpleArguments(): void
    {
        $uri = new UriBuilder('items')
            ->expand([ 'references', 'availability' ]);
        $this->assertEquals('items?$expand=references,availability', urldecode($uri));
    }

    public function testExpandWithFilterArguments(): void
    {
        $uri = new UriBuilder('items')
            ->expand([ 'availability' => [ Expression::greaterThan('dateFilter', '2021-01-15') ] ]);
        $this->assertEquals('items?$expand=availability($filter=dateFilter gt \'2021-01-15\')', urldecode($uri));
    }

    public function testExpandWithMixedArguments(): void
    {
        $uri = new UriBuilder('items')
            ->expand([ 'references', 'availability' => [ Expression::greaterThan('dateFilter', '2021-01-15') ] ]);
        $this->assertEquals('items?$expand=references,availability($filter=dateFilter gt \'2021-01-15\')', urldecode($uri));
    }
}