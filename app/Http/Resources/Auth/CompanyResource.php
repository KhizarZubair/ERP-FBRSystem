<?php

namespace App\Http\Resources\Auth;

use App\Services\FbrScenarioRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'ntn_cnic' => $this->ntn_cnic,
            'province' => $this->province,
            'address' => $this->address,
            'contact_person' => $this->contact_person,
            'email' => $this->email,
            'phone' => $this->phone,
            'seller_cnic' => $this->technical_contact,
            'invoice_series' => $this->invoice_series,
            'currency' => $this->currency,
            'fbr_environment' => $this->fbr_environment,
            'business_activity' => $this->business_activity,
            'sector' => $this->sector,
            'allowed_sandbox_scenarios' => app(FbrScenarioRegistry::class)->allowedCodesForCompany($this->resource),
            'role' => $this->pivot->role,
        ];
    }
}
