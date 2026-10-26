<?php

namespace App\Http\Requests\Item;

class ListItemPricesRequest extends ItemWorkspaceRequest
{
    public function rules(): array
    {
        return ['page' => ['sometimes', 'integer', 'min:1', 'max:100000'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100']];
    }
}
