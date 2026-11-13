<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Services\FbrScenarioRegistry;
use Tests\TestCase;

class FbrScenarioRunnerTest extends TestCase
{
    private FbrScenarioRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new FbrScenarioRegistry;
    }

    public function test_all_twenty_eight_official_scenarios_are_defined_and_supported(): void
    {
        $definitions = $this->registry->definitions();

        $this->assertCount(28, $definitions);
        for ($i = 1; $i <= 28; $i++) {
            $id = sprintf('SN%03d', $i);
            $this->assertArrayHasKey($id, $definitions, "Scenario $id is missing from definitions");
            $this->assertTrue($definitions[$id]['is_supported'], "Scenario $id should be supported");
            $this->assertTrue($this->registry->isSupported($id));
        }
    }

    public function test_auto_mapping_for_fmcg_sector(): void
    {
        $scenarios = $this->registry->scenariosForSectorAndActivity('Manufacturer', 'FMCG');

        $this->assertEqualsCanonicalizing(
            ['SN001', 'SN002', 'SN005', 'SN006', 'SN007', 'SN008', 'SN015', 'SN016', 'SN017', 'SN021', 'SN022', 'SN024'],
            $scenarios
        );
        $this->assertContains('SN008', $scenarios, 'FMCG must include SN008 (Third Schedule)');
    }

    public function test_auto_mapping_for_steel_sector(): void
    {
        $scenarios = $this->registry->scenariosForSectorAndActivity('Manufacturer', 'Steel');

        $this->assertEqualsCanonicalizing(
            ['SN003', 'SN004', 'SN011'],
            $scenarios
        );
        $this->assertContains('SN003', $scenarios, 'Steel must include Melted Steel SN003');
        $this->assertContains('SN004', $scenarios, 'Steel must include Ship Breakers SN004');
        $this->assertContains('SN011', $scenarios, 'Steel must include Toll Manufacturing SN011');
    }

    public function test_auto_mapping_for_textile_sector(): void
    {
        $scenarios = $this->registry->scenariosForSectorAndActivity('Manufacturer', 'Textile');

        $this->assertEqualsCanonicalizing(
            ['SN001', 'SN002', 'SN005', 'SN006', 'SN007', 'SN009', 'SN015', 'SN016', 'SN017', 'SN021', 'SN022', 'SN024'],
            $scenarios
        );
        $this->assertContains('SN009', $scenarios, 'Textile must include Cotton purchase SN009');
    }

    public function test_auto_mapping_for_retailer_activity(): void
    {
        $scenarios = $this->registry->scenariosForSectorAndActivity('Retailer', 'Wholesale / Retails');

        $this->assertEqualsCanonicalizing(
            ['SN026', 'SN027', 'SN028', 'SN008'],
            $scenarios
        );
        $this->assertContains('SN026', $scenarios);
        $this->assertContains('SN027', $scenarios);
        $this->assertContains('SN028', $scenarios);
    }

    public function test_auto_mapping_for_services_and_telecom(): void
    {
        $scenarios = $this->registry->scenariosForSectorAndActivity('Service Provider', 'Services');

        $this->assertEqualsCanonicalizing(
            ['SN018', 'SN019'],
            $scenarios
        );
    }

    public function test_auto_mapping_for_exporter(): void
    {
        $scenarios = $this->registry->scenariosForSectorAndActivity('Exporter', 'All Other Sectors');

        $this->assertEqualsCanonicalizing(
            ['SN001', 'SN002', 'SN005', 'SN006', 'SN007', 'SN015', 'SN016', 'SN017', 'SN021', 'SN022', 'SN024'],
            $scenarios
        );
        $this->assertContains('SN007', $scenarios, 'Exporter must include Zero-rated SN007');
    }

    public function test_for_company_auto_resolves_when_allowed_scenarios_empty(): void
    {
        $company = new Company([
            'name' => 'FMCG Foods Ltd',
            'ntn_cnic' => '1234567',
            'province' => 'Punjab',
            'address' => 'Industrial Estate Lahore',
            'business_activity' => 'Manufacturer',
            'sector' => 'FMCG',
            'allowed_sandbox_scenarios' => [],
        ]);

        $resolved = $this->registry->forCompany($company);

        $this->assertNotEmpty($resolved);
        $ids = array_column($resolved, 'id');
        $this->assertContains('SN008', $ids);
        $this->assertContains('SN001', $ids);
        $this->assertContains('SN002', $ids);
    }

    public function test_build_certification_payload_creates_valid_payload_for_third_schedule_sn008(): void
    {
        $company = new Company([
            'name' => 'FMCG Foods Ltd',
            'ntn_cnic' => '1234567',
            'province' => 'Sindh',
            'address' => 'Karachi',
        ]);

        $payload = $this->registry->buildCertificationPayload($company, 'SN008');

        $this->assertSame('SN008', $payload['scenarioId']);
        $this->assertSame('1234567', $payload['sellerNTNCNIC']);
        $this->assertSame('3rd Schedule Goods', $payload['items'][0]['saleType']);
        $this->assertEquals(1500.0, $payload['items'][0]['fixedNotifiedValueOrRetailPrice']);
        // 1500 * 18% = 270.00
        $this->assertEquals(270.0, $payload['items'][0]['salesTaxApplicable']);
        // For Third Schedule goods the taxable base is the printed retail price.
        $this->assertEquals(1770.0, $payload['items'][0]['totalValues']);
        $this->assertEquals(1500.0, $payload['items'][0]['valueSalesExcludingST']);
        $this->assertEquals(0.0, $payload['items'][0]['furtherTax']);
    }

    public function test_build_certification_payload_creates_valid_payload_for_exempt_sn006(): void
    {
        $company = new Company([
            'name' => 'Pharma Corp',
            'ntn_cnic' => '7654321',
            'province' => 'Punjab',
            'address' => 'Rawalpindi',
        ]);

        $payload = $this->registry->buildCertificationPayload($company, 'SN006');

        $this->assertSame('SN006', $payload['scenarioId']);
        $this->assertSame('Exempt', $payload['items'][0]['rate']);
        $this->assertSame('Exempt Goods', $payload['items'][0]['saleType']);
        $this->assertSame('6th Schd Table I', $payload['items'][0]['sroScheduleNo']);
        $this->assertSame('100', $payload['items'][0]['sroItemSerialNo']);
        $this->assertEquals(0.0, $payload['items'][0]['salesTaxApplicable']);
    }

    public function test_build_certification_payload_creates_valid_payload_for_retailer_sn026(): void
    {
        $company = new Company([
            'name' => 'Supermarket Retail',
            'ntn_cnic' => '9876543',
            'province' => 'Islamabad',
            'address' => 'Blue Area Islamabad',
        ]);

        $payload = $this->registry->buildCertificationPayload($company, 'SN026');

        $this->assertSame('SN026', $payload['scenarioId']);
        $this->assertSame('Unregistered', $payload['buyerRegistrationType']);
        $this->assertSame('', $payload['buyerNTNCNIC']);
        $this->assertEquals(0.0, $payload['items'][0]['furtherTax']);
        $this->assertEquals(180.0, $payload['items'][0]['salesTaxApplicable']);
    }
}
