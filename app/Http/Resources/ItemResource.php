<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'sku' => $this->sku, 'name' => $this->name, 'description' => $this->description,
            'hs_code' => $this->hs_code, 'unit_of_measurement' => $this->unit_of_measurement,
            'sale_type' => $this->sale_type, 'tax_rate_label' => $this->tax_rate_label, 'tax_rate_percent' => $this->tax_rate_percent,
            'extra_tax' => $this->extra_tax, 'fed_payable' => $this->fed_payable,
            'fixed_notified_value_or_retail_price' => $this->fixed_notified_value_or_retail_price,
            'sro_schedule_no' => $this->sro_schedule_no, 'sro_item_serial_no' => $this->sro_item_serial_no,
            'track_stock' => $this->track_stock, 'reorder_level' => $this->reorder_level, 'is_active' => $this->is_active,
            'current_unit_price' => $this->when($this->resource->hasAttribute('current_unit_price'), fn () => $this->current_unit_price),
            'on_hand' => $this->when($this->resource->hasAttribute('on_hand'), fn () => (string) ($this->on_hand ?? '0')),
        ];
    }
}
