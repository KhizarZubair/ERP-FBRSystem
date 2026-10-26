<?php

namespace App\Http\Requests\Item;

class SearchHsCodesRequest extends ItemWorkspaceRequest
{
    public function rules(): array
    {
        return ['search' => ['nullable', 'string', 'max:100'], 'limit' => ['sometimes', 'integer', 'min:1', 'max:50']];
    }
}
