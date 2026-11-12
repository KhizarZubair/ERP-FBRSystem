<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Validation\ValidationException;

class FbrScenarioRegistry
{
    /**
     * Complete official catalogue of FBR digital invoicing sandbox scenarios (SN001 to SN028)
     * as defined in FBR Official Documentation (Section 9 & Section 10).
     *
     * @return array<string, array{id: string, title: string, description: string, sale_type: string, buyer_type: string, rate: string, further_tax: float, retail_price?: float, fed_payable?: float, extra_tax?: float, sro_schedule_no?: string, sro_item_serial_no?: string, hs_code?: string, uom?: string, is_supported: bool}>
     */
    public function definitions(): array
    {
        return [
            'SN001' => [
                'id' => 'SN001',
                'title' => 'Goods at standard rate to registered buyers',
                'description' => 'Goods at standard 18% rate to registered buyers (default)',
                'sale_type' => 'Goods at Standard Rate (default)',
                'buyer_type' => 'Registered',
                'rate' => '18%',
                'further_tax' => 0.0,
                'is_supported' => true,
            ],
            'SN002' => [
                'id' => 'SN002',
                'title' => 'Goods at standard rate to unregistered buyers',
                'description' => 'Goods at standard 18% rate to unregistered buyers (+Further Tax)',
                'sale_type' => 'Goods at Standard Rate (default)',
                'buyer_type' => 'Unregistered',
                'rate' => '18%',
                'further_tax' => 120.0,
                'is_supported' => true,
            ],
            'SN003' => [
                'id' => 'SN003',
                'title' => 'Sale of Steel (Melted and Re-Rolled)',
                'description' => 'Steel sector supply by melted and re-rolled manufacturers',
                'sale_type' => 'Steel Melting and re-rolling',
                'buyer_type' => 'Registered',
                'rate' => '18%',
                'hs_code' => '7206.1000',
                'uom' => 'Metric Ton',
                'further_tax' => 0.0,
                'is_supported' => true,
            ],
            'SN004' => [
                'id' => 'SN004',
                'title' => 'Sale by Ship Breakers',
                'description' => 'Supply by ship breaking industry under special sector rules',
                'sale_type' => 'Ship breaking',
                'buyer_type' => 'Registered',
                'rate' => '18%',
                'hs_code' => '8908.0000',
                'uom' => 'Metric Ton',
                'further_tax' => 0.0,
                'is_supported' => true,
            ],
            'SN005' => [
                'id' => 'SN005',
                'title' => 'Goods at reduced rate',
                'description' => 'Supplies chargeable at concessionary rate under 8th Schedule / SRO',
                'sale_type' => 'Goods at Reduced Rate',
                'buyer_type' => 'Registered',
                'rate' => '1%',
                'sro_schedule_no' => 'Eighth Schedule, Table-1',
                'sro_item_serial_no' => 'Item 1',
                'further_tax' => 0.0,
                'is_supported' => true,
            ],
            'SN006' => [
                'id' => 'SN006',
                'title' => 'Exempt goods sale',
                'description' => 'Supplies exempt from sales tax under 6th Schedule to Sales Tax Act',
                'sale_type' => 'Exempt Goods',
                'buyer_type' => 'Registered',
                'rate' => '0%',
                'sro_schedule_no' => 'Sixth Schedule, Table-1',
                'sro_item_serial_no' => 'Item 1',
                'further_tax' => 0.0,
                'is_supported' => true,
            ],
            'SN007' => [
                'id' => 'SN007',
                'title' => 'Zero rated goods sale',
                'description' => 'Zero-rated supplies (Exports under Section 4 or 5th Schedule)',
                'sale_type' => 'Goods at zero-rate',
                'buyer_type' => 'Registered',
                'rate' => '0%',
                'further_tax' => 0.0,
                'is_supported' => true,
            ],
            'SN008' => [
                'id' => 'SN008',
                'title' => '3rd Schedule goods (Retail Price)',
                'description' => 'FMCG & Third Schedule goods taxed on printed Retail Price (Sec 3(2)(a))',
                'sale_type' => '3rd Schedule Goods',
                'buyer_type' => 'Registered',
                'rate' => '18%',
                'retail_price' => 1500.0,
                'further_tax' => 0.0,
                'is_supported' => true,
            ],
            'SN009' => [
                'id' => 'SN009',
                'title' => 'Cotton Spinners purchase from Cotton Ginners',
                'description' => 'Textile sector cotton supply from ginning factories',
                'sale_type' => 'Cotton Ginners',
                'buyer_type' => 'Registered',
                'rate' => '18%',
                'hs_code' => '5201.0019',
                'uom' => 'KG',
                'further_tax' => 0.0,
                'is_supported' => true,
            ],
            'SN010' => [
                'id' => 'SN010',
                'title' => 'Telecom services rendered',
                'description' => 'Telecommunication services under Federal / Provincial service tax',
                'sale_type' => 'Telecommunication services',
                'buyer_type' => 'Registered',
                'rate' => '19.5%',
                'hs_code' => '9812.0000',
                'uom' => 'Numbers, pieces, units',
                'further_tax' => 0.0,
                'is_supported' => true,
            ],
            'SN011' => [
                'id' => 'SN011',
                'title' => 'Toll Manufacturing sale by Steel sector',
                'description' => 'Toll manufacturing / conversion services in Steel sector',
                'sale_type' => 'Toll Manufacturing',
                'buyer_type' => 'Registered',
                'rate' => '18%',
                'hs_code' => '7206.1000',
                'uom' => 'Metric Ton',
                'further_tax' => 0.0,
                'is_supported' => true,
            ],
            'SN012' => [
                'id' => 'SN012',
                'title' => 'Sale of Petroleum products',
                'description' => 'Petroleum and downstream fuel product distribution',
                'sale_type' => 'Petroleum Products',
                'buyer_type' => 'Registered',
                'rate' => '18%',
                'hs_code' => '2710.1210',
                'uom' => 'Liters',
                'further_tax' => 0.0,
                'is_supported' => true,
            ],
            'SN013' => [
                'id' => 'SN013',
                'title' => 'Electricity Supply to Retailers',
                'description' => 'Electric power distribution under special sales tax regime',
                'sale_type' => 'Electricity Supply to Retailers',
                'buyer_type' => 'Registered',
                'rate' => '18%',
                'hs_code' => '2716.0000',
                'uom' => 'KWH',
                'further_tax' => 0.0,
                'is_supported' => true,
            ],
            'SN014' => [
                'id' => 'SN014',
                'title' => 'Sale of Gas to CNG stations',
                'description' => 'Natural gas transmission and supply to CNG fuel stations',
                'sale_type' => 'Gas to CNG stations',
                'buyer_type' => 'Registered',
                'rate' => '18%',
                'hs_code' => '2711.2100',
                'uom' => 'MMBTU',
                'further_tax' => 0.0,
                'is_supported' => true,
            ],
            'SN015' => [
                'id' => 'SN015',
                'title' => 'Sale of mobile phones',
                'description' => 'Cellular devices under Ninth Schedule sales tax rates',
                'sale_type' => 'Mobile Phones',
                'buyer_type' => 'Registered',
                'rate' => '18%',
                'hs_code' => '8517.1219',
                'uom' => 'Numbers, pieces, units',
                'further_tax' => 0.0,
                'is_supported' => true,
            ],
            'SN016' => [
                'id' => 'SN016',
                'title' => 'Processing / Conversion of Goods',
                'description' => 'Processing, fabrication or conversion of goods for another entity',
                'sale_type' => 'Processing/ Conversion of Goods',
                'buyer_type' => 'Registered',
                'rate' => '18%',
                'further_tax' => 0.0,
                'is_supported' => true,
            ],
            'SN017' => [
                'id' => 'SN017',
                'title' => 'Sale of Goods where FED is charged in ST mode',
                'description' => 'Federal Excise Duty in Sales Tax mode (Beverages, tobacco, lubricants)',
                'sale_type' => 'Goods (FED in ST Mode)',
                'buyer_type' => 'Registered',
                'rate' => '18%',
                'fed_payable' => 50.0,
                'further_tax' => 0.0,
                'is_supported' => true,
            ],
            'SN018' => [
                'id' => 'SN018',
                'title' => 'Services rendered (FED in ST mode)',
                'description' => 'Taxable services with Federal Excise Duty levied in ST mode',
                'sale_type' => 'Services (FED in ST Mode)',
                'buyer_type' => 'Registered',
                'rate' => '16%',
                'hs_code' => '9801.0000',
                'uom' => 'Numbers, pieces, units',
                'further_tax' => 0.0,
                'is_supported' => true,
            ],
            'SN019' => [
                'id' => 'SN019',
                'title' => 'Provincial Sales Tax on services',
                'description' => 'Services subject to Provincial Revenue Authority sales tax (PRA/SRB/KPRA/BRA)',
                'sale_type' => 'Services',
                'buyer_type' => 'Registered',
                'rate' => '13%',
                'hs_code' => '9801.0000',
                'uom' => 'Numbers, pieces, units',
                'further_tax' => 0.0,
                'is_supported' => true,
            ],
            'SN020' => [
                'id' => 'SN020',
                'title' => 'Electric Vehicles',
                'description' => 'Automotive sector electric vehicles under concessionary tax regime',
                'sale_type' => 'Electric Vehicle',
                'buyer_type' => 'Registered',
                'rate' => '1%',
                'hs_code' => '8703.8090',
                'uom' => 'Numbers, pieces, units',
                'further_tax' => 0.0,
                'is_supported' => true,
            ],
            'SN021' => [
                'id' => 'SN021',
                'title' => 'Cement / Concrete Block',
                'description' => 'Cement and pre-cast concrete block manufacturing sector supply',
                'sale_type' => 'Cement /Concrete Block',
                'buyer_type' => 'Registered',
                'rate' => '18%',
                'hs_code' => '2523.2900',
                'uom' => 'Metric Ton',
                'further_tax' => 0.0,
                'is_supported' => true,
            ],
            'SN022' => [
                'id' => 'SN022',
                'title' => 'Potassium Chlorate',
                'description' => 'Potassium Chlorate chemical supply under fixed statutory duty',
                'sale_type' => 'Potassium Chlorate',
                'buyer_type' => 'Registered',
                'rate' => '18%',
                'hs_code' => '2829.1910',
                'uom' => 'KG',
                'further_tax' => 0.0,
                'is_supported' => true,
            ],
            'SN023' => [
                'id' => 'SN023',
                'title' => 'CNG Sales',
                'description' => 'Compressed Natural Gas dispensing station sales',
                'sale_type' => 'CNG Sales',
                'buyer_type' => 'Registered',
                'rate' => '18%',
                'hs_code' => '2711.2100',
                'uom' => 'KG',
                'further_tax' => 0.0,
                'is_supported' => true,
            ],
            'SN024' => [
                'id' => 'SN024',
                'title' => 'Goods listed in SRO 297(I)/2023',
                'description' => 'Special goods covered under statutory SRO 297(I)/2023',
                'sale_type' => 'Goods as per SRO.297(I)/2023',
                'buyer_type' => 'Registered',
                'rate' => '18%',
                'sro_schedule_no' => 'SRO 297(I)/2023',
                'sro_item_serial_no' => 'Serial 1',
                'further_tax' => 0.0,
                'is_supported' => true,
            ],
            'SN025' => [
                'id' => 'SN025',
                'title' => 'Drugs sold under 8th Schedule',
                'description' => 'Pharmaceutical products & medicines chargeable under Eighth Schedule',
                'sale_type' => 'Non-Adjustable Supplies',
                'buyer_type' => 'Registered',
                'rate' => '1%',
                'hs_code' => '3004.9000',
                'uom' => 'Numbers, pieces, units',
                'sro_schedule_no' => 'Eighth Schedule, Table-1',
                'sro_item_serial_no' => 'Item 1',
                'further_tax' => 0.0,
                'is_supported' => true,
            ],
            'SN026' => [
                'id' => 'SN026',
                'title' => 'Retailers sales to End Consumers (Standard rate)',
                'description' => 'Retail sales to general walk-in consumers at standard 18% (0 Further Tax)',
                'sale_type' => 'Goods at Standard Rate (default)',
                'buyer_type' => 'Unregistered',
                'rate' => '18%',
                'further_tax' => 0.0,
                'is_supported' => true,
            ],
            'SN027' => [
                'id' => 'SN027',
                'title' => 'Retailers sales to End Consumers (3rd Schedule goods)',
                'description' => 'Retail sales of 3rd Schedule goods to end consumers on retail price',
                'sale_type' => '3rd Schedule Goods',
                'buyer_type' => 'Unregistered',
                'rate' => '18%',
                'retail_price' => 1500.0,
                'further_tax' => 0.0,
                'is_supported' => true,
            ],
            'SN028' => [
                'id' => 'SN028',
                'title' => 'Retailers sales to End Consumers (Reduced rate goods)',
                'description' => 'Retail sales of reduced rate items to end consumers (0 Further Tax)',
                'sale_type' => 'Goods at Reduced Rate',
                'buyer_type' => 'Unregistered',
                'rate' => '1%',
                'sro_schedule_no' => 'Eighth Schedule, Table-1',
                'sro_item_serial_no' => 'Item 1',
                'further_tax' => 0.0,
                'is_supported' => true,
            ],
        ];
    }

    /**
     * Resolves the official FBR sandbox testing scenarios mapped to a specific
     * Business Activity (Nature) and Sector according to Section 10 of FBR Official Documentation (Pages 47-51).
     *
     * @return array<int, string>
     */
    public function scenariosForSectorAndActivity(string|array|null $businessActivity, string|array|null $sector): array
    {
        $activities = array_values(array_filter(array_map(
            fn ($value): string => strtolower(trim((string) $value)),
            is_array($businessActivity) ? $businessActivity : [$businessActivity]
        )));
        $sectors = array_values(array_filter(array_map(
            fn ($value): string => strtolower(trim((string) $value)),
            is_array($sector) ? $sector : [$sector]
        )));
        $resolved = [];
        foreach ($activities ?: [''] as $activity) {
            foreach ($sectors ?: [''] as $sec) {
                $resolved = [...$resolved, ...$this->scenariosForSingleSectorAndActivity($activity, $sec)];
            }
        }

        return array_values(array_unique($resolved));
    }

    /** @return array<int, string> */
    private function scenariosForSingleSectorAndActivity(string $activity, string $sec): array
    {

        $base = ['SN001', 'SN002', 'SN005', 'SN006', 'SN007', 'SN015', 'SN016', 'SN017', 'SN021', 'SN022', 'SN024'];
        $retail = ['SN026', 'SN027', 'SN028', 'SN008'];
        $sectorScenario = match (true) {
            str_contains($sec, 'steel') => ['SN003', 'SN004', 'SN011'],
            str_contains($sec, 'potassium chlorate') => ['SN022'],
            str_contains($sec, 'mobile') && ! str_contains($sec, 'automobile') => ['SN015'],
            str_contains($sec, 'cement') || str_contains($sec, 'concrete block') => ['SN021'],
            str_contains($sec, 'fmcg') => ['SN008'],
            str_contains($sec, 'textile') => ['SN009'],
            str_contains($sec, 'telecom') => ['SN010'],
            str_contains($sec, 'petroleum') => ['SN012'],
            str_contains($sec, 'electricity') => ['SN013'],
            str_contains($sec, 'gas') => ['SN014'],
            str_contains($sec, 'service') => ['SN018', 'SN019'],
            str_contains($sec, 'automobile') => ['SN020'],
            str_contains($sec, 'cng') => ['SN023'],
            str_contains($sec, 'pharmaceutical') => ['SN025'],
            str_contains($sec, 'wholesale') || str_contains($sec, 'retail') => ['SN026', 'SN027', 'SN028', 'SN008'],
            default => [],
        };

        // Section 10, pages 47–51. Keep each activity's official row distinct.
        return match ($activity) {
            'manufacturer' => match (true) {
                str_contains($sec, 'steel') => ['SN003', 'SN004', 'SN011'],
                str_contains($sec, 'fmcg') => [...$base, 'SN008'],
                str_contains($sec, 'textile') => [...$base, 'SN009'],
                str_contains($sec, 'telecom') => [...$base, 'SN010'],
                str_contains($sec, 'petroleum') => [...$base, 'SN012'],
                str_contains($sec, 'electricity') => [...$base, 'SN013'],
                str_contains($sec, 'gas') => [...$base, 'SN014'],
                str_contains($sec, 'service') => [...$base, 'SN018', 'SN019'],
                str_contains($sec, 'automobile') => [...$base, 'SN020'],
                str_contains($sec, 'cng') => [...$base, 'SN023'],
                str_contains($sec, 'pharmaceutical') => $base,
                str_contains($sec, 'wholesale') || str_contains($sec, 'retail') => [...$base, 'SN026', 'SN027', 'SN028', 'SN008'],
                default => $base,
            },
            'importer' => array_values(array_unique([...$base, ...$sectorScenario])),
            'distributor', 'wholesaler' => match (true) {
                str_contains($sec, 'steel') => ['SN003', 'SN004', 'SN011', ...$retail],
                str_contains($sec, 'all other') => [...$base, ...$retail],
                str_contains($sec, 'wholesale') || str_contains($sec, 'retail') => ['SN001', 'SN002', ...$retail],
                default => array_values(array_unique([...$sectorScenario, ...$retail])),
            },
            'exporter' => match (true) {
                str_contains($sec, 'steel') => [...$base, 'SN003', 'SN004', 'SN011'],
                str_contains($sec, 'fmcg') => [...$base, 'SN008'],
                str_contains($sec, 'textile') => [...$base, 'SN009'],
                str_contains($sec, 'telecom') => [...$base, 'SN010'],
                str_contains($sec, 'petroleum') => [...$base, 'SN012'],
                str_contains($sec, 'electricity') => [...$base, 'SN013'],
                str_contains($sec, 'gas') => [...$base, 'SN014'],
                str_contains($sec, 'service') => [...$base, 'SN018', 'SN019'],
                str_contains($sec, 'automobile') => [...$base, 'SN020'],
                str_contains($sec, 'cng') => [...$base, 'SN023'],
                str_contains($sec, 'pharmaceutical') => [...$base, 'SN025'],
                str_contains($sec, 'wholesale') || str_contains($sec, 'retail') => [...$base, 'SN026', 'SN027', 'SN028', 'SN008'],
                default => $base,
            },
            'retailer' => match (true) {
                str_contains($sec, 'steel') => ['SN003', 'SN004', 'SN011'],
                str_contains($sec, 'all other') => [...$base, ...$retail],
                str_contains($sec, 'pharmaceutical') => ['SN025', ...$retail],
                default => array_values(array_unique([...$sectorScenario, ...$retail])),
            },
            'service provider' => match (true) {
                str_contains($sec, 'all other') => [...$base, 'SN018', 'SN019'],
                str_contains($sec, 'wholesale') || str_contains($sec, 'retail') => [...$retail, 'SN018', 'SN019'],
                default => array_values(array_unique([...$sectorScenario, 'SN018', 'SN019'])),
            },
            'other' => match (true) {
                str_contains($sec, 'steel') => [...$base, 'SN003', 'SN004', 'SN011'],
                str_contains($sec, 'fmcg') => [...$base, 'SN008'],
                str_contains($sec, 'textile') => [...$base, 'SN009'],
                str_contains($sec, 'telecom') => [...$base, 'SN010'],
                str_contains($sec, 'petroleum') => [...$base, 'SN012'],
                str_contains($sec, 'electricity') => [...$base, 'SN013'],
                str_contains($sec, 'gas') => [...$base, 'SN014'],
                str_contains($sec, 'service') => [...$base, 'SN018', 'SN019'],
                str_contains($sec, 'automobile') => [...$base, 'SN020'],
                str_contains($sec, 'cng') => [...$base, 'SN023'],
                str_contains($sec, 'pharmaceutical') => [...$base, 'SN025'],
                str_contains($sec, 'wholesale') || str_contains($sec, 'retail') => [...$base, 'SN026', 'SN027', 'SN028', 'SN008'],
                default => $base,
            },
            default => [],
        };
    }

    /**
     * Returns IDs of scenarios that have verified fiscal rules and can be executed.
     *
     * @return array<int, string>
     */
    public function supportedIds(): array
    {
        return array_values(array_keys(array_filter(
            $this->definitions(),
            fn (array $scenario): bool => $scenario['is_supported'] === true
        )));
    }

    public function isSupported(string $scenarioId): bool
    {
        $scenario = $this->definitions()[$scenarioId] ?? null;

        return $scenario !== null && $scenario['is_supported'] === true;
    }

    /**
     * Returns the array of allowed sandbox scenario IDs for the company.
     * If the company has custom configured scenarios, returns those;
     * otherwise dynamically resolves them from the company's business activity and sector.
     *
     * @return array<int, string>
     */
    public function allowedCodesForCompany(Company $company): array
    {
        $scenarios = $this->scenariosForSectorAndActivity($company->business_activity, $company->sector);

        // FBR DI API v1.12, Section 9 (page 46): SN026-SN028 are
        // applicable only when the taxpayer is registered as a retailer
        // in the Sales Tax Profile. Business activity/sector alone is not proof.
        if ($company->is_sales_tax_profile_retailer !== true) {
            $scenarios = array_values(array_diff($scenarios, ['SN026', 'SN027', 'SN028']));
        }

        return $scenarios;
    }

    /**
     * Resolves scenarios for the company. If no custom scenarios are assigned,
     * it automatically determines the official FBR scenarios based on Company Business Nature & Sector.
     *
     * @return array<int, array{id: string, title: string, is_supported: bool}>
     */
    public function forCompany(Company $company): array
    {
        $allowed = $this->allowedCodesForCompany($company);
        $catalogue = $this->definitions();
        $resolved = [];
        foreach ($allowed as $id) {
            $definition = $catalogue[$id] ?? null;
            $resolved[] = [
                'id' => (string) $id,
                'title' => $definition['title'] ?? ('Scenario '.$id),
                'is_supported' => $definition !== null && $definition['is_supported'] === true,
            ];
        }

        return $resolved;
    }

    /**
     * Builds a certified test invoice payload for FBR sandbox validation/submission.
     *
     * @throws ValidationException
     */
    public function buildCertificationPayload(Company $company, string $scenarioId): array
    {
        $definitions = $this->definitions();
        if (! isset($definitions[$scenarioId])) {
            throw ValidationException::withMessages([
                'scenario_id' => 'Scenario '.$scenarioId.' is not recognized in FBR catalogue.',
            ]);
        }

        $def = $definitions[$scenarioId];
        $quantity = 1.0;
        $unitPrice = 1000.0;
        $ratePercent = (float) str_replace('%', '', $def['rate'] ?? '18%');
        $retailPrice = (float) ($def['retail_price'] ?? 0.0);
        $furtherTax = (float) ($def['further_tax'] ?? 0.0);
        $extraTax = (float) ($def['extra_tax'] ?? 0.0);
        $fedPayable = (float) ($def['fed_payable'] ?? 0.0);
        $sroScheduleNo = $def['sro_schedule_no'] ?? '';
        $sroItemSerialNo = $def['sro_item_serial_no'] ?? '';
        $saleType = $def['sale_type'];

        // Tax base calculation according to Sales Tax Act Section 3(2)(a)
        if ($retailPrice > 0) {
            $taxBase = $quantity * $retailPrice;
            $salesTaxApplicable = round($taxBase * ($ratePercent / 100), 2);
        } else {
            $taxBase = $quantity * $unitPrice;
            $salesTaxApplicable = round($taxBase * ($ratePercent / 100), 2);
        }

        $totalValues = $taxBase + $salesTaxApplicable + $furtherTax + $extraTax + $fedPayable;

        $buyerNTN = $def['buyer_type'] === 'Registered' ? '1000000000000' : '';
        $buyerName = $def['buyer_type'] === 'Registered' ? 'FERTILIZER MANUFAC IRS NEW' : 'End Consumer / Unregistered Buyer';

        return [
            'invoiceType' => 'Sale Invoice',
            'invoiceDate' => now()->toDateString(),
            'sellerNTNCNIC' => $company->ntn_cnic,
            'sellerBusinessName' => $company->name,
            'sellerProvince' => $company->province,
            'sellerAddress' => $company->address,
            'buyerNTNCNIC' => $buyerNTN,
            'buyerBusinessName' => $buyerName,
            'buyerProvince' => $company->province,
            'buyerAddress' => $company->address,
            'buyerRegistrationType' => $def['buyer_type'],
            'invoiceRefNo' => '',
            'scenarioId' => $scenarioId,
            'items' => [[
                'hsCode' => $def['hs_code'] ?? '0101.2100',
                'productDescription' => 'Sandbox certification sample item - '.$def['title'],
                'rate' => $def['rate'],
                'uoM' => $def['uom'] ?? 'Numbers, pieces, units',
                'quantity' => $quantity,
                'totalValues' => $totalValues,
                'valueSalesExcludingST' => $taxBase,
                'fixedNotifiedValueOrRetailPrice' => $retailPrice,
                'salesTaxApplicable' => $salesTaxApplicable,
                'salesTaxWithheldAtSource' => 0.0,
                'extraTax' => $extraTax,
                'furtherTax' => $furtherTax,
                'sroScheduleNo' => $sroScheduleNo,
                'fedPayable' => $fedPayable,
                'discount' => 0.0,
                'saleType' => $saleType,
                'sroItemSerialNo' => $sroItemSerialNo,
            ]],
        ];
    }
}
