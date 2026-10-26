<?php

namespace App\Services;

use App\Enums\InvoiceFbrStatus;
use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceService
{
    public function __construct(private InvoiceCalculator $calculator) {}

    /** @param array<string, mixed> $filters */
    public function listing(Company $company, array $filters): LengthAwarePaginator
    {
        $query = $company->invoices()->withExists([
            'fbrSubmissions as has_submission_history',
            'fbrSubmissions as has_submit_history' => fn ($query) => $query->where('operation', 'submit'),
            'items as has_stock_history' => fn ($query) => $query->whereHas('stockMovements'),
        ]);
        if (! empty($filters['search'])) {
            $term = '%'.addcslashes($filters['search'], '%_\\').'%';
            $query->where(function ($query) use ($term): void {
                $query->where('number', 'like', $term)->orWhere('buyer_business_name', 'like', $term);
            });
        }
        foreach (['status', 'fbr_status'] as $key) {
            if (! empty($filters[$key])) {
                $query->where($key, $filters[$key]);
            }
        }
        if (! empty($filters['from'])) {
            $query->where('invoice_date', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->where('invoice_date', '<=', $filters['to']);
        }

        return $query->orderBy($filters['sort'] ?? 'invoice_date', $filters['direction'] ?? 'desc')
            ->orderByDesc('id')->paginate($filters['per_page'] ?? 15);
    }

    public function find(Company $company, int $id): Invoice
    {
        return $company->invoices()->with('items')->withExists([
            'fbrSubmissions as has_submission_history',
            'fbrSubmissions as has_submit_history' => fn ($query) => $query->where('operation', 'submit'),
            'items as has_stock_history' => fn ($query) => $query->whereHas('stockMovements'),
        ])->whereKey($id)->firstOrFail();
    }

    public function version(Invoice $invoice): string
    {
        return hash('sha256', json_encode([array_diff_key($invoice->getAttributes(), array_flip(['has_submission_history', 'has_submit_history', 'has_stock_history'])), $invoice->items->map(fn ($line) => $line->getAttributes())->all()], JSON_THROW_ON_ERROR));
    }

    public function editable(Invoice $invoice): bool
    {
        return $invoice->status === InvoiceStatus::Draft
            && in_array($invoice->fbr_status, [InvoiceFbrStatus::NotSubmitted, InvoiceFbrStatus::ValidationFailed], true)
            && ! ($invoice->getAttribute('has_submit_history') ?? $invoice->fbrSubmissions()->where('operation', 'submit')->exists())
            && ! $invoice->getAttribute('has_stock_history');
    }

    public function guardWrite(Invoice $invoice, string $version): void
    {
        abort_unless(hash_equals($this->version($invoice), $version), 409, 'Invoice changed in another session. Reload before saving.');
        if (! $this->editable($invoice) || $invoice->items()->whereHas('stockMovements')->exists()) {
            throw ValidationException::withMessages(['invoice' => 'Only editable drafts without an FBR submission or posted stock can be changed.']);
        }
    }

    /** @param array<string, mixed> $data */
    public function save(Company $company, User $user, array $data, ?int $id = null): Invoice
    {
        return DB::transaction(function () use ($company, $user, $data, $id): Invoice {
            $seller = Company::whereKey($company->id)->lockForUpdate()->firstOrFail();
            $invoice = $id === null ? $company->invoices()->make() :
                $company->invoices()->with('items')->whereKey($id)->lockForUpdate()->firstOrFail();
            if ($id !== null) {
                $this->guardWrite($invoice, $data['version']);
            }
            [$buyer, $lines] = $this->prepareLines($company, $data, $invoice, true);
            $attributes = array_intersect_key($data, array_flip(['customer_id', 'invoice_date', 'due_date', 'scenario_id', 'reference_invoice_no', 'notes']));
            if ($id === null) {
                $sequence = (int) $seller->getAttribute('next_invoice_number');
                do {
                    $number = $seller->invoice_series.'-'.str_pad((string) $sequence++, 6, '0', STR_PAD_LEFT);
                } while ($company->invoices()->where('number', $number)->exists());
                $seller->forceFill(['next_invoice_number' => $sequence])->save();
                $attributes += ['number' => $number, 'status' => 'draft', 'fbr_status' => 'not_submitted',
                    'currency' => $seller->currency, 'fbr_environment' => $seller->fbr_environment,
                    'seller_business_name' => $seller->name, 'seller_ntn_cnic' => $seller->ntn_cnic,
                    'seller_province' => $seller->province, 'seller_address' => $seller->address];
            }
            if ($id === null || $invoice->customer_id !== $buyer->id) {
                $attributes += ['buyer_business_name' => $buyer->name, 'buyer_ntn_cnic' => $buyer->ntn_cnic,
                    'buyer_registration_type' => $buyer->registration_type, 'buyer_province' => $buyer->province, 'buyer_address' => $buyer->address];
            }
            $invoice->fill([...$attributes, ...$this->calculator->totals($lines)]);
            $invoice->forceFill(['fbr_status' => InvoiceFbrStatus::NotSubmitted, 'updated_by' => $user->id,
                ...($id === null ? ['created_by' => $user->id] : [])])->save();
            if ($id !== null) {
                $invoice->items()->delete();
            }
            $timestamp = now();
            InvoiceItem::query()->insert(array_map(fn (array $line): array => [
                ...$line, 'invoice_id' => $invoice->id, 'company_id' => $company->id,
                'created_at' => $timestamp, 'updated_at' => $timestamp,
            ], $lines));

            return $invoice->fresh('items');
        }, 3);
    }

    /** @param array<string, mixed> $data @return array{0: \App\Models\Customer, 1: array<int, array<string, mixed>>} */
    private function prepareLines(Company $company, array $data, Invoice $invoice, bool $lock): array
    {
        $id = $invoice->exists ? $invoice->id : null;
        $buyerQuery = $company->customers()->whereKey($data['customer_id']);
        $buyer = ($lock ? $buyerQuery->lockForUpdate() : $buyerQuery)->firstOrFail();
        if (! $buyer->is_active && ($id === null || $invoice->customer_id !== $buyer->id)) {
            throw ValidationException::withMessages(['customer_id' => 'Select an active customer.']);
        }
        $catalogQuery = $company->items()->whereIn('id', array_column($data['lines'], 'item_id'))->orderBy('id');
        $catalog = ($lock ? $catalogQuery->lockForUpdate() : $catalogQuery)->get()->keyBy('id');
        $oldLines = $id === null ? collect() : $invoice->items->keyBy('id');
        $lines = [];
        foreach ($data['lines'] as $index => $input) {
            $item = $catalog->get($input['item_id']);
            $old = isset($input['id']) ? $oldLines->get($input['id']) : null;
            if (isset($input['id']) && ! $old) {
                throw ValidationException::withMessages(["lines.$index.id" => 'Line does not belong to this invoice.']);
            }
            if (! $item || (! $item->is_active && (! $old || $old->item_id !== $item->id))) {
                throw ValidationException::withMessages(["lines.$index.item_id" => 'Select an active item from this company.']);
            }
            $snapshot = $old && $old->item_id === $item->id ? $old : $item;
            $lines[] = [
                'company_id' => $company->id, 'item_id' => $item->id, 'line_number' => $index + 1,
                'sku' => $snapshot->sku, 'product_description' => $old && $old->item_id === $item->id ? $old->product_description : $item->description,
                'hs_code' => $snapshot->hs_code, 'unit_of_measurement' => $snapshot->unit_of_measurement,
                'sale_type' => $snapshot->sale_type, 'tax_rate_label' => $input['tax_rate_label'] ?? $input['tax_rate_percent'].'%',
                'fixed_notified_value_or_retail_price' => $input['fixed_notified_value_or_retail_price'] ?? $snapshot->fixed_notified_value_or_retail_price ?? '0',
                'sro_schedule_no' => $input['sro_schedule_no'] ?? $snapshot->sro_schedule_no,
                'sro_item_serial_no' => $input['sro_item_serial_no'] ?? $snapshot->sro_item_serial_no,
                ...$this->calculator->line($input, $index),
            ];
        }

        return [$buyer, $lines];
    }

    /** @param array<string, mixed> $data */
    public function preview(Company $company, array $data): Invoice
    {
        $seller = Company::whereKey($company->id)->firstOrFail();
        $original = ! empty($data['invoice_id']) ? $this->find($company, (int) $data['invoice_id']) : null;
        if ($original !== null) {
            $this->guardWrite($original, $data['version'] ?? '');
        }
        [$buyer, $lines] = $this->prepareLines($company, $data, $original ?? $company->invoices()->make(), false);
        $sameBuyer = $original !== null && $original->customer_id === $buyer->id;
        $invoice = $company->invoices()->make([
            'customer_id' => $buyer->id, 'number' => $original?->number ?? 'Assigned on save',
            'invoice_type' => 'Sale Invoice', 'invoice_date' => $data['invoice_date'], 'due_date' => $data['due_date'] ?? null,
            'scenario_id' => $data['scenario_id'] ?? null, 'reference_invoice_no' => $data['reference_invoice_no'] ?? null,
            'notes' => $data['notes'] ?? null, 'currency' => $original?->currency ?? $seller->currency,
            'status' => 'draft', 'fbr_status' => 'not_submitted', 'fbr_environment' => $original?->fbr_environment ?? $seller->fbr_environment,
            'seller_business_name' => $original !== null ? $original->seller_business_name : $seller->name,
            'seller_ntn_cnic' => $original !== null ? $original->seller_ntn_cnic : $seller->ntn_cnic,
            'seller_province' => $original !== null ? $original->seller_province : $seller->province,
            'seller_address' => $original !== null ? $original->seller_address : $seller->address,
            'buyer_business_name' => $sameBuyer ? $original->buyer_business_name : $buyer->name,
            'buyer_ntn_cnic' => $sameBuyer ? $original->buyer_ntn_cnic : $buyer->ntn_cnic,
            'buyer_registration_type' => $sameBuyer ? $original->buyer_registration_type : $buyer->registration_type,
            'buyer_province' => $sameBuyer ? $original->buyer_province : $buyer->province,
            'buyer_address' => $sameBuyer ? $original->buyer_address : $buyer->address,
            ...$this->calculator->totals($lines),
        ]);
        $invoice->setRelation('items', new Collection(array_map(fn (array $line): InvoiceItem => new InvoiceItem($line), $lines)));

        return $invoice;
    }

    public function approve(Company $company, User $user, int $id, string $version): Invoice
    {
        return DB::transaction(function () use ($company, $user, $id, $version): Invoice {
            Company::whereKey($company->id)->lockForUpdate()->firstOrFail();
            $invoice = $company->invoices()->with('items')->whereKey($id)->lockForUpdate()->firstOrFail();
            $this->guardWrite($invoice, $version);
            if ($invoice->items->isEmpty()) {
                throw ValidationException::withMessages(['invoice' => 'An invoice needs at least one saved line before approval.']);
            }
            $invoice->forceFill(['status' => InvoiceStatus::Approved, 'approved_by' => $user->id,
                'approved_at' => now(), 'updated_by' => $user->id])->save();

            return $invoice->fresh('items');
        }, 3);
    }

    public function reopenAfterValidationFailure(Company $company, User $user, int $id, string $version): Invoice
    {
        return DB::transaction(function () use ($company, $user, $id, $version): Invoice {
            Company::whereKey($company->id)->lockForUpdate()->firstOrFail();
            $invoice = $company->invoices()->with('items')->whereKey($id)->lockForUpdate()->firstOrFail();
            abort_unless(hash_equals($this->version($invoice), $version), 409, 'Invoice changed in another session. Reload before reopening.');
            abort_unless($invoice->status === InvoiceStatus::Approved && $invoice->fbr_status === InvoiceFbrStatus::ValidationFailed, 422,
                'Only an approved invoice rejected by FBR validation can be reopened.');
            abort_if($invoice->fbrSubmissions()->where('operation', 'submit')->exists(), 409,
                'An invoice with an FBR submission attempt cannot be reopened.');
            abort_if($invoice->items()->whereHas('stockMovements')->exists(), 409,
                'An invoice with posted stock cannot be reopened.');
            $latestValidation = $invoice->fbrSubmissions()->where('operation', 'validate')->latest('id')->first();
            abort_unless($latestValidation?->status->value === 'rejected', 422,
                'The latest FBR validation must be rejected before reopening.');
            $invoice->forceFill([
                'status' => InvoiceStatus::Draft,
                'approved_by' => null,
                'approved_at' => null,
                'updated_by' => $user->id,
            ])->save();

            return $invoice->fresh('items')->loadExists([
                'fbrSubmissions as has_submission_history',
                'items as has_stock_history' => fn ($query) => $query->whereHas('stockMovements'),
            ]);
        }, 3);
    }

    public function delete(Company $company, int $id, string $version): void
    {
        DB::transaction(function () use ($company, $id, $version): void {
            Company::whereKey($company->id)->lockForUpdate()->firstOrFail();
            $invoice = $company->invoices()->with('items')->whereKey($id)->lockForUpdate()->firstOrFail();
            $this->guardWrite($invoice, $version);
            if ($invoice->fbrSubmissions()->exists()) {
                throw ValidationException::withMessages(['invoice' => 'An invoice with FBR audit history cannot be deleted.']);
            }
            $invoice->items()->delete();
            $invoice->delete();
        }, 3);
    }
}
