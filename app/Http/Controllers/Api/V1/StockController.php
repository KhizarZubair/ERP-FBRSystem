<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\ListStockRequest;
use App\Http\Requests\Stock\SaveStockMovementRequest;
use App\Http\Resources\StockMovementResource;
use App\Services\CustomerService;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockController extends Controller
{
    public function __construct(private StockService $stock, private CustomerService $access) {}

    public function balances(ListStockRequest $request, int $company): JsonResponse
    {
        return response()->json($this->stock->balances($request->attributes->get('workspace'), $request->validated()));
    }

    public function index(ListStockRequest $request, int $company): JsonResponse
    {
        return response()->json($this->stock->history($request->attributes->get('workspace'), $request->validated()));
    }

    public function store(SaveStockMovementRequest $request, int $company): JsonResponse
    {
        return (new StockMovementResource($this->stock->post($request->attributes->get('workspace'), $request->user(), $request->validated())))->response()->setStatusCode(201);
    }

    public function reverse(Request $request, int $company, int $movement): JsonResponse
    {
        $workspace = $this->access->company($request->user(), $company, true);
        $data = $request->validate(['notes' => ['required', 'string', 'max:5000']]);

        return (new StockMovementResource($this->stock->reverse($workspace, $request->user(), $movement, $data['notes'])))->response()->setStatusCode(200);
    }
}
