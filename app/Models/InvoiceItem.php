<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['invoice_id', 'item_id', 'line_number', 'sku', 'product_description', 'hs_code', 'unit_of_measurement', 'sale_type', 'tax_rate_label', 'tax_rate_percent', 'quantity', 'unit_price', 'value_sales_excluding_st', 'fixed_notified_value_or_retail_price', 'sales_tax_applicable', 'sales_tax_withheld_at_source', 'extra_tax', 'further_tax', 'fed_payable', 'discount', 'total_values', 'sro_schedule_no', 'sro_item_serial_no'])]
class InvoiceItem extends Model
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'tax_rate_percent' => 'decimal:6',
            'quantity' => 'decimal:6',
            'unit_price' => 'decimal:6',
            'value_sales_excluding_st' => 'decimal:6',
            'fixed_notified_value_or_retail_price' => 'decimal:6',
            'sales_tax_applicable' => 'decimal:6',
            'sales_tax_withheld_at_source' => 'decimal:6',
            'extra_tax' => 'decimal:6',
            'further_tax' => 'decimal:6',
            'fed_payable' => 'decimal:6',
            'discount' => 'decimal:6',
            'total_values' => 'decimal:6',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function fbrSubmissionItems(): HasMany
    {
        return $this->hasMany(FbrSubmissionItem::class);
    }
}
