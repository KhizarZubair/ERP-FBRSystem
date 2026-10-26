<?php

namespace App\Http\Requests\Stock;

use App\Services\CustomerService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveStockMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $this->attributes->set('workspace', app(CustomerService::class)->company($this->user(), (int) $this->route('company'), true));

        return true;
    }

    public function rules(): array
    {
        return ['item_id' => ['required', 'integer', 'min:1'], 'movement_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'movement_type' => ['required', Rule::in(['opening', 'receipt', 'issue', 'adjustment'])],
            'document_number' => ['required', 'string', 'max:100'],
            'quantity' => ['required', 'numeric', 'decimal:0,6', 'min:-999999999', 'max:999999999'],
            'idempotency_key' => ['required', 'uuid'], 'notes' => ['nullable', 'string', 'max:5000']];
    }
}
