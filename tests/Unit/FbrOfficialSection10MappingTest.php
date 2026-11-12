<?php

namespace Tests\Unit;

use App\Services\FbrScenarioRegistry;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FbrOfficialSection10MappingTest extends TestCase
{
    #[Test]
    public function all_activity_and_sector_pairs_match_the_section_10_rules(): void
    {
        $registry = new FbrScenarioRegistry();
        foreach ($registry->definitions() as $id => $definition) {
            $this->assertSame(config('fbr.supported_scenarios.'.$id.'.sale_type'), $definition['sale_type'], $id.' sale type must match FBR Section 9.');
        }
        $base = ['SN001', 'SN002', 'SN005', 'SN006', 'SN007', 'SN015', 'SN016', 'SN017', 'SN021', 'SN022', 'SN024'];
        $retail = ['SN026', 'SN027', 'SN028', 'SN008'];
        $sectors = [
            'Potassium Chlorate', 'Mobile', 'Pharmaceuticals', 'Automobile',
            'Gas Distribution', 'Petroleum', 'Textile', 'Steel',
            'Cement or Concrete Blocks', 'Wholesale / Retails', 'CNG Stations',
            'Services', 'Electricity Distribution', 'Telecom', 'FMCG', 'All Other Sectors',
        ];
        $activities = ['Manufacturer', 'Importer', 'Distributor', 'Wholesaler', 'Retailer', 'Exporter', 'Service Provider', 'Other'];

        foreach ($activities as $activity) {
            foreach ($sectors as $sector) {
                $sectorOnly = match ($sector) {
                    'Steel' => ['SN003', 'SN004', 'SN011'],
                    'Potassium Chlorate' => ['SN022'],
                    'Mobile' => ['SN015'],
                    'Cement or Concrete Blocks' => ['SN021'],
                    'FMCG' => ['SN008'],
                    'Textile' => ['SN009'],
                    'Telecom' => ['SN010'],
                    'Petroleum' => ['SN012'],
                    'Electricity Distribution' => ['SN013'],
                    'Gas Distribution' => ['SN014'],
                    'Services' => ['SN018', 'SN019'],
                    'Automobile' => ['SN020'],
                    'CNG Stations' => ['SN023'],
                    'Pharmaceuticals' => ['SN025'],
                    'Wholesale / Retails' => $retail,
                    default => [],
                };

                $expected = match ($activity) {
                    'Manufacturer' => match ($sector) {
                        'Steel' => ['SN003', 'SN004', 'SN011'],
                        'Potassium Chlorate', 'Mobile', 'Cement or Concrete Blocks' => $base,
                        'FMCG' => [...$base, 'SN008'],
                        'Textile' => [...$base, 'SN009'],
                        'Telecom' => [...$base, 'SN010'],
                        'Petroleum' => [...$base, 'SN012'],
                        'Electricity Distribution' => [...$base, 'SN013'],
                        'Gas Distribution' => [...$base, 'SN014'],
                        'Services' => [...$base, 'SN018', 'SN019'],
                        'Automobile' => [...$base, 'SN020'],
                        'CNG Stations' => [...$base, 'SN023'],
                        'Pharmaceuticals' => $base,
                        'Wholesale / Retails' => [...$base, ...$retail],
                        default => $base,
                    },
                    'Importer' => array_values(array_unique([...$base, ...$sectorOnly])),
                    'Distributor', 'Wholesaler' => match ($sector) {
                        'Steel' => ['SN003', 'SN004', 'SN011', ...$retail],
                        'All Other Sectors' => [...$base, ...$retail],
                        'Wholesale / Retails' => ['SN001', 'SN002', ...$retail],
                        default => array_values(array_unique([...$sectorOnly, ...$retail])),
                    },
                    'Exporter' => $sector === 'Steel' ? [...$base, 'SN003', 'SN004', 'SN011']
                        : ($sector === 'All Other Sectors' ? $base : array_values(array_unique([...$base, ...$sectorOnly]))),
                    'Retailer' => match ($sector) {
                        'Steel' => ['SN003', 'SN004', 'SN011'],
                        'All Other Sectors' => [...$base, ...$retail],
                        'Pharmaceuticals' => ['SN025', ...$retail],
                        default => array_values(array_unique([...$sectorOnly, ...$retail])),
                    },
                    'Service Provider' => match ($sector) {
                        'All Other Sectors' => [...$base, 'SN018', 'SN019'],
                        'Wholesale / Retails' => [...$retail, 'SN018', 'SN019'],
                        default => array_values(array_unique([...$sectorOnly, 'SN018', 'SN019'])),
                    },
                    'Other' => $sector === 'Steel' ? [...$base, 'SN003', 'SN004', 'SN011']
                        : ($sector === 'All Other Sectors' ? $base : array_values(array_unique([...$base, ...$sectorOnly]))),
                };

                $actual = $registry->scenariosForSectorAndActivity($activity, $sector);
                sort($expected);
                sort($actual);
                $this->assertSame($expected, $actual, $activity.' / '.$sector.' must match FBR Section 10 (PDF pages 47–51).');
            }
        }
    }

    #[Test]
    public function multiple_activities_and_sectors_union_their_official_scenarios(): void
    {
        $registry = new FbrScenarioRegistry();
        $actual = $registry->scenariosForSectorAndActivity(
            ['Manufacturer', 'Retailer'],
            ['Steel', 'Textile']
        );

        $this->assertContains('SN003', $actual);
        $this->assertContains('SN004', $actual);
        $this->assertContains('SN011', $actual);
        $this->assertContains('SN009', $actual);
        $this->assertContains('SN026', $actual);
        $this->assertSame(count($actual), count(array_unique($actual)));
    }


    #[Test]
    public function retailer_end_consumer_scenarios_require_sales_tax_profile_retailer_registration(): void
    {
        $registry = new FbrScenarioRegistry();
        $company = new \App\Models\Company([
            'business_activity' => ['Retailer'],
            'sector' => ['Wholesale / Retails'],
            'is_sales_tax_profile_retailer' => false,
        ]);

        $withoutRetailerRegistration = $registry->allowedCodesForCompany($company);
        $this->assertContains('SN008', $withoutRetailerRegistration);
        $this->assertNotContains('SN026', $withoutRetailerRegistration);
        $this->assertNotContains('SN027', $withoutRetailerRegistration);
        $this->assertNotContains('SN028', $withoutRetailerRegistration);

        $company->is_sales_tax_profile_retailer = true;
        $withRetailerRegistration = $registry->allowedCodesForCompany($company);
        $this->assertContains('SN026', $withRetailerRegistration);
        $this->assertContains('SN027', $withRetailerRegistration);
        $this->assertContains('SN028', $withRetailerRegistration);
    }

    #[Test]
    public function non_retailer_activities_cannot_use_retailer_end_consumer_scenarios_even_when_sector_mapping_includes_them(): void
    {
        $company = new \App\Models\Company([
            'business_activity' => ['Distributor'],
            'sector' => ['FMCG'],
            'is_sales_tax_profile_retailer' => false,
        ]);

        $allowed = (new FbrScenarioRegistry())->allowedCodesForCompany($company);
        $this->assertContains('SN008', $allowed);
        $this->assertNotContains('SN026', $allowed);
        $this->assertNotContains('SN027', $allowed);
        $this->assertNotContains('SN028', $allowed);
    }

    #[Test]
    public function company_manual_scenario_list_cannot_override_official_automatic_mapping(): void
    {
        $company = new \App\Models\Company([
            'business_activity' => ['Manufacturer'],
            'sector' => ['Steel'],
            'allowed_sandbox_scenarios' => ['SN001', 'SN099'],
        ]);

        $this->assertSame(['SN003', 'SN004', 'SN011'], (new FbrScenarioRegistry())->allowedCodesForCompany($company));
    }
}
