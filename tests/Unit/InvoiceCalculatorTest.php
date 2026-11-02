<?php

namespace Tests\Unit;

use App\Services\InvoiceCalculator;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InvoiceCalculatorTest extends TestCase
{
    public static function exactDecimalCases(): array
    {
        return [
            'six decimal multiplication and half-up tax' => [
                ['quantity' => '2.500000', 'unit_price' => '10.123456', 'tax_rate_percent' => '18', 'discount' => '0.1'],
                '25.208640', '4.537555', '29.746195',
            ],
            'half-up at seventh decimal' => [
                ['quantity' => '1', 'unit_price' => '0.000005', 'tax_rate_percent' => '10', 'discount' => '0'],
                '0.000005', '0.000001', '0.000006',
            ],
            'line-specific modifiers remain exact' => [
                ['quantity' => '3', 'unit_price' => '10', 'tax_rate_percent' => '18', 'discount' => '1.000001',
                    'sales_tax_withheld_at_source' => '1', 'extra_tax' => '0.100001', 'further_tax' => '0.200002', 'fed_payable' => '0.300003'],
                '28.999999', '5.220000', '33.820005',
            ],
        ];
    }

    #[DataProvider('exactDecimalCases')]
    public function test_calculations_use_exact_six_decimal_half_up_arithmetic(array $input, string $net, string $tax, string $total): void
    {
        $line = app(InvoiceCalculator::class)->line($input, 0);

        $this->assertSame($net, $line['value_sales_excluding_st']);
        $this->assertSame($tax, $line['sales_tax_applicable']);
        $this->assertSame($total, $line['total_values']);
    }

    public function test_invalid_discount_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        app(InvoiceCalculator::class)->line([
            'quantity' => '1', 'unit_price' => '10', 'tax_rate_percent' => '18', 'discount' => '10.000001',
        ], 0);
    }

    public function test_third_schedule_goods_calculate_tax_on_retail_price_not_discounted_net(): void
    {
        // 10 units at discounted trade price Rs 80 each (gross 800), but printed retail price is Rs 100 each.
        // Under Sales Tax Act Section 3(2)(a), Tax Base = 10 * 100 = 1000.
        // Sales Tax at 18% = 180.000000 (NOT 800 * 18% = 144.000000).
        // Value Sales Excluding ST = 800.000000.
        // Total = 800 + 180 = 980.000000.
        $result = app(InvoiceCalculator::class)->line([
            'quantity' => '10.000000',
            'unit_price' => '80.000000',
            'tax_rate_percent' => '18',
            'discount' => '0',
            'fixed_notified_value_or_retail_price' => '100.000000',
            'sale_type' => 'Third Schedule Goods',
        ], 0, ['sector' => 'FMCG']);

        $this->assertSame('800.000000', $result['value_sales_excluding_st']);
        $this->assertSame('180.000000', $result['sales_tax_applicable']);
        $this->assertSame('980.000000', $result['total_values']);
    }

    public function test_third_schedule_goods_strictly_suppress_further_tax(): void
    {
        // Under Section 3(1A) proviso, Further Tax cannot be charged on 3rd Schedule goods even if provided.
        $result = app(InvoiceCalculator::class)->line([
            'quantity' => '5.000000',
            'unit_price' => '200.000000',
            'tax_rate_percent' => '18',
            'fixed_notified_value_or_retail_price' => '250.000000',
            'sale_type' => 'Third Schedule Goods',
            'further_tax' => '30.000000',
        ], 0, ['sector' => 'FMCG']);

        $this->assertSame('0.000000', $result['further_tax']);
        // Tax base = 5 * 250 = 1250. Tax = 1250 * 18% = 225.
        // Net = 1000. Total = 1000 + 225 + 0 = 1225.
        $this->assertSame('225.000000', $result['sales_tax_applicable']);
        $this->assertSame('1225.000000', $result['total_values']);
    }

    public function test_retailer_business_activity_suppresses_further_tax_for_end_consumers(): void
    {
        // Under Sales Tax Act Section 3(1A), supplies by Retailers to end consumers are exempt from further tax.
        $result = app(InvoiceCalculator::class)->line([
            'quantity' => '1.000000',
            'unit_price' => '500.000000',
            'tax_rate_percent' => '18',
            'further_tax' => '20.000000',
        ], 0, [
            'business_activity' => 'Retailer',
            'scenario_id' => 'SN026',
        ]);

        $this->assertSame('0.000000', $result['further_tax']);
        // Net = 500, Tax = 90, Total = 590.
        $this->assertSame('590.000000', $result['total_values']);
    }

    public function test_totals_correctly_aggregates_mixed_standard_and_third_schedule_lines(): void
    {
        $calculator = app(InvoiceCalculator::class);

        // Line 1: Standard Goods (Machine part) - Net 1000, 18% tax = 180, Further tax = 30. Total = 1210.
        $line1 = $calculator->line([
            'quantity' => '1.000000',
            'unit_price' => '1000.000000',
            'tax_rate_percent' => '18',
            'further_tax' => '30.000000',
            'sale_type' => 'Standard Rate',
        ], 0);

        // Line 2: 3rd Schedule Goods (Detergent) - Net 500, Retail 600, 18% tax on 600 = 108. Total = 608.
        $line2 = $calculator->line([
            'quantity' => '1.000000',
            'unit_price' => '500.000000',
            'tax_rate_percent' => '18',
            'fixed_notified_value_or_retail_price' => '600.000000',
            'sale_type' => 'Third Schedule Goods',
        ], 1, ['sector' => 'FMCG']);

        $totals = $calculator->totals([$line1, $line2]);

        $this->assertSame('1500.000000', $totals['value_sales_excluding_st']);
        $this->assertSame('288.000000', $totals['sales_tax_applicable']);
        $this->assertSame('30.000000', $totals['further_tax']);
        $this->assertSame('1818.000000', $totals['total_values']);
    }
}
