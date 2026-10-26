<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Validation\ValidationException;

class FbrScenarioRegistry
{
    /**
     * Complete catalogue of FBR sandbox scenarios (SN001 to SN028).
     * Currently, only SN001 and SN002 are officially supported and executable.
     * Other scenarios are catalogued as unsupported until official fiscal formulas and SRO rules are implemented.
     *
     * @return array<string, array{id: string, title: string, description: string, sale_type: string, buyer_type: string, further_tax: float, is_supported: bool}>
     */
    public function definitions(): array
    {
        return [
            'SN001' => [
                'id' => 'SN001',
                'title' => 'Goods at standard rate to registered buyers',
                'description' => 'Goods at standard rate to registered buyers',
                'sale_type' => 'Goods at Standard Rate (default)',
                'buyer_type' => 'Registered',
                'further_tax' => 0.0,
                'is_supported' => true,
            ],
            'SN002' => [
                'id' => 'SN002',
                'title' => 'Goods at standard rate to unregistered buyers',
                'description' => 'Goods at standard rate to unregistered buyers',
                'sale_type' => 'Goods at Standard Rate (default)',
                'buyer_type' => 'Unregistered',
                'further_tax' => 120.0,
                'is_supported' => true,
            ],
            'SN003' => [
                'id' => 'SN003',
                'title' => 'Exempt goods',
                'description' => 'Exempt goods to registered or unregistered buyers',
                'sale_type' => 'Exempt Goods',
                'buyer_type' => 'Registered',
                'further_tax' => 0.0,
                'is_supported' => false,
            ],
            'SN004' => [
                'id' => 'SN004',
                'title' => 'Zero-rated goods',
                'description' => 'Zero-rated supplies',
                'sale_type' => 'Zero Rated Goods',
                'buyer_type' => 'Registered',
                'further_tax' => 0.0,
                'is_supported' => false,
            ],
            'SN005' => [
                'id' => 'SN005',
                'title' => 'Reduced-rate goods',
                'description' => 'Goods at reduced sales tax rate under SRO/Schedule',
                'sale_type' => 'Goods at Reduced Rate',
                'buyer_type' => 'Registered',
                'further_tax' => 0.0,
                'is_supported' => false,
            ],
            'SN006' => [
                'id' => 'SN006',
                'title' => 'Debit/Credit Note against standard rate invoice',
                'description' => 'Adjustment note for goods at standard rate',
                'sale_type' => 'Goods at Standard Rate (default)',
                'buyer_type' => 'Registered',
                'further_tax' => 0.0,
                'is_supported' => false,
            ],
            'SN007' => [
                'id' => 'SN007',
                'title' => 'Debit/Credit Note against reduced rate invoice',
                'description' => 'Adjustment note for goods at reduced rate',
                'sale_type' => 'Goods at Reduced Rate',
                'buyer_type' => 'Registered',
                'further_tax' => 0.0,
                'is_supported' => false,
            ],
            'SN008' => [
                'id' => 'SN008',
                'title' => '3rd Schedule goods (Retail Price)',
                'description' => 'Goods chargeable on retail price under Third Schedule',
                'sale_type' => 'Third Schedule Goods',
                'buyer_type' => 'Registered',
                'further_tax' => 0.0,
                'is_supported' => false,
            ],
            'SN026' => [
                'id' => 'SN026',
                'title' => 'Retailer sales to registered buyers',
                'description' => 'Retail point-of-sale invoice for registered buyer',
                'sale_type' => 'Goods at Standard Rate (default)',
                'buyer_type' => 'Registered',
                'further_tax' => 0.0,
                'is_supported' => false,
            ],
            'SN027' => [
                'id' => 'SN027',
                'title' => 'Retailer sales to unregistered buyers',
                'description' => 'Retail point-of-sale invoice for unregistered buyer',
                'sale_type' => 'Goods at Standard Rate (default)',
                'buyer_type' => 'Unregistered',
                'further_tax' => 0.0,
                'is_supported' => false,
            ],
            'SN028' => [
                'id' => 'SN028',
                'title' => 'Retailer sales with POS integration',
                'description' => 'General retail POS integration scenario',
                'sale_type' => 'Goods at Standard Rate (default)',
                'buyer_type' => 'Unregistered',
                'further_tax' => 0.0,
                'is_supported' => false,
            ],
        ];
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
     * Resolves the explicit scenarios assigned to the company.
     * Per safety rules: an empty assignment list does NOT default to SN001/SN002;
     * it returns an empty array indicating configuration is incomplete.
     *
     * @return array<int, array{id: string, title: string, is_supported: bool}>
     */
    public function forCompany(Company $company): array
    {
        $allowed = $company->allowed_sandbox_scenarios ?? [];
        if (! is_array($allowed) || empty($allowed)) {
            return [];
        }

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
        if (! $this->isSupported($scenarioId)) {
            throw ValidationException::withMessages([
                'scenario_id' => 'Scenario '.$scenarioId.' is not supported in this release. Official tax formulas and SRO rules are pending.',
            ]);
        }

        $definition = $this->definitions()[$scenarioId];
        $furtherTax = (float) $definition['further_tax'];
        $taxApplicable = 180.0;
        $totalValues = 1000.0 + $taxApplicable + $furtherTax;

        return [
            'invoiceType' => 'Sale Invoice',
            'invoiceDate' => now()->toDateString(),
            'sellerNTNCNIC' => $company->ntn_cnic,
            'sellerBusinessName' => $company->name,
            'sellerProvince' => $company->province,
            'sellerAddress' => $company->address,
            'buyerNTNCNIC' => $definition['buyer_type'] === 'Registered' ? '1000000000000' : '',
            'buyerBusinessName' => 'FERTILIZER MANUFAC IRS NEW',
            'buyerProvince' => $company->province,
            'buyerAddress' => $company->address,
            'buyerRegistrationType' => $definition['buyer_type'],
            'invoiceRefNo' => '',
            'scenarioId' => $scenarioId,
            'items' => [[
                'hsCode' => '0101.2100',
                'productDescription' => 'Sandbox certification sample item',
                'rate' => '18%',
                'uoM' => 'Numbers, pieces, units',
                'quantity' => 1,
                'totalValues' => $totalValues,
                'valueSalesExcludingST' => 1000,
                'fixedNotifiedValueOrRetailPrice' => 0,
                'salesTaxApplicable' => $taxApplicable,
                'salesTaxWithheldAtSource' => 0,
                'extraTax' => 0,
                'furtherTax' => $furtherTax,
                'sroScheduleNo' => '',
                'fedPayable' => 0,
                'discount' => 0,
                'saleType' => $definition['sale_type'],
                'sroItemSerialNo' => '',
            ]],
        ];
    }
}
