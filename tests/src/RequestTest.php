<?php
namespace Rebel\Test\BCApi2;

use PHPUnit\Framework\TestCase;
use Rebel\BCApi2\Request;

class RequestTest extends TestCase
{
    public function testBuildUri(): void
    {
        $request = new Request('/companies');
        $this->assertEquals('/companies', $request->buildUri());

        $request = new Request('/salesInvoices');
        $this->assertEquals('/salesInvoices', $request->buildUri());

        $request = new Request('/salesInvoices', '28091159-8974-ed11-9989-6045bd169deb');
        $this->assertEquals('/salesInvoices(28091159-8974-ed11-9989-6045bd169deb)', $request->buildUri());
    }
}