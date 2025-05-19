<?php
namespace Rebel\Test\BCApi2\Entity;

use PHPUnit\Framework\TestCase;
use Rebel\BCApi2\Entity\Company;

class CompaniesTest extends TestCase
{
    /** @var Company[]  */
    private array $companies = [];

    protected function setUp(): void
    {
        $data = json_decode(file_get_contents('tests/files/companies.json'), true);
        echo count($data);
        foreach ($data['value'] as $result) {
            $this->companies[] = new Company($result);
        }
    }

    public function testProperties(): void
    {
        foreach ($this->companies as $company) {
            $this->assertContains($company->id, [
                'e802e7d1-5408-f011-9afa-6045bdabb318',
                '3ab5c248-e72b-f011-9a4a-7c1e5275406f',
            ]);

            $this->assertNotEmpty($company->name);
            $this->assertGreaterThan(new \DateTime('2020-01-01'), $company->systemCreatedAt);
        }
    }
}