<?php

namespace App\Http\Requests\Item;

class SearchItemReferencesRequest extends SearchHsCodesRequest
{
    public function rules(): array
    {
        return [...parent::rules(), 'hs_code' => ['nullable', 'string', 'regex:/^[0-9]{4}\.[0-9]{4}$/']];
    }
}
