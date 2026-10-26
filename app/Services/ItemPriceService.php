<?php

namespace App\Services;

use App\Models\Item;
use App\Models\ItemPrice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ItemPriceService
{
    public function listing(Item $item, int $perPage = 15): LengthAwarePaginator
    {
        return $item->prices()->where('company_id', $item->company_id)->orderByDesc('effective_from')->orderByDesc('id')->paginate($perPage)->withQueryString();
    }

    public function find(Item $item, int $priceId): ItemPrice
    {
        return $item->prices()->where('company_id', $item->company_id)->whereKey($priceId)->firstOrFail();
    }

    /** @param array<string, mixed> $data */
    public function save(Item $item, array $data, ?int $priceId = null): ItemPrice
    {
        try {
            return DB::transaction(function () use ($item, $data, $priceId): ItemPrice {
                Item::whereKey($item->id)->where('company_id', $item->company_id)->lockForUpdate()->firstOrFail();
                $price = $priceId === null ? $item->prices()->make()
                    : $item->prices()->where('company_id', $item->company_id)->whereKey($priceId)->lockForUpdate()->firstOrFail();
                if ($data['is_active']) {
                    $overlap = $item->prices()->where('company_id', $item->company_id)->where('price_list', $data['price_list'])
                        ->where('currency', $data['currency'])->where('is_active', true)
                        ->where(function ($query) use ($data): void {
                            $query->whereNull('effective_to')->orWhere('effective_to', '>=', $data['effective_from']);
                        });
                    if (! empty($data['effective_to'])) {
                        $overlap->where('effective_from', '<=', $data['effective_to']);
                    }
                    if ($priceId !== null) {
                        $overlap->where('id', '!=', $priceId);
                    }
                    if ($overlap->exists()) {
                        throw ValidationException::withMessages(['effective_from' => 'An active price already covers these dates for this price list. End or deactivate that price first.']);
                    }
                }
                $price->company_id = $item->company_id;
                $price->fill($data)->save();

                return $price;
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['effective_from' => 'A price with this list, currency and start date already exists.']);
        }
    }

    public function delete(Item $item, int $priceId): void
    {
        DB::transaction(function () use ($item, $priceId): void {
            Item::whereKey($item->id)->where('company_id', $item->company_id)->lockForUpdate()->firstOrFail();
            $this->find($item, $priceId)->delete();
        });
    }
}
