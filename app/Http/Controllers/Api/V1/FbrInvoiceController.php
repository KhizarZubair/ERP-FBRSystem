<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\CustomerService;
use App\Services\FbrInvoiceService;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FbrInvoiceController extends Controller
{
    public function __construct(private CustomerService $access, private FbrInvoiceService $fbr, private InvoiceService $invoices) {}

    public function show(Request $request, int $company, int $invoice): JsonResponse
    {
        $workspace = $this->access->company($request->user(), $company);
        $record = $this->invoices->find($workspace, $invoice);

        return response()->json(['environments' => $this->fbr->environments($workspace), 'readiness' => $this->fbr->readiness($workspace),
            'history' => $record->fbrSubmissions()->with('items')->latest('id')->limit(20)->get()->map(fn ($submission) => $this->fbr->summary($submission))->all()]);
    }

    public function reconcile(Request $request, int $company, int $invoice): JsonResponse
    {
        $workspace = $this->access->company($request->user(), $company, true);
        $data = $request->validate(['submission_id' => ['required', 'integer'], 'outcome' => ['required', Rule::in(['accepted', 'not_accepted'])],
            'fbr_invoice_number' => ['required_if:outcome,accepted', 'nullable', 'string', 'max:100'],
            'notes' => ['required', 'string', 'min:20', 'max:4000'], 'confirmed' => ['required', 'accepted']]);

        return response()->json(['data' => $this->fbr->reconcile($workspace, $request->user(), $invoice, $data)]);
    }

    public function run(Request $request, int $company, int $invoice, string $operation): JsonResponse
    {
        $workspace = $this->access->company($request->user(), $company, true);
        $data = $request->validate(['environment' => ['required', Rule::in(['sandbox', 'production'])],
            'version' => ['required', 'string', 'size:64'], 'request_id' => ['required', 'uuid'],
            'scenario_id' => ['nullable', 'string', 'max:5'], 'confirmed' => ['required', 'accepted']]);
        abort_unless(in_array($operation, ['validate', 'submit'], true), 404);

        return response()->json(['data' => $this->fbr->run($workspace, $request->user(), $invoice, $data, $operation)]);
    }
}
