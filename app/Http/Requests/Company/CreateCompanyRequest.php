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
        if ($this->has('seller_ntn') && (is_null($this->input('seller_ntn')) || trim((string) $this->input('seller_ntn')) === '')) {
            $this->merge(['seller_ntn' => null]);
        }
        if ($this->has('seller_cnic') && (is_null($this->input('seller_cnic')) || trim((string) $this->input('seller_cnic')) === '')) {
            $this->merge(['seller_cnic' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'seller_ntn' => ['required_without:seller_cnic', 'nullable', 'string', 'regex:/^[0-9]{7,8}$/'],
            'seller_cnic' => ['required_without:seller_ntn', 'nullable', 'string', 'regex:/^[0-9]{13}$/'],
            'province' => ['required', Rule::in(config('erp.provinces'))],
            'address' => ['required', 'string', 'max:2000'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+(). -]+$/'],
            'business_activity' => ['sometimes', 'required', 'array', 'min:1'],
            'business_activity.*' => ['required', 'string', 'distinct', Rule::in(config('fbr.business_activities'))],
            'sector' => ['sometimes', 'required', 'array', 'min:1'],
            'sector.*' => ['required', 'string', 'distinct', Rule::in(config('fbr.sectors'))],
        ];
    }

    public function messages(): array
    {
        return [
            'seller_ntn.required_without' => 'Either Seller NTN (7 or 8 digits) or Seller CNIC (13 digits) is required.',
            'seller_cnic.required_without' => 'Either Seller CNIC (13 digits) or Seller NTN (7 or 8 digits) is required.',
            'seller_ntn.regex' => 'Seller NTN must contain 7 or 8 digits.',
            'seller_cnic.regex' => 'Seller CNIC must contain exactly 13 digits.',
        ];
    }
}
