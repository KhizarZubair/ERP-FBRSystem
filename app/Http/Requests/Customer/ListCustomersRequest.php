<?php

namespace App\Http\Requests\Customer;

use App\Enums\RegistrationType;
use App\Services\CustomerService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListCustomersRequest extends FormRequest
{
    public function authorize(): bool
    {
        $this->attributes->set('customer_company', app(CustomerService::class)->company($this->user(), (int) $this->route('company')));

        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'registration_type' => ['nullable', Rule::enum(RegistrationType::class)],
            'page' => ['sometimes', 'integer', 'min:1', 'max:100000'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort' => ['sometimes', Rule::in(['name', 'code', 'registration_type', 'province', 'id'])],
            'direction' => ['sometimes', Rule::in(['asc', 'desc'])],
        ];
    }
}
