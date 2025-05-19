<?php
namespace Rebel\Test\BCApi2\Request;

use PHPUnit\Framework\TestCase;
use Rebel\BCApi2\Request\Builder;
use Rebel\BCApi2\Request\Expression;

class BuilderTest extends TestCase
{
    public function testBuildUri(): void
    {
        $request = new Builder('companies');
        $this->assertEquals('companies', $request->buildUri());

        $request = new Builder('salesInvoices');
        $this->assertEquals('salesInvoices', $request->buildUri());

        $request = new Builder('salesInvoices', '28091159-8974-ed11-9989-6045bd169deb');
        $this->assertEquals('salesInvoices(28091159-8974-ed11-9989-6045bd169deb)', $request->buildUri());

        $request = (new Builder('testEntity'))->get('TEST-123');
        $this->assertEquals("testEntity(TEST-123)", (string)$request);

        $request = (new Builder('items'))
            ->select([ 'number', 'displayName' ])
            ->top(5)
            ->skip(3)
            ->count()
            ->where([
                'type' => 'Inventory',
                new Expression('lastModifiedDateTime', '>', new \DateTime('2021-01-15')),
            ])
            ->orderBy('number', 'desc');

        $expected = 'items'
            .'?%24select=number%2CdisplayName'
            .'&%24top=5'
            .'&%24skip=3'
            .'&%24count=true'
            .'&%24filter='
            .'type%20eq%20%27Inventory%27'
            .'%20and%20'
            .'lastModifiedDateTime%20gt%202021-01-15T00%3A00%3A00.000Z'
            .'&%24orderby=number%20desc';
        $this->assertEquals($expected, (string)$request);
    }
}