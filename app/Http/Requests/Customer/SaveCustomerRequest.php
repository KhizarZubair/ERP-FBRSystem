<?php

namespace App\Http\Requests\Customer;

use App\Enums\RegistrationType;
use App\Models\Customer;
use App\Services\CustomerService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $company = app(CustomerService::class)->company($this->user(), (int) $this->route('company'), true);
        if ($this->route('customer') !== null) {
            app(CustomerService::class)->find($company, (int) $this->route('customer'));
        }
        $this->attributes->set('customer_company', $company);

        return true;
    }

    public function rules(): array
    {
        $unique = Rule::unique(Customer::class, 'code')->where('company_id', (int) $this->route('company'));
        if ($this->route('customer') !== null) {
            $unique->ignore((int) $this->route('customer'));
        }

        return [
            'code' => [$this->isMethod('POST') ? 'nullable' : 'required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_-]+$/', $unique],
            'name' => ['required', 'string', 'max:255'],
            'registration_type' => ['required', Rule::enum(RegistrationType::class)],
            'ntn_cnic' => [Rule::requiredIf($this->input('registration_type') === RegistrationType::Registered->value), 'nullable', 'string', 'regex:/^(?:[0-9]{7,8}|[0-9]{13})$/'],
            'province' => ['required', Rule::in(config('erp.provinces'))],
            'city' => ['nullable', 'string', 'max:100'],
            'address' => ['required', 'string', 'max:2000'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+(). -]+$/'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
