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
}
