<?php

namespace App\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Validation\ValidationException;

class InvoiceCalculator
{
    public const TOTALS = ['value_sales_excluding_st', 'sales_tax_applicable', 'sales_tax_withheld_at_source', 'extra_tax', 'further_tax', 'fed_payable', 'discount', 'total_values'];

    /** @param array<string, mixed> $line @return array<string, string> */
    public function line(array $line, int $index): array
    {
        $amount = fn (string $key): BigDecimal => BigDecimal::of((string) ($line[$key] ?? '0'))->toScale(6);
        $gross = $amount('quantity')->multipliedBy($amount('unit_price'));
        $discount = $amount('discount');
        if ($discount->isGreaterThan($gross)) {
            throw ValidationException::withMessages(["lines.$index.discount" => 'Discount cannot exceed quantity multiplied by unit price.']);
        }
        $net = $gross->minus($discount)->toScale(6, RoundingMode::HalfUp);
        $tax = $net->multipliedBy($amount('tax_rate_percent'))->dividedBy(100, 6, RoundingMode::HalfUp);
        $withheld = $amount('sales_tax_withheld_at_source');
        if ($withheld->isGreaterThan($tax)) {
            throw ValidationException::withMessages(["lines.$index.sales_tax_withheld_at_source" => 'Withheld sales tax cannot exceed sales tax.']);
        }
        $total = $net->plus($tax)->plus($amount('extra_tax'))->plus($amount('further_tax'))->plus($amount('fed_payable'))->minus($withheld);
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
            ...array_combine(['discount', 'sales_tax_withheld_at_source', 'extra_tax', 'further_tax', 'fed_payable'],
                array_map(fn ($key) => (string) $amount($key), ['discount', 'sales_tax_withheld_at_source', 'extra_tax', 'further_tax', 'fed_payable'])),
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
