<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyDetailsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $credentials = [];
        foreach (['sandbox', 'production'] as $environment) {
            $integration = $this->fbrIntegrations->first(fn ($row) => $row->environment->value === $environment);
            $credentials[$environment] = ['configured' => $integration && $integration->getRawOriginal('token') !== null, 'status' => $integration?->status->value ?? 'not_configured'];
        }

        return [
            'id' => $this->id, 'name' => $this->name, 'ntn_cnic' => $this->ntn_cnic, 'seller_cnic' => $this->technical_contact, 'province' => $this->province, 'address' => $this->address,
            'contact_person' => $this->contact_person, 'email' => $this->email, 'phone' => $this->phone,
            'invoice_series' => $this->invoice_series, 'currency' => $this->currency, 'fbr_environment' => $this->fbr_environment->value,
            'business_activity' => $this->business_activity, 'sector' => $this->sector,
            'allowed_sandbox_scenarios' => $this->allowed_sandbox_scenarios ?? [],
            'supported_scenarios' => config('fbr.supported_scenarios'),
            'business_activities' => config('fbr.business_activities'), 'sectors' => config('fbr.sectors'),
            'can_edit' => $this->pivot->role->value === 'owner', 'credentials' => $credentials,
        ];
    }
}
