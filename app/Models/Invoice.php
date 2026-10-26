<?php

namespace App\Models;

use App\Enums\FbrEnvironment;
use App\Enums\InvoiceFbrStatus;
use App\Enums\InvoiceStatus;
use App\Enums\RegistrationType;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['customer_id', 'number', 'invoice_type', 'invoice_date', 'due_date', 'scenario_id', 'reference_invoice_no', 'notes', 'currency', 'status', 'fbr_status', 'fbr_environment', 'fbr_invoice_number', 'seller_business_name', 'seller_ntn_cnic', 'seller_province', 'seller_address', 'buyer_business_name', 'buyer_ntn_cnic', 'buyer_registration_type', 'buyer_province', 'buyer_address', 'value_sales_excluding_st', 'sales_tax_applicable', 'sales_tax_withheld_at_source', 'extra_tax', 'further_tax', 'fed_payable', 'discount', 'total_values', 'approved_at', 'issued_at', 'fbr_accepted_at'])]
class Invoice extends Model
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'fbr_status' => InvoiceFbrStatus::class,
            'fbr_environment' => FbrEnvironment::class,
            'buyer_registration_type' => RegistrationType::class,
            'invoice_date' => 'date',
            'due_date' => 'date',
            'value_sales_excluding_st' => 'decimal:6',
            'sales_tax_applicable' => 'decimal:6',
            'sales_tax_withheld_at_source' => 'decimal:6',
            'extra_tax' => 'decimal:6',
            'further_tax' => 'decimal:6',
            'fed_payable' => 'decimal:6',
            'discount' => 'decimal:6',
            'total_values' => 'decimal:6',
            'approved_at' => 'datetime',
            'issued_at' => 'datetime',
            'fbr_accepted_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('line_number');
    }

    public function fbrSubmissions(): HasMany
    {
        return $this->hasMany(FbrSubmission::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
