<?php

namespace App\Http\Requests\Item;

class UpdateItemStatusRequest extends ItemWorkspaceRequest
{
    public function rules(): array
    {
        return ['is_active' => ['required', 'boolean']];
    }
}
