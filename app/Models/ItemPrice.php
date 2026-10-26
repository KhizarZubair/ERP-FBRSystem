<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['item_id', 'price_list', 'unit_price', 'currency', 'effective_from', 'effective_to', 'is_active'])]
class ItemPrice extends Model
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:6',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
