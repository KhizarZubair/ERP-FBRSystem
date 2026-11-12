<?php

namespace Tests\Unit;

use App\Enums\FbrEnvironment;
use App\Http\Resources\Auth\CompanyResource;
use App\Models\Company;
use App\Services\FbrErrorCatalog;
use App\Services\FbrScenarioRegistry;
use App\Services\InvoiceCalculator;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class FbrEndUserLawyerVerificationTest extends TestCase
{
    private InvoiceCalculator $calculator;

    private FbrScenarioRegistry $registry;

    private FbrErrorCatalog $errors;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new InvoiceCalculator;
        $this->registry = new FbrScenarioRegistry;
        $this->errors = new FbrErrorCatalog;
    }

    /**
     * FBR Law: Sales Tax Act 1990 Section 3(2)(a) - Third Schedule Goods (FMCG).
     * Tax must strictly be calculated on Retail Price * Quantity * Rate%, NOT on commercial wholesale value.
     * Further tax must be suppressed (0.00).
     */
    public function test_fbr_law_third_schedule_fmcg_calculation_and_further_tax_suppression(): void
    {
        $lineInput = [
            'quantity' => '100.000000',
            'unit_price' => '120.000000', // Wholesale net value: 100 * 120 = 12,000
            'fixed_notified_value_or_retail_price' => '150.000000', // Printed retail price: 100 * 150 = 15,000
            'tax_rate_percent' => '18.000000',
            'sale_type' => '3rd Schedule Goods',
            'further_tax' => '360.000000', // User erroneously entered further tax for unregistered buyer
        ];

        $context = [
            'business_activity' => 'Manufacturer',
            'sector' => 'FMCG',
            'buyer_registration_type' => 'Unregistered',
        ];

        $calculated = $this->calculator->line($lineInput, 0, $context);

        // Tax Base: 100 * 150 = 15,000. Sales tax at 18%: 15,000 * 0.18 = 2,700.00
        $this->assertSame('2700.000000', $calculated['sales_tax_applicable'], 'Sales Tax must be 2700 based on Retail Price');
        $this->assertSame('0.000000', $calculated['further_tax'], 'Further Tax must be suppressed to 0 on 3rd Schedule goods');

        // Net Value: 100 * 120 = 12,000. Total Values: 12,000 + 2,700 = 14,700
        $this->assertSame('12000.000000', $calculated['value_sales_excluding_st']);
        $this->assertSame('14700.000000', $calculated['total_values']);
    }

    /**
     * FBR Law: Sales Tax Act 1990 Section 3(1A) - Retailers selling to End Consumers.
     * Retailers cannot charge further tax on sales to general consumers.
     */
    public function test_fbr_law_retailer_selling_to_end_consumers_suppresses_further_tax(): void
    {
        $lineInput = [
            'quantity' => '10.000000',
            'unit_price' => '1000.000000',
            'tax_rate_percent' => '18.000000',
            'sale_type' => 'Goods at Standard Rate (default)',
            'further_tax' => '300.000000', // Erroneously entered further tax
        ];

        $context = [
            'business_activity' => 'Retailer',
            'sector' => 'Wholesale / Retails',
            'buyer_registration_type' => 'Unregistered',
        ];

        $calculated = $this->calculator->line($lineInput, 0, $context);

        $this->assertSame('1800.000000', $calculated['sales_tax_applicable']);
        $this->assertSame('0.000000', $calculated['further_tax'], 'Retailers must have 0 further tax');
        $this->assertSame('11800.000000', $calculated['total_values']);
    }

    /**
     * FBR Law: Standard Manufacturer selling to unregistered buyer.
     * Further tax (3%) is legally allowed and preserved.
     */
    public function test_fbr_law_standard_manufacturer_unregistered_buyer_preserves_further_tax(): void
    {
        $lineInput = [
            'quantity' => '5.000000',
            'unit_price' => '2000.000000', // Net = 10,000
            'tax_rate_percent' => '18.000000', // ST = 1,800
            'sale_type' => 'Goods at Standard Rate (default)',
            'further_tax' => '300.000000', // 3% Further Tax
        ];

        $context = [
            'business_activity' => 'Manufacturer',
            'sector' => 'All Other Sectors',
            'buyer_registration_type' => 'Unregistered',
        ];

        $calculated = $this->calculator->line($lineInput, 0, $context);

        $this->assertSame('1800.000000', $calculated['sales_tax_applicable']);
        $this->assertSame('300.000000', $calculated['further_tax']);
        $this->assertSame('12100.000000', $calculated['total_values']);
    }

    /**
     * FBR DI Specification Section 10: Auto-Resolution of Scenarios across Sectors.
     */
    public function test_fbr_specification_section_10_sector_auto_resolutions(): void
    {
        // 1. Steel Sector
        $steelScenarios = $this->registry->scenariosForSectorAndActivity('Manufacturer', 'Steel');
        $this->assertContains('SN003', $steelScenarios, 'Steel must contain Melted Steel (SN003)');
        $this->assertContains('SN004', $steelScenarios, 'Steel must contain Ship Breaking (SN004)');
        $this->assertContains('SN011', $steelScenarios, 'Steel must contain Toll Manufacturing (SN011)');

        // 2. FMCG Sector
        $fmcgScenarios = $this->registry->scenariosForSectorAndActivity('Manufacturer', 'FMCG');
        $this->assertContains('SN008', $fmcgScenarios, 'FMCG must contain Third Schedule (SN008)');

        // 3. Textile Sector
        $textileScenarios = $this->registry->scenariosForSectorAndActivity('Manufacturer', 'Textile');
        $this->assertContains('SN009', $textileScenarios, 'Textile must contain Cotton Spinners (SN009)');

        // 4. Retailer
        $retailerScenarios = $this->registry->scenariosForSectorAndActivity('Retailer', 'Wholesale / Retails');
        $this->assertContains('SN026', $retailerScenarios, 'Retailer must contain SN026');
        $this->assertContains('SN027', $retailerScenarios, 'Retailer must contain SN027');
        $this->assertContains('SN028', $retailerScenarios, 'Retailer must contain SN028');

        // 5. Service Provider
        $servicesScenarios = $this->registry->scenariosForSectorAndActivity('Service Provider', 'Services');
        $this->assertContains('SN018', $servicesScenarios);
        $this->assertContains('SN019', $servicesScenarios);

        // 6. Exporter
        $exporterScenarios = $this->registry->scenariosForSectorAndActivity('Exporter', 'All Other Sectors');
        $this->assertContains('SN001', $exporterScenarios);
        $this->assertContains('SN007', $exporterScenarios);
    }

    /**
     * FBR DI Specification Section 4: Payload Construction for all 28 Scenarios.
     */
    public function test_fbr_specification_section_4_payload_structure(): void
    {
        $company = new Company([
            'name' => 'FBR Senior Test Co',
            'ntn_cnic' => '7654321',
            'province' => 'Sindh',
            'address' => 'Clifton, Karachi',
            'business_activity' => 'Manufacturer',
            'sector' => 'FMCG',
            'fbr_environment' => FbrEnvironment::Sandbox,
        ]);

        $payload = $this->registry->buildCertificationPayload($company, 'SN008');

        // Check Header fields required by FBR DI API Section 4
        $this->assertSame('Sale Invoice', $payload['invoiceType']);
        $this->assertNotEmpty($payload['invoiceDate']);
        $this->assertSame('7654321', $payload['sellerNTNCNIC']);
        $this->assertSame('FBR Senior Test Co', $payload['sellerBusinessName']);
        $this->assertSame('Sindh', $payload['sellerProvince']);
        $this->assertSame('Clifton, Karachi', $payload['sellerAddress']);
        $this->assertSame('Registered', $payload['buyerRegistrationType']);
        $this->assertSame('SN008', $payload['scenarioId']);

        // Check Line item fields required by FBR DI API Section 4
        $this->assertCount(1, $payload['items']);
        $item = $payload['items'][0];
        $this->assertSame('3rd Schedule Goods', $item['saleType']);
        $this->assertGreaterThan(0, $item['fixedNotifiedValueOrRetailPrice'], 'SN008 must provide retail price');
        $this->assertArrayHasKey('hsCode', $item);
        $this->assertArrayHasKey('productDescription', $item);
        $this->assertArrayHasKey('rate', $item);
        $this->assertArrayHasKey('uoM', $item);
        $this->assertArrayHasKey('quantity', $item);
        $this->assertArrayHasKey('totalValues', $item);
        $this->assertArrayHasKey('valueSalesExcludingST', $item);
        $this->assertArrayHasKey('salesTaxApplicable', $item);
        $this->assertArrayHasKey('salesTaxWithheldAtSource', $item);
        $this->assertArrayHasKey('extraTax', $item);
        $this->assertArrayHasKey('furtherTax', $item);
        $this->assertArrayHasKey('sroScheduleNo', $item);
        $this->assertArrayHasKey('sroItemSerialNo', $item);
        $this->assertArrayHasKey('fedPayable', $item);
        $this->assertArrayHasKey('discount', $item);
    }

    /**
     * FBR Official Error Catalog Section 7 & 8: Key Error Codes Lookup.
     */
    public function test_fbr_official_error_catalog_lookup(): void
    {
        // 0102: Third Schedule calculation mismatch
        $err0102 = $this->errors->entry('0102');
        $this->assertNotNull($err0102);
        $this->assertSame('CALCULATION', $err0102['category']);
        $this->assertStringContainsString('third schedule', strtolower($err0102['guidance']));

        // 0077: SRO Schedule missing
        $err0077 = $this->errors->entry('0077');
        $this->assertNotNull($err0077);
        $this->assertSame('SRO_SCHEDULE', $err0077['category']);

        // 0036: Reference invoice number missing
        $err0036 = $this->errors->entry('0036');
        $this->assertNotNull($err0036);
        $this->assertSame('INVOICE_HEADER', $err0036['category']);

        // 0053: Buyer Registration Type mismatch
        $err0053 = $this->errors->entry('0053');
        $this->assertNotNull($err0053);
        $this->assertSame('BUYER', $err0053['category']);
    }

    /**
     * End-User Company Resource: Dynamic Auto-Resolution in UI/API payloads.
     */
    public function test_company_resource_automatically_resolves_sector_scenarios_for_ui(): void
    {
        $company = new Company([
            'name' => 'Textile Mill Pakistan',
            'ntn_cnic' => '1234567',
            'province' => 'Punjab',
            'address' => 'Faisalabad Industrial Area',
            'business_activity' => 'Manufacturer',
            'sector' => 'Textile',
            'allowed_sandbox_scenarios' => null, // Not configured manually
            'fbr_environment' => FbrEnvironment::Sandbox,
        ]);

        $resolved = $this->registry->allowedCodesForCompany($company);
        $this->assertNotEmpty($resolved);
        $this->assertContains('SN009', $resolved, 'Textile mill must automatically resolve cotton supply SN009');

        $company->setRelation('pivot', (object) ['role' => 'owner']);
        $resourceArray = (new CompanyResource($company))->toArray(Request::create('/'));
        $this->assertContains('SN009', $resourceArray['allowed_sandbox_scenarios']);
        $this->assertSame('Manufacturer', $resourceArray['business_activity']);
        $this->assertSame('Textile', $resourceArray['sector']);
    }
}
