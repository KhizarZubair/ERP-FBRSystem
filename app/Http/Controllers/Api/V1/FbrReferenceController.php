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
        
        $dateInput = $request->input('date');
        try {
            $formattedDate = $dateInput ? \Carbon\Carbon::parse($dateInput)->format('d-M-Y') : now()->format('d-M-Y');
        } catch (\Throwable) {
            $formattedDate = now()->format('d-M-Y');
        }

        $transactionTypeId = $request->input('transaction_type_id');
        if (empty($transactionTypeId) && $request->filled('sale_type')) {
            $saleTypeInput = trim((string) $request->input('sale_type'));
            try {
                $saleTypesCatalog = $this->references->saleTypes($workspace);
                foreach ($saleTypesCatalog['records'] as $code => $desc) {
                    if (strcasecmp($desc, $saleTypeInput) === 0 || str_contains(strtolower($desc), strtolower($saleTypeInput))) {
                        $transactionTypeId = (int) $code;
                        break;
                    }
                }
            } catch (\Throwable) {
                $transactionTypeId = 18;
            }
        }
        $transactionTypeId = (int) ($transactionTypeId ?: 18);

        $provinceId = $request->input('province_id');
        if (empty($provinceId)) {
            $provinceName = $request->input('province') ?: $workspace->province;
            if ($provinceName) {
                try {
                    $provincesCatalog = $this->references->provinces($workspace);
                    foreach ($provincesCatalog['records'] as $code => $desc) {
                        if (strcasecmp($desc, $provinceName) === 0 || str_contains(strtolower($desc), strtolower($provinceName))) {
                            $provinceId = (int) $code;
                            break;
                        }
                    }
                } catch (\Throwable) {
                    $provinceId = 1;
                }
            }
        }
        $provinceId = (int) ($provinceId ?: 1);

        try {
            $ratesCatalog = $this->references->rates($workspace, $formattedDate, $transactionTypeId, $provinceId);
            return response()->json($this->references->searchCatalog(
                $ratesCatalog, 
                (string) $request->input('search', ''), 
                (int) $request->input('limit', 50)
            ));
        } catch (\Throwable) {
            return response()->json([
                'data' => [
                    ['code' => '1', 'description' => '18%'],
                    ['code' => '2', 'description' => '0%'],
                    ['code' => '3', 'description' => 'Exempt'],
                    ['code' => '4', 'description' => '1%'],
                    ['code' => '5', 'description' => '5%'],
                    ['code' => '6', 'description' => '10%'],
                    ['code' => '7', 'description' => '12%'],
                    ['code' => '8', 'description' => '15%'],
                ],
                'meta' => ['stale' => true, 'source' => 'fallback'],
            ]);
        }
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
            'date' => ['sometimes', 'nullable', 'string'],
        ]);

        $date = ! empty($data['date']) ? $data['date'] : now()->toDateString();
        try {
            $parsedDate = \Carbon\Carbon::parse($date)->toDateString();
        } catch (\Throwable) {
            $parsedDate = now()->toDateString();
        }

        try {
            $result = $this->references->buyerVerification(
                $workspace, $data['registration_number'], $parsedDate
            );
            return response()->json(['data' => $result]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            if ($e->getStatusCode() === 503) {
                return response()->json([
                    'data' => [
                        'active' => false,
                        'status' => 'FBR Gateway Offline',
                        'registration_type' => 'Unregistered',
                        'source' => 'offline_fallback',
                        'is_offline' => true,
                        'message' => 'FBR verification gateway is currently slow or unreachable. Customer can be saved locally as unverified.',
                    ],
                ]);
            }
            throw $e;
        } catch (\Throwable $e) {
            return response()->json([
                'data' => [
                    'active' => false,
                    'status' => 'FBR Verification Unavailable',
                    'registration_type' => 'Unregistered',
                    'source' => 'offline_fallback',
                    'is_offline' => true,
                    'message' => 'Could not contact FBR server. Customer can be saved locally as unverified.',
                ],
            ]);
        }
    }
}
