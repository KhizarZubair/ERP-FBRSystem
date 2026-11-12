<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\FbrSubmission;
use App\Models\Invoice;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Throwable;

class FbrInvoiceService
{
    public function __construct(
        private InvoiceService $invoices,
        private FbrErrorCatalog $errors,
        private FbrReferenceService $references,
    ) {}

    public function environments(Company $company): array
    {
        $environment = $company->fbr_environment->value;
        $integration = $company->fbrIntegrations()->where('environment', $environment)->first();
        if (! $integration || blank($integration->token) || strtoupper(trim($integration->token)) === 'N/A') {
            return [];
        }
        if ($environment === 'production' && ! $this->productionCertificationIsCurrent($company)) {
            return [];
        }

        return [$environment];
    }

    private function productionCertificationIsCurrent(Company $company): bool
    {
        $sandbox = $company->fbrIntegrations()->where('environment', 'sandbox')->first();
        if (! $sandbox || blank($sandbox->token)) {
            return false;
        }

        $profileFingerprint = hash('sha256', json_encode([
            $company->ntn_cnic, $company->name, $company->province, $company->address,
            $company->business_activity, $company->sector,
        ], JSON_THROW_ON_ERROR));
        $tokenFingerprint = hash_hmac('sha256', (string) $sandbox->token, (string) config('app.key'));
        $store = Cache::store(config('fbr.reference_cache_store'));
        $verified = $store->get('fbr:company-verification:v1:'.$company->id, []);
        $certification = $store->get('fbr:sandbox-certification:v1:'.$company->id, []);
        if (! is_array($verified) || ($verified['verified'] ?? false) !== true
            || ! hash_equals((string) ($verified['profile_fingerprint'] ?? ''), $profileFingerprint)
            || ! hash_equals((string) ($verified['token_fingerprint'] ?? ''), $tokenFingerprint)
            || ! is_array($certification) || ($certification['all_passed'] ?? false) !== true
            || ! hash_equals((string) ($certification['profile_fingerprint'] ?? ''), $profileFingerprint)
            || ! hash_equals((string) ($certification['token_fingerprint'] ?? ''), $tokenFingerprint)) {
            return false;
        }

        $assignedIds = array_column(app(FbrScenarioRegistry::class)->forCompany($company), 'id');
        $passedIds = collect($certification['results'] ?? [])
            ->filter(fn ($result) => ($result['passed'] ?? false) === true)
            ->pluck('scenario_id')->all();

        return $assignedIds !== [] && count($assignedIds) === count($passedIds)
            && collect($assignedIds)->every(fn ($id) => in_array($id, $passedIds, true));
    }

    /** @return array{ready: bool, blockers: array<int, string>, scenarios: array<string, array<string, string>>} */
    public function readiness(Company $company): array
    {
        $blockers = [];
        foreach (['name' => 'registered business name', 'ntn_cnic' => 'seller NTN/CNIC', 'province' => 'seller province',
            'address' => 'registered address', 'business_activity' => 'business activity', 'sector' => 'sector'] as $field => $label) {
            if (blank($company->$field)) {
                $blockers[] = 'Add '.$label.' in Company Details.';
            }
        }
        if (! preg_match('/^(?:[0-9]{7,8}|[0-9]{13})$/', (string) $company->ntn_cnic)) {
            $blockers[] = 'Seller NTN must contain 7 or 8 digits, or a CNIC must contain 13 digits.';
        }
        $allowedScenarios = app(FbrScenarioRegistry::class)->allowedCodesForCompany($company);
        if ($company->fbr_environment->value === 'sandbox' && empty($allowedScenarios)) {
            $blockers[] = 'Select a sector or at least one supported sandbox scenario.';
        }
        if ($this->environments($company) === []) {
            $blockers[] = 'Configure the '.$company->fbr_environment->value.' FBR token.';
        }
        $scenarios = array_intersect_key(config('fbr.supported_scenarios'), array_flip($allowedScenarios));

        return ['ready' => $blockers === [], 'blockers' => $blockers, 'scenarios' => $scenarios];
    }

    public function payload(Invoice $invoice, string $environment, ?string $scenario): array
    {
        $payload = [
            'invoiceType' => $invoice->invoice_type, 'invoiceDate' => $invoice->invoice_date->toDateString(),
            'sellerNTNCNIC' => $invoice->seller_ntn_cnic, 'sellerBusinessName' => $invoice->seller_business_name,
            'sellerProvince' => $invoice->seller_province, 'sellerAddress' => $invoice->seller_address,
            'buyerNTNCNIC' => $invoice->buyer_ntn_cnic ?? '', 'buyerBusinessName' => $invoice->buyer_business_name,
            'buyerProvince' => $invoice->buyer_province, 'buyerAddress' => $invoice->buyer_address,
            'buyerRegistrationType' => $invoice->buyer_registration_type->value,
            'invoiceRefNo' => $invoice->reference_invoice_no ?? '',
        ];
        if ($environment === 'sandbox') {
            if ($scenario !== $invoice->scenario_id) {
                throw ValidationException::withMessages(['scenario_id' => 'The FBR scenario must match the scenario saved on the approved invoice. Reopen the invoice to change it.']);
            }
            if (! preg_match('/^SN0(?:0[1-9]|1[0-9]|2[0-8])$/', $scenario ?? '')) {
                throw ValidationException::withMessages(['scenario_id' => 'Select an applicable sandbox scenario SN001–SN028.']);
            }
            $allowedScenarios = app(FbrScenarioRegistry::class)->allowedCodesForCompany($invoice->company);
            if (! in_array($scenario, $allowedScenarios, true)) {
                throw ValidationException::withMessages(['scenario_id' => 'This scenario is not enabled for the seller company.']);
            }
            $scenarioRules = config('fbr.supported_scenarios.'.$scenario);
            if ($scenarioRules === null) {
                throw ValidationException::withMessages(['scenario_id' => 'This sandbox scenario is not supported by this release.']);
            }
            if ($invoice->buyer_registration_type->value !== $scenarioRules['buyer_registration_type']) {
                throw ValidationException::withMessages(['scenario_id' => 'The selected scenario does not match the buyer registration type.']);
            }
            $payload['scenarioId'] = $scenario;
        }
        $errors = [];
        foreach (['sellerNTNCNIC', 'sellerBusinessName', 'sellerProvince', 'sellerAddress', 'buyerBusinessName', 'buyerProvince', 'buyerAddress'] as $field) {
            if (blank($payload[$field])) {
                $errors[$field] = 'This saved invoice field is required by FBR.';
            }
        }
        if (! preg_match('/^(?:[0-9]{7,8}|[0-9]{13})$/', (string) $payload['sellerNTNCNIC'])) {
            $errors['sellerNTNCNIC'] = 'Seller NTN must contain 7 or 8 digits, or a CNIC must contain 13 digits.';
        }
        if ($payload['buyerRegistrationType'] === 'Registered' && blank($payload['buyerNTNCNIC'])) {
            $errors['buyerNTNCNIC'] = 'Registered buyers require NTN/CNIC.';
        }
        $scenarioSaleType = $environment === 'sandbox' ? config('fbr.supported_scenarios.'.$scenario.'.sale_type') : null;
        $payload['items'] = $invoice->items->map(function ($line, $index) use (&$errors, $scenarioSaleType): array {
            foreach (['hs_code', 'product_description', 'tax_rate_label', 'unit_of_measurement', 'sale_type'] as $field) {
                if (blank($line->$field)) {
                    $errors['lines.'.$index.'.'.$field] = 'Item '.($index + 1).': '.$field.' is required for FBR.';
                }
            }
            if ($scenarioSaleType !== null && strcasecmp(trim((string) $line->sale_type), trim((string) $scenarioSaleType)) !== 0) {
                $errors['lines.'.$index.'.sale_type'] = 'Item '.($index + 1).': sale type is not supported by the saved sandbox scenario.';
            }

            return [
                'hsCode' => $line->hs_code, 'productDescription' => $line->product_description,
                'rate' => $line->tax_rate_label, 'uoM' => $line->unit_of_measurement,
                'quantity' => (float) $line->quantity, 'totalValues' => (float) $line->total_values,
                'valueSalesExcludingST' => (float) $line->value_sales_excluding_st,
                'fixedNotifiedValueOrRetailPrice' => (float) $line->fixed_notified_value_or_retail_price,
                'salesTaxApplicable' => (float) $line->sales_tax_applicable,
                'salesTaxWithheldAtSource' => (float) $line->sales_tax_withheld_at_source,
                'extraTax' => (float) $line->extra_tax, 'furtherTax' => (float) $line->further_tax,
                'sroScheduleNo' => $line->sro_schedule_no ?? '', 'fedPayable' => (float) $line->fed_payable,
                'discount' => (float) $line->discount, 'saleType' => $line->sale_type,
                'sroItemSerialNo' => $line->sro_item_serial_no ?? '',
            ];
        })->all();
        if ($invoice->items->isEmpty()) {
            $errors['items'] = 'At least one item is required.';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $payload;
    }

    public function run(Company $company, User $user, int $id, array $data, string $operation): array
    {
        $environment = $data['environment'];
        if (! in_array($environment, $this->environments($company), true)) {
            throw ValidationException::withMessages(['environment' => 'Add a token for the selected environment in Company Details.']);
        }
        $token = $company->fbrIntegrations()->where('environment', $environment)->firstOrFail()->token;
        $submission = DB::transaction(function () use ($company, $user, $id, $data, $environment, $operation, $token): FbrSubmission {
            $settings = Company::whereKey($company->id)->lockForUpdate()->firstOrFail();
            abort_unless($settings->fbr_environment->value === $environment, 409, 'Company FBR environment changed. Reload before sending.');
            $currentToken = $settings->fbrIntegrations()->where('environment', $environment)->firstOrFail()->token;
            abort_unless(hash_equals($token, $currentToken), 409, 'FBR credentials changed. Reload before sending.');
            $invoice = $company->invoices()->with('items')->whereKey($id)->lockForUpdate()->firstOrFail();
            abort_unless(hash_equals($this->invoices->version($invoice), $data['version']), 409, 'Invoice changed. Reload before submitting.');
            abort_unless($invoice->status === InvoiceStatus::Approved, 422, 'Approve the invoice before FBR validation or submission.');
            $blocked = $invoice->fbrSubmissions()->where('operation', 'submit')->where(function ($query) use ($environment): void {
                $query->whereIn('status', ['processing', 'pending', 'unknown'])->orWhere(fn ($query) => $query->where('status', 'accepted')->where('environment', $environment));
            })->exists();
            abort_if($blocked, 409, 'Submission is accepted, in progress, or its result is unknown. Do not resend; review submission history with PRAL.');
            abort_if($invoice->fbrSubmissions()->where('idempotency_key', $data['request_id'])->exists(), 409, 'This request was already processed. Reload submission history.');
            $this->validateOfficialReferences($company, $invoice);
            $payload = $this->payload($invoice, $environment, $data['scenario_id'] ?? null);
            $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR).'|'.hash('sha256', $token));
            if ($operation === 'submit') {
                $validation = $invoice->fbrSubmissions()->where('environment', $environment)->where('operation', 'validate')->where('payload_hash', $hash)->latest('id')->first();
                $lastSubmitId = (int) $invoice->fbrSubmissions()->where('environment', $environment)->where('operation', 'submit')->max('id');
                abort_unless($validation && $validation->id > $lastSubmitId && $validation->status->value === 'accepted' && $validation->completed_at?->gt(now()->subMinutes(10)), 422, 'Validate this exact payload successfully within 10 minutes before submitting.');
            }
            $attempt = (int) $invoice->fbrSubmissions()->where('environment', $environment)->where('operation', $operation)->max('attempt_number') + 1;
            $record = $invoice->fbrSubmissions()->make([
                'attempt_number' => $attempt, 'idempotency_key' => $data['request_id'], 'environment' => $environment,
                'operation' => $operation, 'status' => 'processing', 'endpoint' => config('fbr.'.$environment.'_'.$operation.'_url'),
                'request_payload' => $payload, 'payload_hash' => $hash, 'submitted_at' => now(),
            ]);
            $record->forceFill(['company_id' => $company->id, 'submitted_by' => $user->id])->save();
            if ($operation === 'submit') {
                $invoice->forceFill(['fbr_status' => 'pending', 'fbr_environment' => $environment])->save();
            }

            return $record;
        });
        try {
            $response = Http::withToken($token)->acceptJson()->asJson()->connectTimeout(10)->timeout(35)->post($submission->endpoint, $submission->request_payload);
            $body = str_replace($token, '[redacted]', $response->body());
            $json = json_decode($body, true);
            $validation = is_array($json) ? ($json['validationResponse'] ?? []) : [];
            $lines = is_array($validation) && is_array($validation['invoiceStatuses'] ?? null) ? $validation['invoiceStatuses'] : [];
            $valid = $response->successful() && ($validation['statusCode'] ?? null) === '00' && strtolower($validation['status'] ?? '') === 'valid';
            foreach ($lines as $line) {
                if (($line['statusCode'] ?? null) !== '00' || strtolower($line['status'] ?? '') !== 'valid') {
                    $valid = false;
                }
            }
            $number = is_array($json) ? ($json['invoiceNumber'] ?? null) : null;
            if ($operation === 'submit') {
                $valid = $valid && is_string($number) && $number !== '' && count($lines) === count($submission->request_payload['items']);
            }
            $explicitReject = in_array($response->status(), [400, 401, 402, 403, 404, 405, 422, 429], true) || (($validation['statusCode'] ?? null) === '01') || strtolower($validation['status'] ?? '') === 'invalid';
            foreach ($lines as $line) {
                if (($line['statusCode'] ?? null) === '01' || strtolower($line['status'] ?? '') === 'invalid') {
                    $explicitReject = true;
                }
            }
            $partialAcceptance = filled($number) || collect($lines)->contains(fn ($line) => filled($line['invoiceNo'] ?? null));
            $state = $valid ? 'accepted' : ($operation === 'submit' && (! $explicitReject || $partialAcceptance) ? 'unknown' : 'rejected');
            $errorCode = is_array($validation) ? ($validation['errorCode'] ?? null) : null;
            $message = $valid ? null : ($validation['error'] ?? null);
            if (! $message) {
                $message = $valid ? null : 'FBR request not accepted (HTTP '.$response->status().'). Check token, IP approval and item errors.';
            }
            $this->finish($submission, $state, $response->status(), is_array($json) ? $json : null, $body, $message,
                $valid && $operation === 'submit' ? $number : null, $lines, $errorCode);
        } catch (Throwable $exception) {
            $this->finish($submission, $operation === 'submit' ? 'unknown' : 'failed', null, null, null,
                $operation === 'submit' ? 'No reliable FBR response. Do not retry until PRAL confirms whether this invoice was accepted.' : 'Could not reach FBR validation. Check connectivity and try validation again.', null, [], null);
        }

        return $this->summary($submission->fresh('items'));
    }

    private function validateOfficialReferences(Company $company, Invoice $invoice): void
    {
        $saleTypes = $this->references->saleTypes($company)['records'];
        $provinces = $this->references->provinces($company)['records'];
        $provinceId = collect($provinces)->search(fn (string $description): bool => strcasecmp(trim($description), trim($invoice->seller_province)) === 0);
        if ($provinceId === false) {
            throw ValidationException::withMessages(['sellerProvince' => 'Seller province was not found in the current FBR province list.']);
        }
        $date = $invoice->invoice_date->format('d-M-Y');
        $ratesBySaleType = [];
        foreach ($invoice->items as $index => $line) {
            $this->references->validateHsUnit($company, $line->hs_code, $line->unit_of_measurement);
            $transactionTypeId = collect($saleTypes)->search(fn (string $description): bool => strcasecmp(trim($description), trim($line->sale_type)) === 0);
            if ($transactionTypeId === false) {
                throw ValidationException::withMessages(['lines.'.$index.'.sale_type' => 'Select a current FBR transaction type.']);
            }
            $ratesBySaleType[$transactionTypeId] ??= $this->references->rates($company, $date, (int) $transactionTypeId, (int) $provinceId)['records'];
            $rateDescriptions = $ratesBySaleType[$transactionTypeId];
            if (! in_array(mb_strtolower(trim($line->tax_rate_label)), array_map(fn (string $value): string => mb_strtolower(trim($value)), $rateDescriptions), true)) {
                throw ValidationException::withMessages(['lines.'.$index.'.tax_rate_label' => 'Select a current FBR-approved rate for this transaction type and invoice date.']);
            }
            if (preg_match('/^([0-9]+(?:\.[0-9]+)?)%/', trim($line->tax_rate_label), $matches) &&
                ! BigDecimal::of($matches[1])->isEqualTo(BigDecimal::of($line->tax_rate_percent))) {
                throw ValidationException::withMessages(['lines.'.$index.'.tax_rate_percent' => 'Tax percentage does not match the selected FBR rate.']);
            }
        }
        if (filled($invoice->buyer_ntn_cnic)) {
            $buyer = $this->references->buyerVerification($company, $invoice->buyer_ntn_cnic, $invoice->invoice_date->toDateString());
            $expected = $invoice->buyer_registration_type->value;
            if (strcasecmp($buyer['registration_type'], $expected) !== 0 || ($expected === 'Registered' && ! $buyer['active'])) {
                throw ValidationException::withMessages(['buyer_ntn_cnic' => 'Buyer registration type or active status does not match the current FBR record.']);
            }
        }
    }

    private function finish(FbrSubmission $submission, string $state, ?int $http, ?array $json, ?string $body, ?string $message, ?string $number, array $lines, ?string $errorCode = null): void
    {
        DB::transaction(function () use ($submission, $state, $http, $json, $body, $message, $number, $lines, $errorCode): void {
            $invoice = Invoice::whereKey($submission->invoice_id)->lockForUpdate()->firstOrFail();
            $stored = FbrSubmission::whereKey($submission->id)->lockForUpdate()->firstOrFail();
            if ($stored->reconciled_at !== null) {
                return;
            }
            $submission->forceFill(['status' => $state, 'http_status' => $http, 'response_payload' => $json, 'response_body' => $body,
                'business_status_code' => $json['validationResponse']['statusCode'] ?? null, 'error_message' => $message,
                'business_error_code' => $errorCode,
                'fbr_invoice_number' => $number, 'completed_at' => now()])->save();
            foreach ($lines as $index => $line) {
                $lineNumber = (int) ($line['itemSNo'] ?? $index + 1);
                if ($lineNumber < 1 || $lineNumber > $invoice->items()->count()) {
                    continue;
                }
                $record = $submission->items()->make(['invoice_id' => $invoice->id, 'line_number' => $lineNumber,
                    'status' => $line['status'] ?? null, 'status_code' => $line['statusCode'] ?? null,
                    'error_code' => $line['errorCode'] ?? null, 'error_message' => $line['error'] ?? null,
                    'fbr_invoice_number' => $line['invoiceNo'] ?? null, 'response_payload' => $line]);
                $record->forceFill(['company_id' => $submission->company_id])->save();
            }
            if ($submission->operation->value === 'submit') {
                $invoice->forceFill(['fbr_status' => $state, 'fbr_environment' => $submission->environment,
                    'fbr_invoice_number' => $number, 'fbr_accepted_at' => $state === 'accepted' ? now() : null,
                    'status' => $state === 'accepted' && $submission->environment->value === 'production' ? 'issued' : 'approved',
                    'issued_at' => $state === 'accepted' && $submission->environment->value === 'production' ? now() : null])->save();
            } elseif ($state === 'rejected') {
                $invoice->forceFill(['fbr_status' => 'validation_failed'])->save();
            }
        });
    }

    public function reconcile(Company $company, User $user, int $id, array $data): array
    {
        return DB::transaction(function () use ($company, $user, $id, $data): array {
            $invoice = $company->invoices()->whereKey($id)->lockForUpdate()->firstOrFail();
            $record = $invoice->fbrSubmissions()->where('operation', 'submit')->whereKey($data['submission_id'])->lockForUpdate()->firstOrFail();
            abort_unless($record->status->value === 'unknown' || ($record->status->value === 'processing' && $record->submitted_at->lt(now()->subMinutes(2))), 409, 'Only uncertain or stalled submission attempts can be reconciled.');
            $accepted = $data['outcome'] === 'accepted';
            $this->finish($record, $accepted ? 'accepted' : 'rejected', $record->http_status, $record->response_payload, $record->response_body,
                $accepted ? null : 'PRAL confirmation recorded: invoice was not accepted.', $accepted ? $data['fbr_invoice_number'] : null, [], $record->business_error_code);
            $record->refresh()->forceFill(['reconciled_at' => now(), 'reconciliation_notes' => 'Verified by ERP user '.$user->id.': '.$data['notes']])->save();

            return $this->summary($record->fresh('items'));
        });
    }

    public function summary(FbrSubmission $record): array
    {
        $company = $record->company;
        $sector = $company?->sector;
        $activity = $company?->business_activity;
        $headerEntry = $this->errors->entry($record->business_error_code, $sector, $activity);

        return [
            'id' => $record->id,
            'environment' => $record->environment->value,
            'operation' => $record->operation->value,
            'status' => $record->status->value,
            'reconciliation_notes' => $record->reconciliation_notes,
            'http_status' => $record->http_status,
            'error_code' => $record->business_error_code,
            'error' => $record->error_message,
            'category' => $this->errors->category($record->business_error_code),
            'guidance' => $this->errors->guidance($record->business_error_code, $sector, $activity),
            'solution' => $headerEntry['solution'] ?? null,
            'fbr_invoice_number' => $record->fbr_invoice_number,
            'created_at' => $record->created_at->toIso8601String(),
            'lines' => $record->items->map(function ($line) use ($sector, $activity) {
                $lineEntry = $this->errors->entry($line->error_code, $sector, $activity);

                return [
                    'line_number' => $line->line_number,
                    'status' => $line->status,
                    'error_code' => $line->error_code,
                    'error' => $line->error_message,
                    'category' => $this->errors->category($line->error_code),
                    'guidance' => $this->errors->guidance($line->error_code, $sector, $activity),
                    'solution' => $lineEntry['solution'] ?? null,
                ];
            })->all(),
        ];
    }
}
