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
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'seller_ntn' => ['required', 'string', 'regex:/^[0-9]{7,8}$/'],
            'seller_cnic' => ['required', 'string', 'regex:/^[0-9]{13}$/'],
            'province' => ['required', Rule::in(config('erp.provinces'))],
            'address' => ['required', 'string', 'max:2000'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+(). -]+$/'],

            'invoice_series' => ['sometimes', 'nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9_-]+$/'],
            'currency' => ['sometimes', Rule::in(['PKR'])],
            'fbr_environment' => ['sometimes', Rule::in(['sandbox', 'production'])],
            'business_activity' => ['sometimes', 'nullable', Rule::in(config('fbr.business_activities'))],
            'sector' => ['sometimes', 'nullable', Rule::in(config('fbr.sectors'))],
            'allowed_sandbox_scenarios' => ['sometimes', 'array'],
            'allowed_sandbox_scenarios.*' => ['required', 'string', 'distinct', Rule::in(array_keys(app(FbrScenarioRegistry::class)->definitions()))],
            'sandbox_token' => [Rule::prohibitedIf($this->input('fbr_environment') !== 'sandbox'), 'nullable', 'string', 'max:4096', 'regex:/^\\S+$/'],
            'production_token' => [Rule::prohibitedIf($this->input('fbr_environment') !== 'production'), 'nullable', 'string', 'max:4096', 'regex:/^\\S+$/'],
        ];
    }
}
