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
        $quantity = $amount('quantity');
        $gross = $quantity->multipliedBy($amount('unit_price'));
        $discount = $amount('discount');
        if ($discount->isGreaterThan($gross)) {
            throw ValidationException::withMessages(["lines.$index.discount" => 'Discount cannot exceed quantity multiplied by unit price.']);
        }
        $net = $gross->minus($discount)->toScale(6, RoundingMode::HalfUp);
        $taxRate = $amount('tax_rate_percent');
        $taxRateLabel = trim((string) ($line['tax_rate_label'] ?? ''));

        // Sector & Business Activity classification
        $retailPrice = $amount('fixed_notified_value_or_retail_price');
        $saleType = strtolower(trim((string) ($line['sale_type'] ?? '')));
        $sectors = array_map(
            fn ($value): string => strtolower(trim((string) $value)),
            is_array($context['sector'] ?? null) ? $context['sector'] : [$context['sector'] ?? '']
        );
        $businessActivities = array_map(
            fn ($value): string => strtolower(trim((string) $value)),
            is_array($context['business_activity'] ?? null) ? $context['business_activity'] : [$context['business_activity'] ?? '']
        );
        $hasFmcgSector = collect($sectors)->contains(fn (string $value): bool => str_contains($value, 'fmcg'));
        $isRetailer = in_array('retailer', $businessActivities, true);
        $scenarioId = strtoupper(trim((string) ($context['scenario_id'] ?? $line['scenario_id'] ?? '')));

        $isFedInStModeGoods = $scenarioId === 'SN017' || str_contains($saleType, 'goods (fed in st mode)');
        $isCement = $scenarioId === 'SN021' || str_contains($saleType, 'cement') || str_contains($saleType, 'concrete block');
        $isPotassiumChlorate = $scenarioId === 'SN022' || str_contains($saleType, 'potassium chlorate');
        $isCottonGinner = $scenarioId === 'SN009' || str_contains($saleType, 'cotton ginner');

        // Check if item falls under Sales Tax Act Section 3(2)(a) (Third Schedule / FMCG Retail Price)
        $isThirdSchedule = str_contains($saleType, '3rd schedule')
            || str_contains($saleType, 'third schedule')
            || $scenarioId === 'SN008'
            || $scenarioId === 'SN027'
            || ($retailPrice->isPositive() && ($hasFmcgSector || str_contains($saleType, 'retail')));

        if ($isFedInStModeGoods) {
            // SN017 — Goods (FED in ST Mode): ((value + perUnitFed * qty) * rate%) + (perUnitFed * qty)
            // Default statutory rate: Rs. 80 per Liter unless specified in tax_rate_label
            $perUnitFed = BigDecimal::of('80')->toScale(6);
            if (preg_match('/(?:rs\.?|rupees)\s*([0-9]+(?:\.[0-9]+)?)/i', $taxRateLabel, $matches)) {
                $perUnitFed = BigDecimal::of($matches[1])->toScale(6);
            }
            $fedComponent = $quantity->multipliedBy($perUnitFed);
            $tax = $net->plus($fedComponent)
                ->multipliedBy($taxRate)
                ->dividedBy(100, 6, RoundingMode::HalfUp)
                ->plus($fedComponent)
                ->toScale(6, RoundingMode::HalfUp);
        } elseif ($isPotassiumChlorate) {
            // SN022 — Potassium Chlorate: (value * rate%) + (perKgDuty * qty)
            // Default statutory rate: 18% along with Rs. 60 per KG
            $perKgDuty = BigDecimal::of('60')->toScale(6);
            if (preg_match('/(?:rs\.?|rupees)\s*([0-9]+(?:\.[0-9]+)?)/i', $taxRateLabel, $matches)) {
                $perKgDuty = BigDecimal::of($matches[1])->toScale(6);
            }
            $tax = $net->multipliedBy($taxRate)
                ->dividedBy(100, 6, RoundingMode::HalfUp)
                ->plus($quantity->multipliedBy($perKgDuty))
                ->toScale(6, RoundingMode::HalfUp);
        } elseif ($isCement) {
            // SN021 — Cement / Concrete Block: fixed statutory rate Rs. 2 per KG * qty
            $fixedRate = BigDecimal::of('2')->toScale(6);
            if (preg_match('/(?:rs\.?|rupees)\s*([0-9]+(?:\.[0-9]+)?)/i', $taxRateLabel, $matches)) {
                $fixedRate = BigDecimal::of($matches[1])->toScale(6);
            }
            $tax = $quantity->multipliedBy($fixedRate)->toScale(6, RoundingMode::HalfUp);
        } elseif ($isThirdSchedule && $retailPrice->isPositive()) {
            // Section 3(2)(a): Tax base is Printed Retail Price * Quantity
            $taxBase = $retailPrice->multipliedBy($quantity)->toScale(6, RoundingMode::HalfUp);
            $tax = $taxBase->multipliedBy($taxRate)->dividedBy(100, 6, RoundingMode::HalfUp);
        } else {
            // Standard / Net Value based
            $tax = $net->multipliedBy($taxRate)->dividedBy(100, 6, RoundingMode::HalfUp);
        }

        // Further Tax handling under Section 3(1A) & FBR Sector Rules
        $buyerRegistrationType = $context['buyer_registration_type'] ?? null;
        if ($buyerRegistrationType instanceof \BackedEnum) {
            $buyerRegistrationType = (string) $buyerRegistrationType->value;
        }
        $buyerRegistrationType = strtolower(trim((string) $buyerRegistrationType));


        $hasExplicitFurtherTax = array_key_exists('further_tax', $line)
            && $line['further_tax'] !== null
            && trim((string) $line['further_tax']) !== '';

        if ($isThirdSchedule || $isRetailer || $scenarioId === 'SN001') {
            $furtherTax = BigDecimal::zero()->toScale(6);
        } elseif (! $hasExplicitFurtherTax && ($scenarioId === 'SN002' || $buyerRegistrationType === 'unregistered')) {
            $furtherTaxRate = BigDecimal::of((string) config('fbr.further_tax_rate_percent', 4))->toScale(6);
            $furtherTax = $net->multipliedBy($furtherTaxRate)->dividedBy(100, 6, RoundingMode::HalfUp);
        } else {
            $furtherTax = $amount('further_tax');
        }

        $withheld = $amount('sales_tax_withheld_at_source');
        if ($withheld->isGreaterThan($tax)) {
            throw ValidationException::withMessages(["lines.$index.sales_tax_withheld_at_source" => 'Withheld sales tax cannot exceed sales tax.']);
        }
        // SN009 — Cotton Ginners (FBR Sales Error 0050): ST Withheld must be either 0 or equal to Sales Tax
        if ($isCottonGinner && ! $withheld->isZero() && ! $withheld->isEqualTo($tax)) {
            throw ValidationException::withMessages([
                "lines.$index.sales_tax_withheld_at_source" => "For sale type 'Cotton Ginners', Sales Tax Withheld must be either 0 or equal to Sales Tax ({$tax}).",
            ]);
        }

        $total = $net->plus($tax)->plus($amount('extra_tax'))->plus($furtherTax)->plus($amount('fed_payable'))->minus($withheld);
        foreach ([$net, $tax, $total] as $value) {
            if ($value->isGreaterThan('99999999999999.999999')) {
                throw ValidationException::withMessages(["lines.$index.unit_price" => 'Calculated amount exceeds the supported range.']);
            }
        }

        return [
            'quantity' => (string) $quantity, 'unit_price' => (string) $amount('unit_price'),
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
