<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Item;
use App\Models\ItemPrice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ItemService
{
    public function __construct(private ItemPriceService $prices) {}

    /** @param array<string, mixed> $filters */
    public function listing(Company $company, array $filters): LengthAwarePaginator
    {
        $query = $company->items()->select('items.*');
        $today = $filters['price_date'] ?? today()->toDateString();
        $priceQuery = ItemPrice::query()->whereColumn('item_prices.item_id', 'items.id')
            ->whereColumn('item_prices.company_id', 'items.company_id')
            ->where('is_active', true)->where('price_list', $filters['price_list'] ?? 'Standard')->where('currency', 'PKR')
            ->where('effective_from', '<=', $today)
            ->where(function ($query) use ($today): void {
                $query->whereNull('effective_to')->orWhere('effective_to', '>=', $today);
            })->orderByDesc('effective_from')->orderByDesc('id')->limit(1);
        $query->addSelect(['current_unit_price' => (clone $priceQuery)->select('unit_price')])
            ->withSum(['stockMovements as on_hand' => function ($query) use ($company): void {
                $query->where('stock_movements.company_id', $company->id);
            }], 'quantity');
        if (! empty($filters['search'])) {
            $search = '%'.addcslashes($filters['search'], '%_\\').'%';
            $query->where(function ($query) use ($search): void {
                $query->where('items.name', 'like', $search)->orWhere('items.sku', 'like', $search)->orWhere('items.hs_code', 'like', $search);
            });
        }
        if (isset($filters['is_active'])) {
            $query->where('items.is_active', $filters['is_active']);
        }
        $sort = $filters['sort'] ?? 'name';
        $column = $sort === 'price' ? 'current_unit_price' : 'items.'.$sort;

        return $query->orderBy($column, $filters['direction'] ?? 'asc')->orderBy('items.id')
            ->paginate($filters['per_page'] ?? 15)->withQueryString();
    }

    public function find(Company $company, int $itemId): Item
    {
        $today = today()->toDateString();

        return $company->items()->select('items.*')
            ->addSelect(['current_unit_price' => ItemPrice::query()
                ->select('unit_price')
                ->whereColumn('item_prices.item_id', 'items.id')
                ->whereColumn('item_prices.company_id', 'items.company_id')
                ->where('price_list', 'Standard')
                ->where('currency', 'PKR')
                ->where('is_active', true)
                ->where('effective_from', '<=', $today)
                ->where(function ($query) use ($today): void {
                    $query->whereNull('effective_to')->orWhere('effective_to', '>=', $today);
                })
                ->orderByDesc('effective_from')
                ->orderByDesc('id')
                ->limit(1)])
            ->whereKey($itemId)
            ->firstOrFail();
    }

    /** @param array<string, mixed> $data */
    public function save(Company $company, array $data, ?int $itemId = null): Item
    {
        try {
            return DB::transaction(function () use ($company, $data, $itemId): Item {
                $item = $itemId === null ? $company->items()->make()
                    : $company->items()->whereKey($itemId)->lockForUpdate()->firstOrFail();
                $price = $data['price'] ?? null;
                unset($data['price']);
                if (array_key_exists('tax_rate_percent', $data) && empty($data['tax_rate_label'])) {
                    $data['tax_rate_label'] = rtrim(rtrim(number_format((float) $data['tax_rate_percent'], 6, '.', ''), '0'), '.').'%';
                }
                $item->fill($data)->save();
                if ($price !== null) {
                    $this->prices->save($item, $price);
                }

                return $item;
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['sku' => 'SKU is already in use.']);
        }
    }

    public function status(Company $company, int $itemId, bool $active): Item
    {
        return $this->save($company, ['is_active' => $active], $itemId);
    }

    public function delete(Company $company, int $itemId): void
    {
        try {
            DB::transaction(function () use ($company, $itemId): void {
                $item = $company->items()->whereKey($itemId)->lockForUpdate()->firstOrFail();
                if ($item->invoiceItems()->exists() || $item->stockMovements()->exists()) {
                    throw ValidationException::withMessages(['item' => 'This item has invoice or stock history. Deactivate it instead.']);
                }
                $item->prices()->where('company_id', $company->id)->delete();
                $item->delete();
            });
        } catch (QueryException $exception) {
            if (($exception->errorInfo[1] ?? null) === 1451) {
                throw ValidationException::withMessages(['item' => 'This item has invoice or stock history. Deactivate it instead.']);
            }
            throw $exception;
        }
    }
}
