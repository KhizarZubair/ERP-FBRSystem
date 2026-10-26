<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['invoice_id', 'fbr_submission_id', 'invoice_item_id', 'line_number', 'status', 'status_code', 'error_code', 'error_message', 'fbr_invoice_number', 'response_payload'])]
#[Hidden(['response_payload'])]
class FbrSubmissionItem extends Model
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'response_payload' => 'encrypted:array',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(FbrSubmission::class, 'fbr_submission_id');
    }

    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(InvoiceItem::class);
    }
}
