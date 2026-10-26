<?php

namespace App\Http\Resources;

use App\Services\InvoiceCalculator;
use App\Services\InvoiceService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = ['id' => $this->id, 'customer_id' => $this->customer_id, 'number' => $this->number,
            'invoice_type' => $this->invoice_type, 'invoice_date' => $this->invoice_date->toDateString(),
            'due_date' => $this->due_date?->toDateString(), 'scenario_id' => $this->scenario_id,
            'reference_invoice_no' => $this->reference_invoice_no, 'notes' => $this->notes, 'currency' => $this->currency,
            'status' => $this->status->value, 'fbr_status' => $this->fbr_status->value,
            'fbr_environment' => $this->fbr_environment->value, 'fbr_invoice_number' => $this->fbr_invoice_number,
            'seller_business_name' => $this->seller_business_name, 'seller_ntn_cnic' => $this->seller_ntn_cnic,
            'seller_province' => $this->seller_province, 'seller_address' => $this->seller_address,
            'buyer_business_name' => $this->buyer_business_name, 'buyer_ntn_cnic' => $this->buyer_ntn_cnic,
            'buyer_registration_type' => $this->buyer_registration_type?->value,
            'buyer_province' => $this->buyer_province, 'buyer_address' => $this->buyer_address,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'editable' => app(InvoiceService::class)->editable($this->resource)];
        foreach (InvoiceCalculator::TOTALS as $key) {
            $data[$key] = $this->$key;
        }
        if ($this->resource->relationLoaded('items')) {
            $data['version'] = app(InvoiceService::class)->version($this->resource);
            $data['lines'] = $this->items->map(fn ($line) => array_intersect_key($line->attributesToArray(), array_flip([
                'id', 'item_id', 'line_number', 'sku', 'product_description', 'hs_code', 'unit_of_measurement', 'sale_type',
                'tax_rate_label', 'tax_rate_percent', 'quantity', 'unit_price', 'fixed_notified_value_or_retail_price',
                'sro_schedule_no', 'sro_item_serial_no', ...InvoiceCalculator::TOTALS])))->all();
        }

        return $data;
    }
}
