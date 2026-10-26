<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemPriceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'price_list' => $this->price_list, 'unit_price' => $this->unit_price,
            'currency' => $this->currency, 'effective_from' => $this->effective_from->toDateString(),
            'effective_to' => $this->effective_to?->toDateString(), 'is_active' => $this->is_active];
    }
}
