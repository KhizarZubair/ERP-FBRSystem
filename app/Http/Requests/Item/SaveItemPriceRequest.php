<?php

namespace App\Http\Requests\Item;

use Illuminate\Validation\Rule;

class SaveItemPriceRequest extends ItemWorkspaceRequest
{
    /** @return array<string, array<mixed>> */
    public static function priceRules(string $prefix = ''): array
    {
        return [
            $prefix.'price_list' => ['required', Rule::in(['Standard', 'Wholesale'])],
            $prefix.'unit_price' => ['required', 'numeric', 'decimal:0,6', 'min:0', 'regex:/^[0-9]{1,14}(?:\\.[0-9]{1,6})?$/'],
            $prefix.'currency' => ['required', Rule::in(['PKR'])],
            $prefix.'effective_from' => ['required', 'date_format:Y-m-d'],
            $prefix.'effective_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:'.$prefix.'effective_from'],
            $prefix.'is_active' => ['required', 'boolean'],
        ];
    }

    public function rules(): array
    {
        return self::priceRules();
    }
}
