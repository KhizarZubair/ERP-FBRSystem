<?php

namespace App\Http\Requests\Customer;

use App\Services\CustomerService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $service = app(CustomerService::class);
        $company = $service->company($this->user(), (int) $this->route('company'), true);
        $service->find($company, (int) $this->route('customer'));
        $this->attributes->set('customer_company', $company);

        return true;
    }

    public function rules(): array
    {
        return ['is_active' => ['required', 'boolean']];
    }
}
