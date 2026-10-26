<?php

namespace App\Models;

use App\Enums\StockMovementType;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['item_id', 'movement_date', 'document_number', 'movement_type', 'quantity', 'invoice_item_id', 'reversal_of_id', 'idempotency_key', 'notes'])]
class StockMovement extends Model
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'movement_type' => StockMovementType::class,
            'movement_date' => 'date',
            'quantity' => 'decimal:6',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(InvoiceItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'reversal_of_id');
    }

    public function reversal(): HasOne
    {
        return $this->hasOne(StockMovement::class, 'reversal_of_id');
    }
}
