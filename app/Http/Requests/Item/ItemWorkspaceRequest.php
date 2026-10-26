<?php

namespace App\Http\Requests\Item;

use App\Services\CompanyService;
use App\Services\ItemPriceService;
use App\Services\ItemService;
use Illuminate\Foundation\Http\FormRequest;

class ItemWorkspaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $company = app(CompanyService::class)->workspace($this->user(), (int) $this->route('company'), ! $this->isMethodSafe());
        $this->attributes->set('item_company', $company);
        if ($this->route('item') !== null) {
            $item = app(ItemService::class)->find($company, (int) $this->route('item'));
            $this->attributes->set('workspace_item', $item);
            if ($this->route('price') !== null) {
                $this->attributes->set('workspace_price', app(ItemPriceService::class)->find($item, (int) $this->route('price')));
            }
        }

        return true;
    }

    public function rules(): array
    {
        return [];
    }
}
