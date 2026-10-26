<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Support\Facades\DB;

class OverviewService
{
    public function __construct(private StockService $stock) {}

    /** @return array<string, mixed> */
    public function summary(Company $company): array
    {
        $invoices = $company->invoices()->selectRaw("COUNT(*) AS total, SUM(status = 'draft') AS drafts, SUM(fbr_status = 'accepted') AS accepted, SUM(fbr_status IN ('validation_failed', 'rejected', 'unknown')) AS attention, COALESCE(SUM(CASE WHEN status <> 'cancelled' THEN total_values ELSE 0 END), 0) AS saved_total, COALESCE(SUM(CASE WHEN status <> 'cancelled' THEN sales_tax_applicable ELSE 0 END), 0) AS saved_tax")->first();
        $low = DB::query()->fromSub($this->stock->balancesQuery($company), 'balances')->where('is_active', true)->whereColumn('available', '<', 'reorder_level')->count();

        return ['invoice_count' => (int) $invoices->total, 'draft_count' => (int) $invoices->drafts,
            'accepted_count' => (int) $invoices->accepted, 'attention_count' => (int) $invoices->attention,
            'saved_total' => (string) $invoices->saved_total, 'saved_tax' => (string) $invoices->saved_tax,
            'customer_count' => $company->customers()->count(), 'item_count' => $company->items()->count(), 'low_stock_count' => $low,
            'recent_invoices' => $company->invoices()->withExists([
                'fbrSubmissions as has_submit_history' => fn ($query) => $query->where('operation', 'submit'),
                'items as has_stock_history' => fn ($query) => $query->whereHas('stockMovements'),
            ])->orderByDesc('invoice_date')->orderByDesc('id')->limit(5)->get()];
    }
}
