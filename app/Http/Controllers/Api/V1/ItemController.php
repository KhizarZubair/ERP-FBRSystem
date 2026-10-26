<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Item\ItemWorkspaceRequest;
use App\Http\Requests\Item\ListItemsRequest;
use App\Http\Requests\Item\SaveItemRequest;
use App\Http\Requests\Item\UpdateItemStatusRequest;
use App\Http\Resources\ItemResource;
use App\Services\ItemService;
use Illuminate\Http\JsonResponse;

class ItemController extends Controller
{
    public function __construct(private ItemService $items) {}

    public function index(ListItemsRequest $request): JsonResponse
    {
        return ItemResource::collection($this->items->listing($request->attributes->get('item_company'), $request->validated()))->response();
    }

    public function show(ItemWorkspaceRequest $request): JsonResponse
    {
        return (new ItemResource($request->attributes->get('workspace_item')))->response();
    }

    public function store(SaveItemRequest $request): JsonResponse
    {
        return (new ItemResource($this->items->save($request->attributes->get('item_company'), $request->validated())))->response()->setStatusCode(201);
    }

    public function update(SaveItemRequest $request, int $company, int $item): JsonResponse
    {
        return (new ItemResource($this->items->save($request->attributes->get('item_company'), $request->validated(), $item)))->response();
    }

    public function status(UpdateItemStatusRequest $request, int $company, int $item): JsonResponse
    {
        return (new ItemResource($this->items->status($request->attributes->get('item_company'), $item, $request->boolean('is_active'))))->response();
    }

    public function destroy(ItemWorkspaceRequest $request, int $company, int $item): JsonResponse
    {
        $this->items->delete($request->attributes->get('item_company'), $item);

        return response()->json(['message' => 'Item deleted successfully.']);
    }
}
