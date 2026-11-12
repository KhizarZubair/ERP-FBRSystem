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
        return hash('sha256', json_encode([$company->ntn_cnic, $company->name, $company->province, $company->address, $company->business_activity, $company->sector, $company->is_sales_tax_profile_retailer], JSON_THROW_ON_ERROR));
    }

    private function tokenFingerprint(string $token): string
    {
        return hash_hmac('sha256', $token, (string) config('app.key'));
    }

    private function invalidateFbrState(Company $company): void
    {
        $this->stateStore()->forget($this->verifiedKey($company));
        $this->stateStore()->forget($this->certificationKey($company));
        $sandbox = $company->fbrIntegrations()->where('environment', 'sandbox')->first();
        if ($sandbox) {
            $sandbox->status = FbrIntegrationStatus::Configured;
            $sandbox->verified_at = null;
            $sandbox->save();
        }
        // A production credential must not remain presented as unlocked after sandbox credentials change.
        $company->fbrIntegrations()->where('environment', 'production')->delete();
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
        $sandbox = $company->fbrIntegrations()->where('environment', 'sandbox')->first();
        $production = $company->fbrIntegrations()->where('environment', 'production')->first();
        $profileFingerprint = $this->profileFingerprint($company);
        $sandboxToken = (string) ($sandbox?->token ?? '');
        $currentTokenFingerprint = $sandboxToken !== '' ? $this->tokenFingerprint($sandboxToken) : '';
        $verifiedCurrentProfile = is_array($verified)
            && ($verified['verified'] ?? false) === true
            && $currentTokenFingerprint !== ''
            && hash_equals((string) ($verified['token_fingerprint'] ?? ''), $currentTokenFingerprint)
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
            && $currentTokenFingerprint !== ''
            && hash_equals((string) ($certification['token_fingerprint'] ?? ''), $currentTokenFingerprint)
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
            'sandbox_configured' => $sandbox !== null && filled($sandbox->token),
            'production_configured' => $production !== null && filled($production->token) && $verifiedCurrentProfile && $certifiedCurrentProfile,
        ])->header('Cache-Control', 'no-store, private');
    }

    public function verifyFbr(Request $request): JsonResponse
    {
        $company = $this->company($request);
        if (blank($company->business_activity) || blank($company->sector)) {
            throw ValidationException::withMessages([
                'business_activity' => 'Select the company business activity and sector from the official FBR list before verification.',
            ]);
        }
        $ntn = trim((string) $company->ntn_cnic);
        if (! preg_match('/^(?:[0-9]{7}|[0-9]{13})$/', $ntn)) {
            $this->stateStore()->forget($this->verifiedKey($company));
            throw ValidationException::withMessages(['company_ntn' => 'Company NTN/CNIC must contain exactly 7 or 13 digits.']);
        }
        $this->stateStore()->forget($this->verifiedKey($company));

        $data = $request->validate(['sandbox_token' => ['sometimes', 'required', 'string', 'max:4096', 'regex:/^\S+$/']]);
        $tokenInput = trim((string) ($data['sandbox_token'] ?? ''));
        if ($tokenInput !== '') {
            $existingToken = (string) ($company->fbrIntegrations()->where('environment', 'sandbox')->first()?->token ?? '');
            if ($existingToken === '' || ! hash_equals($existingToken, $tokenInput)) {
                $this->invalidateFbrState($company);
                $integration = $company->fbrIntegrations()->firstOrNew(['environment' => 'sandbox']);
                $integration->token = $tokenInput;
                $integration->status = FbrIntegrationStatus::Configured;
                $integration->verified_at = null;
                $integration->save();
            }
        }

        $verificationToken = (string) ($company->fbrIntegrations()->where('environment', 'sandbox')->first()?->token ?? '');
        if ($verificationToken === '') {
            return response()->json(['verified' => false, 'message' => 'Save this company’s FBR Sandbox Token before taxpayer verification.'], 422);
        }
        try {
            // This is a real FBR lookup. No local/mock success is returned when FBR is unavailable.
            $date = now()->toDateString();
            $client = Http::withToken($verificationToken)->acceptJson()->asJson()->connectTimeout(4)->timeout(12)
                ->withOptions(['allow_redirects' => false]);
            $statusResponse = $client->send('GET', config('fbr.statl_url'), ['json' => ['regno' => $ntn, 'date' => $date]]);
            $typeResponse = $client->send('GET', config('fbr.registration_type_url'), ['json' => ['Registration_No' => $ntn]]);

            if (! $statusResponse->successful() || ! $typeResponse->successful()) {
                $errors = [];
                if (! $statusResponse->successful()) $errors['statl'] = [in_array($statusResponse->status(), [401, 403], true) ? 'FBR rejected the Sandbox Token for STATL. Check your PRAL Sandbox Token.' : 'STATL request failed with HTTP '.$statusResponse->status().'. Check the NTN and retry.'];
                if (! $typeResponse->successful()) $errors['get_reg_type'] = [in_array($typeResponse->status(), [401, 403], true) ? 'FBR rejected the Sandbox Token for Get_Reg_Type. Check your PRAL Sandbox Token.' : 'Get_Reg_Type request failed with HTTP '.$typeResponse->status().'. Check the NTN and retry.'];
                return response()->json(['verified' => false, 'message' => 'FBR taxpayer verification failed. Review the details below.', 'errors' => $errors], 422);
            }
            $status = $statusResponse->json();
            $type = $typeResponse->json();
            if (! is_array($status) || ! is_array($type)) {
                return response()->json(['verified' => false, 'message' => 'FBR returned an unreadable taxpayer verification response.'], 502);
            }
            // PRAL's documented STATL response uses the exact key "status code".
            // The PDF shows codes 01/02 with "In-Active"; Active is accepted only
            // when the returned status text explicitly says "Active".
            $statusCode = trim((string) ($status['status code'] ?? ''));
            $statusText = trim((string) ($status['status'] ?? ''));
            $registrationType = trim((string) ($type['REGISTRATION_TYPE'] ?? ''));
            $registrationNo = preg_replace('/\\D+/', '', (string) ($type['REGISTRATION_NO'] ?? ''));
            $registrationStatusCode = trim((string) ($type['statuscode'] ?? ''));
            $active = strcasecmp($statusText, 'Active') === 0;
            $ntnMatches = $registrationNo !== '' && hash_equals(preg_replace('/\\D+/', '', $ntn), $registrationNo);
            $registered = $registrationStatusCode === '00' && strcasecmp($registrationType, 'Registered') === 0;
            if (! $ntnMatches || ! $active || ! $registered) {
                $this->stateStore()->put($this->verifiedKey($company), [
                    'verified' => false, 'status' => $statusText ?: 'Unknown',
                    'statl_status_code' => $statusCode ?: 'Unknown',
                    'registration_type' => $registrationType ?: 'Unknown', 'checked_at' => now()->toIso8601String(),
                    'profile_fingerprint' => $this->profileFingerprint($company),
                    'token_fingerprint' => $this->tokenFingerprint($verificationToken),
                ], now()->addHours(12));

                $errors = [];
                if (! $ntnMatches) $errors['company_ntn'] = ['The NTN returned by FBR does not match the NTN saved in this company profile. Check Company Details.'];
                if (! $active) $errors['statl'] = ['FBR STATL returned status '.($statusText ?: 'missing').' (status code '.($statusCode ?: 'missing').'). The company must have an explicit Active status.'];
                if (! $registered) $errors['get_reg_type'] = ['FBR registration type is '.($registrationType ?: 'missing').' (status code '.($registrationStatusCode ?: 'missing').'). A Registered taxpayer record is required.'];
                return response()->json(['verified' => false, 'status' => $statusText ?: 'Unknown', 'registration_type' => $registrationType ?: 'Unknown',
                    'message' => 'Company verification failed. Correct the highlighted information and try again.', 'errors' => $errors], 422);
            }
            $result = ['verified' => true, 'ntn_cnic' => $ntn,
                'checks' => [
                    'company_ntn' => ['passed' => true, 'value' => $ntn, 'fbr_value' => $registrationNo],
                    'statl' => ['passed' => true, 'value' => $statusText, 'status_code' => $statusCode],
                    'get_reg_type' => ['passed' => true, 'registration_type' => $registrationType, 'status_code' => $registrationStatusCode],
                ],
                'status' => $statusText, 'statl_status_code' => $statusCode, 'registration_type' => $registrationType,
                'checked_at' => now()->toIso8601String(), 'profile_fingerprint' => $this->profileFingerprint($company),
                'token_fingerprint' => $this->tokenFingerprint($verificationToken)];
            $this->stateStore()->put($this->verifiedKey($company), $result, now()->addHours(12));

            return response()->json($result)->header('Cache-Control', 'no-store, private');
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['verified' => false, 'message' => 'FBR taxpayer verification is currently unavailable. No verification was assumed; please retry later.', 'errors' => ['fbr_connection' => ['Could not complete STATL and Get_Reg_Type requests. Check internet connectivity and the PRAL Sandbox Token, then retry.']]], 503);
        }
    }

    public function saveSandboxToken(Request $request): JsonResponse
    {
        $company = $this->company($request);
        $data = $request->validate([
            'sandbox_token' => ['required', 'string', 'max:4096', 'regex:/^\S+$/'],
        ]);

        $newToken = trim($data['sandbox_token']);
        $oldToken = (string) ($company->fbrIntegrations()->where('environment', 'sandbox')->first()?->token ?? '');
        if ($oldToken === '' || ! hash_equals($oldToken, $newToken)) {
            $this->invalidateFbrState($company);
        }
        $integration = $company->fbrIntegrations()->firstOrNew(['environment' => 'sandbox']);
        $integration->token = $newToken;
        if ($oldToken === '' || ! hash_equals($oldToken, $newToken)) {
            $integration->status = FbrIntegrationStatus::Configured;
            $integration->verified_at = null;
        }
        $integration->save();

        return response()->json(['saved' => true, 'sandbox_configured' => true])->header('Cache-Control', 'no-store, private');
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
            'sandbox_token' => ['nullable', 'string', 'max:4096', 'regex:/^\S+$/'],
            'scenario_id' => ['sometimes', 'string', Rule::in($assignedIds)],
        ]);
        $token = (string) ($company->fbrIntegrations()->where('environment', 'sandbox')->first()?->token ?? '');
        if ($token === '') {
            throw ValidationException::withMessages(['sandbox_token' => 'Save the Sandbox Token first.']);
        }
        $tokenFingerprint = $this->tokenFingerprint($token);
        abort_unless(hash_equals((string) ($verified['token_fingerprint'] ?? ''), $tokenFingerprint)
            && hash_equals((string) ($verified['profile_fingerprint'] ?? ''), $this->profileFingerprint($company)),
            422, 'The saved Sandbox Token or company profile has changed. Verify the company again before certification.');
        if (isset($data['sandbox_token']) && ! hash_equals($token, trim((string) $data['sandbox_token']))) {
            abort(422, 'The submitted token differs from the verified saved token. Save and verify the new token first.');
        }
        $integration = $company->fbrIntegrations()->where('environment', 'sandbox')->firstOrFail();

        $previous = $this->currentCertification($company);
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

        $sandbox = $company->fbrIntegrations()->where('environment', 'sandbox')->first();
        $currentSandboxFingerprint = $sandbox && filled($sandbox->token) ? $this->tokenFingerprint((string) $sandbox->token) : '';
        abort_unless(is_array($verified) && ($verified['verified'] ?? false) === true
            && $currentSandboxFingerprint !== ''
            && hash_equals((string) ($verified['token_fingerprint'] ?? ''), $currentSandboxFingerprint)
            && hash_equals((string) ($verified['profile_fingerprint'] ?? ''), $profileFingerprint), 422, 'Verify the current company profile with its saved Sandbox Token first.');

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
        abort_unless($sandbox && hash_equals((string) ($certification['token_fingerprint'] ?? ''), $this->tokenFingerprint((string) $sandbox->token)), 422,
            'Sandbox token changed after certification. Run sandbox certification again.');

        $integration = $company->fbrIntegrations()->firstOrNew(['environment' => 'production']);
        $integration->token = trim($data['production_token']);
        $integration->status = FbrIntegrationStatus::Configured;
        $integration->verified_at = null;
        $integration->save();

        return response()->json(['saved' => true, 'production_configured' => true])->header('Cache-Control', 'no-store, private');
    }
}
