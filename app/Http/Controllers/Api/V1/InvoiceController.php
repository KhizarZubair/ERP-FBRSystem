<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invoice\ApproveInvoiceRequest;
use App\Http\Requests\Invoice\ListInvoicesRequest;
use App\Http\Requests\Invoice\SaveInvoiceRequest;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\ItemResource;
use App\Services\CustomerService;
use App\Services\InvoiceService;
use App\Services\ItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InvoiceController extends Controller
{
    public function __construct(private InvoiceService $invoices, private CustomerService $access) {}

    public function lookups(Request $request, int $company, string $kind): JsonResponse
    {
        $workspace = $this->access->company($request->user(), $company);
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000000'], 'price_date' => ['nullable', 'date_format:Y-m-d'],
            'price_list' => ['nullable', Rule::in(['Standard', 'Wholesale'])]]);
        $filters['per_page'] = 20;
        if ($kind === 'customers') {
            $result = $this->access->listing($workspace, $filters);

            return CustomerResource::collection($result)->response();
        }
        abort_unless($kind === 'items', 404);
        $filters['is_active'] = true;

        return ItemResource::collection(app(ItemService::class)->listing($workspace, $filters))->response();
    }

    public function index(ListInvoicesRequest $request, int $company): JsonResponse
    {
        return InvoiceResource::collection($this->invoices->listing($request->attributes->get('workspace'), $request->validated()))->response();
    }

    public function show(Request $request, int $company, int $invoice): JsonResponse
    {
        return (new InvoiceResource($this->invoices->find($this->access->company($request->user(), $company), $invoice)))->response();
    }

    public function preview(SaveInvoiceRequest $request, int $company): JsonResponse
    {
        return (new InvoiceResource($this->invoices->preview($request->attributes->get('workspace'), $request->validated())))->response()->setStatusCode(200);
    }

    public function approve(ApproveInvoiceRequest $request, int $company, int $invoice): JsonResponse
    {
        return (new InvoiceResource($this->invoices->approve($request->attributes->get('workspace'), $request->user(), $invoice, $request->validated('version'))))->response()->setStatusCode(200);
    }

    public function reopen(Request $request, int $company, int $invoice): JsonResponse
    {
        $workspace = $this->access->company($request->user(), $company, true);
        $data = $request->validate(['version' => ['required', 'string', 'size:64'], 'confirmed' => ['required', 'accepted']]);

        return (new InvoiceResource($this->invoices->reopenAfterValidationFailure(
            $workspace, $request->user(), $invoice, $data['version']
        )))->response();
    }

    public function store(SaveInvoiceRequest $request, int $company): JsonResponse
    {
        return (new InvoiceResource($this->invoices->save($request->attributes->get('workspace'), $request->user(), $request->validated())))->response()->setStatusCode(201);
    }

    public function update(SaveInvoiceRequest $request, int $company, int $invoice): JsonResponse
    {
        return (new InvoiceResource($this->invoices->save($request->attributes->get('workspace'), $request->user(), $request->validated(), $invoice)))->response();
    }

    public function destroy(Request $request, int $company, int $invoice): JsonResponse
    {
        $workspace = $this->access->company($request->user(), $company, true);
        $data = $request->validate(['version' => ['required', 'string', 'size:64']]);
        $this->invoices->delete($workspace, $invoice, $data['version']);

        return response()->json(['message' => 'Draft invoice deleted.']);
    }
}
