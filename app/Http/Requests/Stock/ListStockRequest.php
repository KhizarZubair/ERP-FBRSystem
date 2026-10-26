<?php

namespace App\Http\Requests\Stock;

use App\Services\CustomerService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        $this->attributes->set('workspace', app(CustomerService::class)->company($this->user(), (int) $this->route('company')));

        return true;
    }

    public function rules(): array
    {
        return ['item_id' => ['nullable', 'integer', 'min:1'], 'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000000'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', ...($this->filled('from') ? ['after_or_equal:from'] : [])],
            'movement_type' => ['nullable', Rule::in(['opening', 'receipt', 'issue', 'adjustment', 'reversal'])]];
    }
}
