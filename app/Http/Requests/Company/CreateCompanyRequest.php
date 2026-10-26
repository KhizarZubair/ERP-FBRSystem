<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active === true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => strtolower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'seller_ntn' => ['required', 'string', 'regex:/^[0-9]{7,8}$/'],
            'seller_cnic' => ['required', 'string', 'regex:/^[0-9]{13}$/'],
            'province' => ['required', Rule::in(config('erp.provinces'))],
            'address' => ['required', 'string', 'max:2000'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+(). -]+$/'],
        ];
    }
}
