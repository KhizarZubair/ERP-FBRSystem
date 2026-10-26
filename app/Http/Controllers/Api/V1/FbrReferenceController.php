<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Item\SearchHsCodesRequest;
use App\Http\Requests\Item\SearchItemReferencesRequest;
use App\Services\CustomerService;
use App\Services\FbrReferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FbrReferenceController extends Controller
{
    public function __construct(private FbrReferenceService $references, private CustomerService $access) {}

    public function hsCodes(SearchHsCodesRequest $request): JsonResponse
    {
        return response()->json($this->references->searchHsCodes(
            $request->attributes->get('item_company'), (string) $request->input('search', ''), (int) $request->input('limit', 30)
        ));
    }

    public function units(SearchItemReferencesRequest $request): JsonResponse
    {
        $catalog = $this->references->units($request->attributes->get('item_company'), $request->input('hs_code'));

        return response()->json($this->references->searchCatalog($catalog, (string) $request->input('search', ''), (int) $request->input('limit', 30)));
    }

    public function saleTypes(SearchItemReferencesRequest $request): JsonResponse
    {
        $catalog = $this->references->saleTypes($request->attributes->get('item_company'));

        return response()->json($this->references->searchCatalog($catalog, (string) $request->input('search', ''), (int) $request->input('limit', 30)));
    }

    public function provinces(SearchItemReferencesRequest $request): JsonResponse
    {
        return response()->json($this->references->searchCatalog(
            $this->references->provinces($request->attributes->get('item_company')),
            (string) $request->input('search', ''),
            (int) $request->input('limit', 30)
        ));
    }

    public function rates(Request $request, int $company): JsonResponse
    {
        $workspace = $this->access->company($request->user(), $company);
        $data = $request->validate([
            'date' => ['required', 'date_format:d-M-Y'],
            'transaction_type_id' => ['required', 'integer', 'min:1'],
            'province_id' => ['required', 'integer', 'min:1'],
        ]);

        return response()->json($this->references->searchCatalog($this->references->rates(
            $workspace, $data['date'], $data['transaction_type_id'], $data['province_id']
        )));
    }

    public function sroSchedules(Request $request, int $company): JsonResponse
    {
        $workspace = $this->access->company($request->user(), $company);
        $data = $request->validate([
            'date' => ['required', 'date_format:d-M-Y'], 'rate_id' => ['required', 'integer', 'min:1'],
            'province_id' => ['required', 'integer', 'min:1'],
        ]);

        return response()->json($this->references->searchCatalog($this->references->sroSchedules(
            $workspace, $data['rate_id'], $data['date'], $data['province_id']
        )));
    }

    public function sroItems(Request $request, int $company): JsonResponse
    {
        $workspace = $this->access->company($request->user(), $company);
        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d'], 'sro_id' => ['required', 'integer', 'min:1']]);

        return response()->json($this->references->searchCatalog($this->references->sroItems(
            $workspace, $data['date'], $data['sro_id']
        )));
    }

    public function buyerVerification(Request $request, int $company): JsonResponse
    {
        $workspace = $this->access->company($request->user(), $company);
        $data = $request->validate([
            'registration_number' => ['required', 'string', 'regex:/^(?:[0-9]{7,8}|[0-9]{13})$/'],
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        return response()->json(['data' => $this->references->buyerVerification(
            $workspace, $data['registration_number'], $data['date']
        )]);
    }
}
