<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\FbrIntegrationStatus;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\FbrErrorCatalog;
use App\Services\FbrScenarioRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class FbrIntegrationController extends Controller
{
    public function __construct(
        private FbrScenarioRegistry $registry,
        private FbrErrorCatalog $errors,
    ) {}

    private function stateStore()
    {
        return Cache::store(config('fbr.reference_cache_store'));
    }

    private function company(Request $request): Company
    {
        $user = $request->user();
        abort_unless($user, 401);
        $company = $user->companies()->where('companies.is_active', true)->wherePivot('is_active', true)->first();
        abort_unless($company, 404, 'Complete your Company Profile first.');

        return $company;
    }

    private function verifiedKey(Company $company): string
    {
        return 'fbr:company-verification:v1:'.$company->id;
    }

    private function certificationKey(Company $company): string
    {
        return 'fbr:sandbox-certification:v1:'.$company->id;
    }

    private function profileFingerprint(Company $company): string
    {
        return hash('sha256', json_encode([$company->ntn_cnic, $company->name, $company->province, $company->address], JSON_THROW_ON_ERROR));
    }

    private function currentCertification(Company $company): array
    {
        $result = $this->stateStore()->get($this->certificationKey($company), []);

        return is_array($result) ? $result : [];
    }

    public function status(Request $request): JsonResponse
    {
        $company = $this->company($request);
        $verified = $this->stateStore()->get($this->verifiedKey($company), []);
        $certification = $this->currentCertification($company);
        $production = $company->fbrIntegrations()->where('environment', 'production')->first();
        $profileFingerprint = $this->profileFingerprint($company);
        $verifiedCurrentProfile = is_array($verified)
            && ($verified['verified'] ?? false) === true
            && hash_equals((string) ($verified['profile_fingerprint'] ?? ''), $profileFingerprint);

        $assignedScenarios = $this->registry->forCompany($company);

        $passedScenarioIds = collect($certification['results'] ?? [])
            ->filter(fn ($r) => ($r['passed'] ?? false) === true)
            ->pluck('scenario_id')
            ->all();

        $allAssignedPassed = ! empty($assignedScenarios)
            && count($passedScenarioIds) === count($assignedScenarios)
            && collect($assignedScenarios)->every(fn ($s) => in_array($s['id'], $passedScenarioIds, true));

        $certifiedCurrentProfile = ($certification['all_passed'] ?? false) === true
            && $allAssignedPassed
            && hash_equals((string) ($certification['profile_fingerprint'] ?? ''), $profileFingerprint);

        return response()->json([
            'verified' => $verifiedCurrentProfile,
            'verification' => $verifiedCurrentProfile ? $verified : null,
            'business_activity' => $company->business_activity,
            'sector' => $company->sector,
            'scenarios' => $assignedScenarios,
            'results' => $certification['results'] ?? [],
            'all_passed' => $verifiedCurrentProfile && $certifiedCurrentProfile,
            'certified_token_fingerprint' => $certification['token_fingerprint'] ?? null,
            'production_configured' => $production !== null && filled($production->token),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function verifyFbr(Request $request): JsonResponse
    {
        $company = $this->company($request);
        $ntn = trim((string) $company->ntn_cnic);
        if (! preg_match('/^(?:[0-9]{7,8}|[0-9]{13})$/', $ntn)) {
            $this->stateStore()->forget($this->verifiedKey($company));
            throw ValidationException::withMessages(['seller_ntn' => 'Seller NTN/CNIC must contain 7 or 8 digits, or exactly 13 digits.']);
        }
        $this->stateStore()->forget($this->verifiedKey($company));

        $verificationToken = (string) config('fbr.verification_token', '');
        if ($verificationToken === '') {
            $verificationToken = (string) ($company->fbrIntegrations()->where('environment', 'sandbox')->value('token') ?? '');
        }
        if ($verificationToken === '') {
            return response()->json(['verified' => false, 'message' => 'FBR taxpayer lookup requires an authorized API token. Configure FBR_VERIFICATION_TOKEN on the server or obtain a token through your approved integration setup.'], 503);
        }
        try {
            // This is a real FBR lookup. No local/mock success is returned when FBR is unavailable.
            $date = now()->toDateString();
            $client = Http::withToken($verificationToken)->acceptJson()->asJson()->connectTimeout(4)->timeout(12)
                ->withOptions(['allow_redirects' => false]);
            $statusResponse = $client->send('GET', config('fbr.statl_url'), ['json' => ['regno' => $ntn, 'date' => $date]]);
            $typeResponse = $client->send('GET', config('fbr.registration_type_url'), ['json' => ['Registration_No' => $ntn]]);

            if (! $statusResponse->successful() || ! $typeResponse->successful()) {
                return response()->json(['verified' => false, 'message' => 'FBR could not verify this taxpayer. Check the NTN/CNIC and FBR service access, then retry.'], 422);
            }
            $status = $statusResponse->json();
            $type = $typeResponse->json();
            if (! is_array($status) || ! is_array($type)) {
                return response()->json(['verified' => false, 'message' => 'FBR returned an unreadable taxpayer verification response.'], 502);
            }
            $statusText = trim((string) ($status['status'] ?? ''));
            $registrationType = trim((string) ($type['REGISTRATION_TYPE'] ?? ''));
            $active = strcasecmp($statusText, 'Active') === 0;
            if ($statusText === '' || $registrationType === '' || ! $active) {
                $this->stateStore()->put($this->verifiedKey($company), [
                    'verified' => false, 'status' => $statusText ?: 'Unknown',
                    'registration_type' => $registrationType ?: 'Unknown', 'checked_at' => now()->toIso8601String(),
                    'profile_fingerprint' => $this->profileFingerprint($company),
                ], now()->addHours(12));

                return response()->json(['verified' => false, 'status' => $statusText ?: 'Unknown', 'registration_type' => $registrationType ?: 'Unknown',
                    'message' => 'FBR did not return an active, complete taxpayer record. Sandbox credentials remain locked.'], 422);
            }
            $result = ['verified' => true, 'status' => $statusText, 'registration_type' => $registrationType,
                'checked_at' => now()->toIso8601String(), 'profile_fingerprint' => $this->profileFingerprint($company)];
            $this->stateStore()->put($this->verifiedKey($company), $result, now()->addHours(12));

            return response()->json($result)->header('Cache-Control', 'no-store, private');
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['verified' => false, 'message' => 'FBR taxpayer verification is currently unavailable. No verification was assumed; please retry later.'], 503);
        }
    }

    public function runSandboxCertification(Request $request): JsonResponse
    {
        $company = $this->company($request);
        $verified = $this->stateStore()->get($this->verifiedKey($company), []);
        abort_unless(is_array($verified) && ($verified['verified'] ?? false) === true, 422, 'Verify the company with FBR before running sandbox certification.');

        $assignedScenarios = $this->registry->forCompany($company);
        if (empty($assignedScenarios)) {
            throw ValidationException::withMessages([
                'sandbox_token' => 'Configure at least one assigned sandbox scenario in Company Details before running certification.',
            ]);
        }

        $assignedIds = array_column($assignedScenarios, 'id');

        $data = $request->validate([
            'sandbox_token' => ['required', 'string', 'max:4096', 'regex:/^\S+$/'],
            'scenario_id' => ['sometimes', 'string', Rule::in($assignedIds)],
        ]);
        $token = trim($data['sandbox_token']);
        $integration = $company->fbrIntegrations()->firstOrNew(['environment' => 'sandbox']);
        $integration->token = $token;
        $integration->status = FbrIntegrationStatus::Configured;
        $integration->verified_at = null;
        $integration->save();

        $previous = $this->currentCertification($company);
        $tokenFingerprint = hash_hmac('sha256', $token, (string) config('app.key'));
        $profileFingerprint = $this->profileFingerprint($company);

        // Discard cached results if token changed OR if company profile changed
        if (($previous['token_fingerprint'] ?? null) !== $tokenFingerprint
            || ! hash_equals((string) ($previous['profile_fingerprint'] ?? ''), $profileFingerprint)) {
            $previous = [];
        }

        $resultsById = [];
        foreach (($previous['results'] ?? []) as $previousResult) {
            if (isset($previousResult['scenario_id'])) {
                $resultsById[$previousResult['scenario_id']] = $previousResult;
            }
        }

        $scenariosToRun = isset($data['scenario_id'])
            ? array_values(array_filter($assignedScenarios, fn ($scenario) => $scenario['id'] === $data['scenario_id']))
            : $assignedScenarios;

        foreach ($scenariosToRun as $scenario) {
            $scenarioId = $scenario['id'];

            if (! $this->registry->isSupported($scenarioId)) {
                $resultsById[$scenarioId] = [
                    'scenario_id' => $scenarioId,
                    'title' => $scenario['title'],
                    'passed' => false,
                    'fbr_invoice_no' => null,
                    'error_code' => 'UNSUPPORTED',
                    'error' => 'Scenario '.$scenarioId.' is not supported in this release. Official tax formulas and SRO rules are pending.',
                    'raw_response' => null,
                ];

                continue;
            }

            try {
                $payload = $this->registry->buildCertificationPayload($company, $scenarioId);

                // Certification is sandbox-only by design. Never use the production endpoint here.
                $response = Http::withToken($token)->acceptJson()->asJson()->connectTimeout(5)->timeout(25)
                    ->withOptions(['allow_redirects' => false])
                    ->post(config('fbr.sandbox_submit_url'), $payload);
                $body = json_decode(str_replace($token, '[redacted]', $response->body()), true);
                $validation = is_array($body) ? ($body['validationResponse'] ?? []) : [];
                $lineResults = is_array($validation) ? ($validation['invoiceStatuses'] ?? []) : [];
                $passed = $response->successful()
                    && (string) ($validation['statusCode'] ?? '') === '00'
                    && strtolower((string) ($validation['status'] ?? '')) === 'valid'
                    && is_string($body['invoiceNumber'] ?? null)
                    && $body['invoiceNumber'] !== '';
                foreach (is_array($lineResults) ? $lineResults : [] as $line) {
                    if ((string) ($line['statusCode'] ?? '') !== '00' || strtolower((string) ($line['status'] ?? '')) !== 'valid') {
                        $passed = false;
                    }
                }
                $errCode = $passed ? null : ($validation['errorCode'] ?? ($lineResults[0]['errorCode'] ?? null));
                $errEntry = $errCode !== null ? $this->errors->entry((string) $errCode, $company->sector, $company->business_activity) : null;
                $resultsById[$scenarioId] = [
                    'scenario_id' => $scenarioId,
                    'title' => $scenario['title'],
                    'passed' => $passed,
                    'fbr_invoice_no' => $body['invoiceNumber'] ?? null,
                    'error_code' => $errCode,
                    'error' => $passed ? null : ($validation['error'] ?? ($lineResults[0]['error'] ?? ('FBR sandbox rejected the scenario (HTTP '.$response->status().').'))),
                    'category' => $errEntry['category'] ?? null,
                    'guidance' => $errEntry['guidance'] ?? null,
                    'solution' => $errEntry['solution'] ?? null,
                    'raw_response' => $body,
                ];
            } catch (Throwable $exception) {
                report($exception);
                $resultsById[$scenarioId] = [
                    'scenario_id' => $scenarioId,
                    'title' => $scenario['title'],
                    'passed' => false,
                    'fbr_invoice_no' => null,
                    'error_code' => null,
                    'error' => 'Could not reach FBR Sandbox. Check connectivity and token, then retry.',
                    'raw_response' => null,
                ];
            }
        }

        $results = [];
        foreach ($assignedScenarios as $scenario) {
            if (isset($resultsById[$scenario['id']])) {
                $results[] = $resultsById[$scenario['id']];
            }
        }
        $isFullRun = count($scenariosToRun) === count($assignedScenarios);
        $allPassed = $isFullRun
            && count($results) === count($assignedScenarios)
            && collect($results)->every(fn ($result) => ($result['passed'] ?? false) === true);
        $state = [
            'all_passed' => $allPassed,
            'results' => $results,
            'token_fingerprint' => $tokenFingerprint,
            'profile_fingerprint' => $profileFingerprint,
            'checked_at' => now()->toIso8601String(),
        ];
        $this->stateStore()->put($this->certificationKey($company), $state, now()->addDays(30));

        if ($allPassed) {
            $integration->status = FbrIntegrationStatus::Verified;
            $integration->verified_at = now();
        } else {
            $integration->status = FbrIntegrationStatus::Configured;
            $integration->verified_at = null;
        }
        $integration->save();

        return response()->json($state)->header('Cache-Control', 'no-store, private');
    }

    public function saveProductionToken(Request $request): JsonResponse
    {
        $company = $this->company($request);
        $verified = $this->stateStore()->get($this->verifiedKey($company), []);
        $certification = $this->currentCertification($company);
        $profileFingerprint = $this->profileFingerprint($company);

        abort_unless(is_array($verified) && ($verified['verified'] ?? false) === true
            && hash_equals((string) ($verified['profile_fingerprint'] ?? ''), $profileFingerprint), 422, 'Verify the current company profile with FBR first.');

        $assignedScenarios = $this->registry->forCompany($company);
        abort_if(empty($assignedScenarios), 422, 'Configure at least one assigned sandbox scenario in Company Details first.');

        $passedScenarioIds = collect($certification['results'] ?? [])
            ->filter(fn ($r) => ($r['passed'] ?? false) === true)
            ->pluck('scenario_id')
            ->all();

        $allAssignedPassed = count($passedScenarioIds) === count($assignedScenarios)
            && collect($assignedScenarios)->every(fn ($s) => in_array($s['id'], $passedScenarioIds, true));

        abort_unless(($certification['all_passed'] ?? false) === true
            && $allAssignedPassed
            && hash_equals((string) ($certification['profile_fingerprint'] ?? ''), $profileFingerprint), 422, 'All sandbox scenarios must pass for the current company profile before a production token can be saved.');

        $data = $request->validate(['production_token' => ['required', 'string', 'max:4096', 'regex:/^\S+$/']]);
        $sandbox = $company->fbrIntegrations()->where('environment', 'sandbox')->first();
        abort_unless($sandbox && hash_equals((string) ($certification['token_fingerprint'] ?? ''), hash_hmac('sha256', (string) $sandbox->token, (string) config('app.key'))), 422,
            'Sandbox token changed after certification. Run sandbox certification again.');

        $integration = $company->fbrIntegrations()->firstOrNew(['environment' => 'production']);
        $integration->token = trim($data['production_token']);
        $integration->status = FbrIntegrationStatus::Configured;
        $integration->verified_at = null;
        $integration->save();

        return response()->json(['saved' => true, 'production_configured' => true])->header('Cache-Control', 'no-store, private');
    }
}
