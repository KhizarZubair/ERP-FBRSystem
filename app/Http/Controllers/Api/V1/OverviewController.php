<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Services\CustomerService;
use App\Services\OverviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OverviewController extends Controller
{
    public function __construct(private OverviewService $overview, private CustomerService $access) {}

    public function show(Request $request, int $company): JsonResponse
    {
        $data = $this->overview->summary($this->access->company($request->user(), $company));
        $data['recent_invoices'] = InvoiceResource::collection($data['recent_invoices']);

        return response()->json(['data' => $data]);
    }
}
