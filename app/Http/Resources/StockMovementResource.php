<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'item_id' => $this->item_id, 'movement_date' => $this->movement_date->toDateString(),
            'movement_type' => $this->movement_type->value, 'document_number' => $this->document_number,
            'quantity' => $this->quantity, 'notes' => $this->notes, 'reversal_of_id' => $this->reversal_of_id];
    }
}
