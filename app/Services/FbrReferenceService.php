<?php

namespace App\Services;

use App\Enums\FbrEnvironment;
use App\Models\Company;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class FbrReferenceService
{
    /** @return array{active: bool, status: string, registration_type: string, source: string} */
    public function buyerVerification(Company $company, string $registrationNumber, string $date): array
    {
        $environment = Company::whereKey($company->id)->value('fbr_environment');
        $environment = $environment instanceof FbrEnvironment ? $environment->value : $environment;
        $integration = $company->fbrIntegrations()->where('environment', $environment)
            ->whereIn('status', ['configured', 'verified'])->first(['token']);
        if ($integration === null || blank($integration->token)) {
            throw ValidationException::withMessages(['buyer_ntn_cnic' => 'Configure the company FBR token before verifying a buyer.']);
        }
        $key = 'fbr:buyer:v1:'.$company->id.':'.$environment.':'.hash_hmac('sha256', $integration->token, (string) config('app.key')).':'.hash('sha256', $registrationNumber.'|'.$date);
        $cache = Cache::store(config('fbr.reference_cache_store'));
        if (is_array($cached = $cache->get($key))) {
            return [...$cached, 'source' => 'cache'];
        }
        try {
            $client = Http::withToken($integration->token)->acceptJson()->asJson()->connectTimeout(3)->timeout(12)
                ->withOptions(['allow_redirects' => false]);
            $statusResponse = $client->send('GET', config('fbr.statl_url'), ['json' => ['regno' => $registrationNumber, 'date' => $date]]);
            $typeResponse = $client->send('GET', config('fbr.registration_type_url'), ['json' => ['Registration_No' => $registrationNumber]]);
            if (in_array($statusResponse->status(), [401, 403], true) || in_array($typeResponse->status(), [401, 403], true)) {
                throw ValidationException::withMessages(['buyer_ntn_cnic' => 'FBR rejected the configured token while verifying the buyer.']);
            }
            $status = $statusResponse->json();
            $type = $typeResponse->json();
            if (! $statusResponse->successful() || ! $typeResponse->successful() || ! is_array($status) || ! is_array($type)) {
                throw new RuntimeException('Invalid buyer verification response.');
            }
            $statusText = trim((string) ($status['status'] ?? ''));
            $registrationType = trim((string) ($type['REGISTRATION_TYPE'] ?? ''));
            if ($statusText === '' || $registrationType === '') {
                throw new RuntimeException('Incomplete buyer verification response.');
            }
            $result = [
                'active' => strcasecmp($statusText, 'Active') === 0,
                'status' => $statusText,
                'registration_type' => $registrationType,
            ];
            $cache->put($key, $result, (int) config('fbr.reference_fresh_seconds'));

            return [...$result, 'source' => 'fbr'];
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            abort(503, 'FBR buyer verification is temporarily unavailable.');
        }
    }

    /** @return array{records: array<string, string>, fetched_at: int, stale: bool, source: string} */
    public function hsCodes(Company $company): array
    {
        return $this->catalog($company, 'hs');
    }

    /** @return array{records: array<string, string>, fetched_at: int, stale: bool, source: string} */
    public function units(Company $company, ?string $hsCode = null): array
    {
        return $this->catalog($company, 'units', $hsCode ? [
            'hs_code' => $hsCode,
            'annexure_id' => (int) config('fbr.sales_annexure_id'),
        ] : []);
    }

    /** @return array{records: array<string, string>, fetched_at: int, stale: bool, source: string} */
    public function saleTypes(Company $company): array
    {
        return $this->catalog($company, 'sale-types');
    }

    public function provinces(Company $company): array
    {
        return $this->catalog($company, 'provinces');
    }

    public function rates(Company $company, string $date, int $transactionTypeId, int $provinceId): array
    {
        return $this->catalog($company, 'rates', [
            'date' => $date,
            'transTypeId' => $transactionTypeId,
            'originationSupplier' => $provinceId,
        ]);
    }

    public function sroSchedules(Company $company, int $rateId, string $date, int $provinceId): array
    {
        return $this->catalog($company, 'sro-schedules', [
            'rate_id' => $rateId,
            'date' => $date,
            'origination_supplier_csv' => $provinceId,
        ]);
    }

    public function sroItems(Company $company, string $date, int $sroId): array
    {
        return $this->catalog($company, 'sro-items', ['date' => $date, 'sro_id' => $sroId]);
    }

    /** @param array<string, mixed> $query @return array{records: array<string, string>, fetched_at: int, stale: bool, source: string} */
    private function catalog(Company $company, string $kind, array $query = []): array
    {
        $environment = Company::whereKey($company->id)->value('fbr_environment');
        $environment = $environment instanceof FbrEnvironment ? $environment->value : $environment;
        $integration = $company->fbrIntegrations()->where('environment', $environment)
            ->whereIn('status', ['configured', 'verified'])->first(['id', 'token']);
        if ($integration === null || empty($integration->token)) {
            throw ValidationException::withMessages(['hs_code' => 'Configure your company’s '.$environment.' FBR token in Company Details to load FBR reference lists.']);
        }
        $token = $integration->token;
        $fingerprint = hash_hmac('sha256', $token, (string) config('app.key'));
        $key = 'fbr:'.$kind.':v1:'.$company->id.':'.$environment.':'.$fingerprint.($query ? ':'.hash('sha256', json_encode($query, JSON_THROW_ON_ERROR)) : '');
        $cache = Cache::store(config('fbr.reference_cache_store'));
        $cached = $cache->get($key);
        if (is_array($cached) && now()->timestamp < $cached['fetched_at'] + (int) config('fbr.reference_fresh_seconds')) {
            return [...$cached, 'stale' => false, 'source' => 'cache'];
        }
        if ($failure = $cache->get($key.':failure')) {
            if ($failure === 'credentials') {
                throw ValidationException::withMessages(['hs_code' => 'FBR rejected the configured token. Check the token and integration access in Company Details.']);
            }
            if (is_array($cached)) {
                return [...$cached, 'stale' => true, 'source' => 'stale-cache'];
            }
            abort(503, 'FBR reference lists are temporarily unavailable. Please try again shortly.');
        }
        $lock = $cache->lock($key.':lock', 20);
        if (! $lock->get()) {
            if (is_array($cached)) {
                return [...$cached, 'stale' => true, 'source' => 'stale-cache'];
            }
            abort(503, 'FBR reference lists are being loaded. Please try again shortly.');
        }
        try {
            $latest = $cache->get($key);
            if (is_array($latest) && now()->timestamp < $latest['fetched_at'] + (int) config('fbr.reference_fresh_seconds')) {
                return [...$latest, 'stale' => false, 'source' => 'cache'];
            }
            $definitions = [
                'hs' => ['fbr.hs_codes_url', 'hS_CODE', 'description'],
                'units' => [$query ? 'fbr.hs_units_url' : 'fbr.units_url', 'uoM_ID', 'description'],
                'sale-types' => ['fbr.sale_types_url', 'transactioN_TYPE_ID', 'transactioN_DESC'],
                'provinces' => ['fbr.provinces_url', 'stateProvinceCode', 'stateProvinceDesc'],
                'rates' => ['fbr.rates_url', 'ratE_ID', 'ratE_DESC'],
                'sro-schedules' => ['fbr.sro_schedules_url', 'srO_ID', 'srO_DESC'],
                'sro-items' => ['fbr.sro_items_url', 'srO_ITEM_ID', 'srO_ITEM_DESC'],
            ];
            [$urlKey, $idField, $descriptionField] = $definitions[$kind] ?? throw new RuntimeException('Unknown reference catalog.');
            $response = Http::withToken($token)->acceptJson()->connectTimeout(3)->timeout(12)
                ->withOptions(['allow_redirects' => false])->get(config($urlKey), $query);
            if (in_array($response->status(), [401, 403], true)) {
                $cache->forget($key);
                $cache->put($key.':failure', 'credentials', (int) config('fbr.reference_retry_seconds'));
                throw ValidationException::withMessages(['hs_code' => 'FBR rejected the configured token. Check the token and integration access in Company Details.']);
            }
            if (! $response->successful()) {
                throw new RuntimeException('Reference request failed.');
            }
            $payload = $response->json();
            if (! is_array($payload) || ! array_is_list($payload) || $payload === [] || count($payload) > 50000) {
                throw new RuntimeException('Invalid reference response.');
            }
            $records = [];
            foreach ($payload as $row) {
                $code = $row[$idField] ?? null;
                $description = $row[$descriptionField] ?? null;
                if (! is_array($row) || ! (is_string($code) || is_int($code)) || ! is_string($description) || trim($description) === '' ||
                    ($kind === 'hs' && ! preg_match('/^[0-9]{4}\.[0-9]{4}$/', (string) $code))) {
                    throw new RuntimeException('Invalid reference row.');
                }
                $records[(string) $code] = trim($description);
            }
            ksort($records);
            $result = ['records' => $records, 'fetched_at' => now()->timestamp];
            $cache->put($key, $result, (int) config('fbr.reference_retention_seconds'));
            $cache->forget($key.':failure');

            return [...$result, 'stale' => false, 'source' => 'fbr'];
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            $cache->put($key.':failure', 'unavailable', (int) config('fbr.reference_retry_seconds'));
            if (is_array($cached)) {
                return [...$cached, 'stale' => true, 'source' => 'stale-cache'];
            }
            abort(503, 'FBR reference lists could not be loaded. Please try again shortly.');
        } finally {
            $lock->release();
        }
    }

    /** @return array{data: array<int, array{code: string, description: string}>, meta: array<string, mixed>} */
    public function searchHsCodes(Company $company, string $search = '', int $limit = 30): array
    {
        return $this->searchCatalog($this->hsCodes($company), $search, $limit);
    }

    /** @param array<string, mixed> $catalog @return array<string, mixed> */
    public function searchCatalog(array $catalog, string $search = '', int $limit = 30): array
    {
        $rows = [];
        foreach ($catalog['records'] as $code => $description) {
            if ($search !== '' && ! str_contains((string) $code, $search) && mb_stripos($description, $search) === false) {
                continue;
            }
            $rows[] = ['code' => (string) $code, 'description' => $description];
            if (count($rows) > $limit) {
                break;
            }
        }
        $more = count($rows) > $limit;

        return ['data' => array_slice($rows, 0, $limit), 'meta' => [
            'has_more' => $more, 'stale' => $catalog['stale'], 'source' => $catalog['source'],
            'fetched_at' => date(DATE_ATOM, $catalog['fetched_at']),
        ]];
    }

    public function validateHsCode(Company $company, string $code): void
    {
        if (! array_key_exists($code, $this->hsCodes($company)['records'])) {
            throw ValidationException::withMessages(['hs_code' => 'Select a valid HS code from the FBR reference list.']);
        }
    }

    public function validateHsUnit(Company $company, string $hsCode, string $unit): void
    {
        $allowed = $this->units($company, $hsCode)['records'];
        if (! in_array(mb_strtolower(trim($unit)), array_map(fn (string $value): string => mb_strtolower(trim($value)), $allowed), true)) {
            throw ValidationException::withMessages([
                'unit_of_measurement' => 'Select a UOM returned by FBR for the selected HS code.',
            ]);
        }
    }
}
