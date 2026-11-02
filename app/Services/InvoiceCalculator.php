<?php

namespace App\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Validation\ValidationException;

class InvoiceCalculator
{
    public const TOTALS = ['value_sales_excluding_st', 'sales_tax_applicable', 'sales_tax_withheld_at_source', 'extra_tax', 'further_tax', 'fed_payable', 'discount', 'total_values'];

    /**
     * Calculate line amounts with FBR sector & business activity fiscal rules.
     *
     * @param  array<string, mixed>  $line
     * @param  array<string, mixed>  $context  Optional context: business_activity, sector, buyer_registration_type, scenario_id
     * @return array<string, string>
     */
    public function line(array $line, int $index, array $context = []): array
    {
        $amount = fn (string $key): BigDecimal => BigDecimal::of((string) ($line[$key] ?? '0'))->toScale(6);
        $gross = $amount('quantity')->multipliedBy($amount('unit_price'));
        $discount = $amount('discount');
        if ($discount->isGreaterThan($gross)) {
            throw ValidationException::withMessages(["lines.$index.discount" => 'Discount cannot exceed quantity multiplied by unit price.']);
        }
        $net = $gross->minus($discount)->toScale(6, RoundingMode::HalfUp);
        $taxRate = $amount('tax_rate_percent');

        // Sector & Business Activity classification
        $retailPrice = $amount('fixed_notified_value_or_retail_price');
        $saleType = strtolower(trim((string) ($line['sale_type'] ?? '')));
        $sector = strtolower(trim((string) ($context['sector'] ?? '')));
        $businessActivity = strtolower(trim((string) ($context['business_activity'] ?? '')));
        $scenarioId = strtoupper(trim((string) ($context['scenario_id'] ?? $line['scenario_id'] ?? '')));

        // Check if item falls under Sales Tax Act Section 3(2)(a) (Third Schedule / FMCG Retail Price)
        $isThirdSchedule = str_contains($saleType, '3rd schedule')
            || str_contains($saleType, 'third schedule')
            || $scenarioId === 'SN008'
            || $scenarioId === 'SN027'
            || ($retailPrice->isPositive() && (str_contains($sector, 'fmcg') || str_contains($saleType, 'retail')));

        if ($isThirdSchedule && $retailPrice->isPositive()) {
            // Section 3(2)(a): Tax base is Printed Retail Price * Quantity
            $taxBase = $retailPrice->multipliedBy($amount('quantity'))->toScale(6, RoundingMode::HalfUp);
            $tax = $taxBase->multipliedBy($taxRate)->dividedBy(100, 6, RoundingMode::HalfUp);
        } else {
            // Standard / Net Value based
            $tax = $net->multipliedBy($taxRate)->dividedBy(100, 6, RoundingMode::HalfUp);
        }

        // Further Tax handling under Section 3(1A) & FBR Sector Rules:
        // 1. Proviso to Section 3(1A): Further tax is NOT applicable on 3rd schedule goods
        // 2. Retailers selling to end consumers (SN026, SN027, SN028) are exempt from further tax
        $furtherTax = $amount('further_tax');
        if ($isThirdSchedule && $furtherTax->isPositive()) {
            $furtherTax = BigDecimal::zero()->toScale(6);
        }
        if ($businessActivity === 'retailer' && $furtherTax->isPositive()) {
            $furtherTax = BigDecimal::zero()->toScale(6);
        }

        $withheld = $amount('sales_tax_withheld_at_source');
        if ($withheld->isGreaterThan($tax)) {
            throw ValidationException::withMessages(["lines.$index.sales_tax_withheld_at_source" => 'Withheld sales tax cannot exceed sales tax.']);
        }
        $total = $net->plus($tax)->plus($amount('extra_tax'))->plus($furtherTax)->plus($amount('fed_payable'))->minus($withheld);
        foreach ([$net, $tax, $total] as $value) {
            if ($value->isGreaterThan('99999999999999.999999')) {
                throw ValidationException::withMessages(["lines.$index.unit_price" => 'Calculated amount exceeds the supported range.']);
            }
        }

        return [
            'quantity' => (string) $amount('quantity'), 'unit_price' => (string) $amount('unit_price'),
            'tax_rate_percent' => (string) $amount('tax_rate_percent'),
            'value_sales_excluding_st' => (string) $net, 'sales_tax_applicable' => (string) $tax,
            'total_values' => (string) $total,
            'discount' => (string) $discount,
            'sales_tax_withheld_at_source' => (string) $withheld,
            'extra_tax' => (string) $amount('extra_tax'),
            'further_tax' => (string) $furtherTax,
            'fed_payable' => (string) $amount('fed_payable'),
        ];
    }

    /** @param array<int, array<string, mixed>> $lines @return array<string, string> */
    public function totals(array $lines): array
    {
        $totals = [];
        foreach (self::TOTALS as $key) {
            $value = BigDecimal::zero();
            foreach ($lines as $line) {
                $value = $value->plus($line[$key]);
            }
            if ($value->isGreaterThan('99999999999999.999999')) {
                throw ValidationException::withMessages(['lines' => 'Invoice total exceeds the supported range.']);
            }
            $totals[$key] = (string) $value->toScale(6);
        }

        return $totals;
    }
}
