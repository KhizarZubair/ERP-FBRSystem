<?php

namespace App\Http\Requests\Invoice;

use App\Services\CustomerService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $this->attributes->set('workspace', app(CustomerService::class)->company($this->user(), (int) $this->route('company'), true));

        return true;
    }

    public function rules(): array
    {
        $company = $this->attributes->get('workspace');
        $allowedScenarios = $company->allowed_sandbox_scenarios ?? [];
        $amount = ['required', 'numeric', 'decimal:0,6', 'min:0', 'max:999999999'];
        $optional = ['nullable', 'numeric', 'decimal:0,6', 'min:0', 'max:999999999'];

        return [
            'invoice_id' => ['nullable', 'integer', 'min:1'],
            'customer_id' => ['required', 'integer', 'min:1'],
            'invoice_date' => ['required', 'date_format:Y-m-d'],
            'due_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:invoice_date'],
            'scenario_id' => [
                Rule::requiredIf($company->fbr_environment->value === 'sandbox' && $allowedScenarios !== []),
                'nullable', 'string', Rule::in($allowedScenarios),
            ],
            'reference_invoice_no' => ['nullable', 'string', 'max:100'], 'notes' => ['nullable', 'string', 'max:5000'],
            'version' => [$this->isMethod('POST') ? 'nullable' : 'required', 'string', 'size:64'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.id' => ['nullable', 'integer', 'min:1', 'distinct'],
            'lines.*.item_id' => ['required', 'integer', 'min:1'],
            'lines.*.quantity' => ['required', 'numeric', 'decimal:0,6', 'min:0.000001', 'max:999999999'],
            'lines.*.unit_price' => $amount,
            'lines.*.tax_rate_percent' => ['required', 'numeric', 'decimal:0,6', 'min:0', 'max:100'],
            'lines.*.tax_rate_label' => ['nullable', 'string', 'max:100'],
            'lines.*.discount' => $optional, 'lines.*.sales_tax_withheld_at_source' => $optional,
            'lines.*.extra_tax' => $optional, 'lines.*.further_tax' => $optional, 'lines.*.fed_payable' => $optional,
            'lines.*.fixed_notified_value_or_retail_price' => $optional,
            'lines.*.sro_schedule_no' => ['nullable', 'string', 'max:100'],
            'lines.*.sro_item_serial_no' => ['nullable', 'string', 'max:100'],
        ];
    }
}
