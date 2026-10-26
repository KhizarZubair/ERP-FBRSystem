<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['extra_tax', 'fed_payable', 'sku', 'name', 'description', 'hs_code', 'unit_of_measurement', 'sale_type', 'tax_rate_label', 'tax_rate_percent', 'fixed_notified_value_or_retail_price', 'sro_schedule_no', 'sro_item_serial_no', 'track_stock', 'reorder_level', 'is_active'])]
class Item extends Model
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'extra_tax' => 'decimal:6', 'fed_payable' => 'decimal:6',
            'tax_rate_percent' => 'decimal:6',
            'fixed_notified_value_or_retail_price' => 'decimal:6',
            'track_stock' => 'boolean',
            'reorder_level' => 'decimal:6',
            'is_active' => 'boolean',
        ];
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ItemPrice::class);
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
