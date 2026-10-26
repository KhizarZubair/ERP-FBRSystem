<?php

namespace App\Services;

use App\Models\Company;
use App\Models\StockMovement;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StockService
{
    public function balancesQuery(Company $company): Builder
    {
        $sums = DB::table('stock_movements')->where('company_id', $company->id)->groupBy('item_id')
            ->selectRaw("item_id, SUM(quantity) AS available, SUM(CASE WHEN movement_type = 'opening' THEN quantity ELSE 0 END) AS opening, SUM(CASE WHEN movement_type = 'receipt' THEN quantity ELSE 0 END) AS received, SUM(CASE WHEN movement_type = 'issue' THEN -quantity ELSE 0 END) AS issued, SUM(CASE WHEN movement_type IN ('adjustment','reversal') THEN quantity ELSE 0 END) AS adjusted");

        return DB::table('items')->where('items.company_id', $company->id)->where('items.track_stock', true)
            ->leftJoinSub($sums, 'totals', 'totals.item_id', '=', 'items.id')
            ->select(['items.id', 'items.name', 'items.sku', 'items.unit_of_measurement', 'items.reorder_level', 'items.is_active'])
            ->selectRaw('COALESCE(available, 0) AS available, COALESCE(opening, 0) AS opening, COALESCE(received, 0) AS received, COALESCE(issued, 0) AS issued, COALESCE(adjusted, 0) AS adjusted');
    }

    /** @param array<string, mixed> $filters */
    public function balances(Company $company, array $filters): LengthAwarePaginator
    {
        $query = $this->balancesQuery($company);
        if (! empty($filters['item_id'])) {
            $query->where('items.id', $filters['item_id']);
        }
        if (! empty($filters['search'])) {
            $term = '%'.addcslashes($filters['search'], '%_\\').'%';
            $query->where(function ($query) use ($term): void {
                $query->where('items.name', 'like', $term)->orWhere('items.sku', 'like', $term);
            });
        }

        return $query->orderBy('items.name')->orderBy('items.id')->paginate($filters['per_page'] ?? 15);
    }

    /** @param array<string, mixed> $filters */
    public function history(Company $company, array $filters): LengthAwarePaginator
    {
        $ledger = DB::table('stock_movements')->where('company_id', $company->id)->select('stock_movements.*')
            ->selectRaw('SUM(quantity) OVER (PARTITION BY item_id ORDER BY movement_date, id ROWS UNBOUNDED PRECEDING) AS running_balance');
        if (! empty($filters['item_id'])) {
            $ledger->where('item_id', $filters['item_id']);
        }
        $query = DB::query()->fromSub($ledger, 'ledger')->join('items', 'items.id', '=', 'ledger.item_id')
            ->where('items.company_id', $company->id)->select('ledger.id', 'ledger.item_id', 'ledger.movement_date', 'ledger.document_number', 'ledger.movement_type', 'ledger.quantity', 'ledger.notes', 'ledger.invoice_item_id', 'ledger.reversal_of_id', 'ledger.running_balance', 'items.name as item_name', 'items.sku', 'items.unit_of_measurement')
            ->selectSub(DB::table('stock_movements as reversals')->select('id')->whereColumn('reversals.reversal_of_id', 'ledger.id')->where('reversals.company_id', $company->id)->limit(1), 'reversed_by_id');
        if (! empty($filters['from'])) {
            $query->where('ledger.movement_date', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->where('ledger.movement_date', '<=', $filters['to']);
        }
        if (! empty($filters['movement_type'])) {
            $query->where('ledger.movement_type', $filters['movement_type']);
        }

        return $query->orderByDesc('ledger.movement_date')->orderByDesc('ledger.id')->paginate($filters['per_page'] ?? 15);
    }

    /** @param array<string, mixed> $data */
    public function post(Company $company, User $user, array $data): StockMovement
    {
        try {
            return DB::transaction(function () use ($company, $user, $data): StockMovement {
                $item = $company->items()->whereKey($data['item_id'])->lockForUpdate()->firstOrFail();
                if (! $item->is_active || ! $item->track_stock) {
                    throw ValidationException::withMessages(['item_id' => 'Select an active stock-tracked item.']);
                }
                $quantity = BigDecimal::of((string) $data['quantity'])->toScale(6);
                if ($quantity->isZero() || (in_array($data['movement_type'], ['opening', 'receipt', 'issue'], true) && $quantity->isNegative())) {
                    throw ValidationException::withMessages(['quantity' => 'Opening, receipt and issue quantities must be positive; adjustments may be signed.']);
                }
                if ($data['movement_type'] === 'issue') {
                    $quantity = $quantity->negated();
                }
                $existing = $company->stockMovements()->where('idempotency_key', $data['idempotency_key'])->first();
                if ($existing) {
                    $same = $existing->item_id === $item->id && $existing->movement_type->value === $data['movement_type']
                        && $existing->movement_date->toDateString() === $data['movement_date']
                        && $existing->document_number === $data['document_number']
                        && BigDecimal::of($existing->quantity)->isEqualTo($quantity)
                        && ($existing->notes ?? '') === ($data['notes'] ?? '');
                    abort_unless($same, 409, 'This request key already belongs to a different stock entry.');

                    return $existing;
                }
                if ($data['movement_type'] === 'opening' && $item->stockMovements()->exists()) {
                    throw ValidationException::withMessages(['movement_type' => 'Opening stock must be the first movement. Use an adjustment for corrections.']);
                }
                $movement = $company->stockMovements()->make([...$data, 'quantity' => (string) $quantity]);
                $movement->forceFill(['created_by' => $user->id])->save();

                return $movement;
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            abort(409, 'Stock request key is already in use. Reload and check the saved entry before retrying.');
        }
    }

    public function reverse(Company $company, User $user, int $id, string $notes): StockMovement
    {
        return DB::transaction(function () use ($company, $user, $id, $notes): StockMovement {
            $original = $company->stockMovements()->whereKey($id)->firstOrFail();
            $company->items()->whereKey($original->item_id)->lockForUpdate()->firstOrFail();
            $original = $company->stockMovements()->whereKey($id)->lockForUpdate()->firstOrFail();
            if ($original->movement_type->value === 'reversal' || $original->invoice_item_id !== null) {
                throw ValidationException::withMessages(['movement' => 'Invoice-linked movements and reversals cannot be manually reversed.']);
            }
            if ($existing = $original->reversal()->first()) {
                return $existing;
            }
            $movement = $company->stockMovements()->make([
                'item_id' => $original->item_id, 'movement_date' => max(today()->toDateString(), $original->movement_date->toDateString()),
                'document_number' => 'REV-'.$original->id, 'movement_type' => 'reversal',
                'quantity' => (string) BigDecimal::of($original->quantity)->negated()->toScale(6),
                'reversal_of_id' => $original->id, 'idempotency_key' => (string) Str::uuid(), 'notes' => $notes,
            ]);
            $movement->forceFill(['created_by' => $user->id])->save();

            return $movement;
        }, 3);
    }
}
