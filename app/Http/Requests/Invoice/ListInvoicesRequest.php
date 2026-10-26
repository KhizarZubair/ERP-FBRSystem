<?php

namespace App\Http\Requests\Invoice;

use App\Services\CustomerService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListInvoicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $this->attributes->set('workspace', app(CustomerService::class)->company($this->user(), (int) $this->route('company')));

        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'], 'page' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'sort' => ['nullable', Rule::in(['number', 'invoice_date', 'total_values', 'buyer_business_name'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'status' => ['nullable', Rule::in(['draft', 'approved', 'issued', 'cancelled'])],
            'fbr_status' => ['nullable', Rule::in(['not_submitted', 'pending', 'validation_failed', 'accepted', 'rejected', 'unknown'])],
            'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', ...($this->filled('from') ? ['after_or_equal:from'] : [])],
        ];
    }
}
