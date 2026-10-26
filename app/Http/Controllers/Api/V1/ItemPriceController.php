<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Item\ItemWorkspaceRequest;
use App\Http\Requests\Item\ListItemPricesRequest;
use App\Http\Requests\Item\SaveItemPriceRequest;
use App\Http\Resources\ItemPriceResource;
use App\Services\ItemPriceService;
use Illuminate\Http\JsonResponse;

class ItemPriceController extends Controller
{
    public function __construct(private ItemPriceService $prices) {}

    public function index(ListItemPricesRequest $request): JsonResponse
    {
        return ItemPriceResource::collection($this->prices->listing($request->attributes->get('workspace_item'), (int) $request->input('per_page', 15)))->response();
    }

    public function show(ItemWorkspaceRequest $request): JsonResponse
    {
        return (new ItemPriceResource($request->attributes->get('workspace_price')))->response();
    }

    public function store(SaveItemPriceRequest $request): JsonResponse
    {
        return (new ItemPriceResource($this->prices->save($request->attributes->get('workspace_item'), $request->validated())))->response()->setStatusCode(201);
    }

    public function update(SaveItemPriceRequest $request, int $company, int $item, int $price): JsonResponse
    {
        return (new ItemPriceResource($this->prices->save($request->attributes->get('workspace_item'), $request->validated(), $price)))->response();
    }

    public function destroy(ItemWorkspaceRequest $request, int $company, int $item, int $price): JsonResponse
    {
        $this->prices->delete($request->attributes->get('workspace_item'), $price);

        return response()->json(['message' => 'Price deleted successfully.']);
    }
}
