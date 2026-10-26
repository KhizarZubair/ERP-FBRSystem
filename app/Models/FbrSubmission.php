<?php

namespace App\Models;

use App\Enums\FbrEnvironment;
use App\Enums\FbrOperation;
use App\Enums\FbrSubmissionStatus;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['invoice_id', 'attempt_number', 'idempotency_key', 'environment', 'operation', 'status', 'endpoint', 'request_payload', 'payload_hash', 'http_status', 'response_payload', 'response_body', 'business_status_code', 'business_error_code', 'fbr_invoice_number', 'error_message', 'submitted_at', 'completed_at', 'reconciled_at', 'reconciliation_notes'])]
#[Hidden(['request_payload', 'response_payload', 'response_body'])]
class FbrSubmission extends Model
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'environment' => FbrEnvironment::class,
            'status' => FbrSubmissionStatus::class,
            'operation' => FbrOperation::class,
            'request_payload' => 'encrypted:array',
            'response_payload' => 'encrypted:array',
            'response_body' => 'encrypted',
            'submitted_at' => 'datetime',
            'completed_at' => 'datetime',
            'reconciled_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(FbrSubmissionItem::class)->orderBy('line_number');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
}
