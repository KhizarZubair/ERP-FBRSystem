<?php

namespace App\Http\Requests\Item;

use Illuminate\Validation\Rule;

class ListItemsRequest extends ItemWorkspaceRequest
{
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
            'price_list' => ['sometimes', Rule::in(['Standard', 'Wholesale'])],
            'page' => ['sometimes', 'integer', 'min:1', 'max:100000'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort' => ['sometimes', Rule::in(['name', 'sku', 'hs_code', 'price', 'id'])],
            'direction' => ['sometimes', Rule::in(['asc', 'desc'])],
        ];
    }
}
