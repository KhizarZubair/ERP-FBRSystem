<?php

namespace App\Http\Requests\Item;

use App\Models\Item;
use App\Services\FbrReferenceService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveItemRequest extends ItemWorkspaceRequest
{
    protected function prepareForValidation(): void
    {
        $merge = [];
        foreach (['sro_schedule_no', 'sro_item_serial_no'] as $sroField) {
            if ($this->has($sroField) && (trim((string) $this->input($sroField)) === '' || $this->input($sroField) === null)) {
                $merge[$sroField] = null;
            }
        }
        foreach (['fixed_notified_value_or_retail_price', 'extra_tax', 'fed_payable'] as $numField) {
            if ($this->has($numField) && (trim((string) $this->input($numField)) === '' || $this->input($numField) === null)) {
                $merge[$numField] = '0';
            }
        }
        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    public function rules(): array
    {
        $unique = Rule::unique(Item::class, 'sku')->where('company_id', (int) $this->route('company'));
        if ($this->route('item') !== null) {
            $unique->ignore((int) $this->route('item'));
        }
        $rules = [
            'sku' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z0-9_-]+$/', $unique],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:4000'],
            'hs_code' => ['required', 'string', 'regex:/^[0-9]{4}\.[0-9]{4}$/'],
            'unit_of_measurement' => ['required', 'string', 'max:150'],
            'sale_type' => ['required', 'string', 'max:255'],
            'tax_rate_label' => ['sometimes', 'string', 'max:100'],
            'extra_tax' => ['sometimes', 'numeric', 'decimal:0,6', 'min:0', 'max:99999999999999'],
            'fed_payable' => ['sometimes', 'numeric', 'decimal:0,6', 'min:0', 'max:99999999999999'],
            'tax_rate_percent' => ['required', 'numeric', 'decimal:0,6', 'between:0,100'],
            'fixed_notified_value_or_retail_price' => ['required', 'numeric', 'decimal:0,6', 'min:0', 'regex:/^[0-9]{1,14}(?:\\.[0-9]{1,6})?$/'],
            'sro_schedule_no' => ['nullable', 'string', 'max:100'],
            'sro_item_serial_no' => ['nullable', 'string', 'max:100'],
            'track_stock' => ['required', 'boolean'],
            'reorder_level' => ['required', 'numeric', 'decimal:0,6', 'min:0', 'regex:/^[0-9]{1,14}(?:\\.[0-9]{1,6})?$/'],
            'is_active' => ['required', 'boolean'],
        ];
        $rules['price'] = [$this->isMethod('POST') ? 'required' : 'sometimes', 'array:price_list,unit_price,currency,effective_from,effective_to,is_active'];
        if ($this->has('price')) {
            $rules = array_merge($rules, SaveItemPriceRequest::priceRules('price.'));
            $rules['price.unit_price'][3] = 'min:0.01';
        }

        return $rules;
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || ! $this->filled('hs_code')) {
                return;
            }
            $existing = $this->attributes->get('workspace_item');
            if ($existing !== null && $existing->hs_code === $this->input('hs_code') &&
                $existing->unit_of_measurement === $this->input('unit_of_measurement')) {
                return;
            }
            $references = app(FbrReferenceService::class);
            $company = $this->attributes->get('item_company');
            $references->validateHsCode($company, (string) $this->input('hs_code'));
            $references->validateHsUnit($company, (string) $this->input('hs_code'), (string) $this->input('unit_of_measurement'));
        }];
    }
}
