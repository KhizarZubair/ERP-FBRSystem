<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'code' => $this->code, 'name' => $this->name,
            'registration_type' => $this->registration_type->value,
            'ntn' => strlen((string) $this->ntn_cnic) <= 8 ? $this->ntn_cnic : null,
            'cnic' => $this->cnic ?? (strlen((string) $this->ntn_cnic) === 13 ? $this->ntn_cnic : null),
            'ntn_cnic' => $this->ntn_cnic,
            'province' => $this->province, 'city' => $this->city, 'address' => $this->address,
            'email' => $this->email, 'phone' => $this->phone, 'is_active' => $this->is_active,
        ];
    }
}
