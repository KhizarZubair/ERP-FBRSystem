<?php

namespace App\Http\Requests\Company;

use App\Services\FbrScenarioRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email') && is_string($this->input('email'))) {
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
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+(). -]+$/'],

            'invoice_series' => ['sometimes', 'nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9_-]+$/'],
            'currency' => ['sometimes', Rule::in(['PKR'])],
            'fbr_environment' => ['sometimes', Rule::in(['sandbox', 'production'])],
            'business_activity' => ['sometimes', 'required', 'array', 'min:1'],
            'business_activity.*' => ['required', 'string', 'distinct', Rule::in(config('fbr.business_activities'))],
            'sector' => ['sometimes', 'required', 'array', 'min:1'],
            'sector.*' => ['required', 'string', 'distinct', Rule::in(config('fbr.sectors'))],
            'is_sales_tax_profile_retailer' => ['sometimes', 'boolean'],
            'allowed_sandbox_scenarios' => ['sometimes', 'array'],
            'allowed_sandbox_scenarios.*' => ['required', 'string', 'distinct', Rule::in(array_keys(app(FbrScenarioRegistry::class)->definitions()))],
            'sandbox_token' => [Rule::prohibitedIf($this->input('fbr_environment') !== 'sandbox'), 'nullable', 'string', 'max:4096', 'regex:/^\\S+$/'],
            'production_token' => [Rule::prohibitedIf($this->input('fbr_environment') !== 'production'), 'nullable', 'string', 'max:4096', 'regex:/^\\S+$/'],
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
