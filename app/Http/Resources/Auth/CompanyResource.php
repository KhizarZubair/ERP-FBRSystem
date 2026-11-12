<?php

namespace App\Http\Resources\Auth;

use App\Services\FbrScenarioRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $sellerNtn = (strlen((string) $this->ntn_cnic) >= 7 && strlen((string) $this->ntn_cnic) <= 8) ? $this->ntn_cnic : null;
        $sellerCnic = $this->technical_contact ?: (strlen((string) $this->ntn_cnic) === 13 ? $this->ntn_cnic : null);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'ntn_cnic' => $this->ntn_cnic,
            'seller_ntn' => $sellerNtn,
            'seller_cnic' => $sellerCnic,
            'province' => $this->province,
            'address' => $this->address,
            'contact_person' => $this->contact_person,
            'email' => $this->email,
            'phone' => $this->phone,
            'seller_cnic' => $this->technical_contact,
            'invoice_series' => $this->invoice_series,
            'currency' => $this->currency,
            'fbr_environment' => $this->fbr_environment,
            'business_activity' => $this->business_activity ?? [],
            'sector' => $this->sector ?? [],
            'is_sales_tax_profile_retailer' => (bool) $this->is_sales_tax_profile_retailer,
            'allowed_sandbox_scenarios' => app(FbrScenarioRegistry::class)->allowedCodesForCompany($this->resource),
            'role' => $this->pivot->role,
        ];
    }
}
