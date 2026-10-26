<?php

namespace Tests\Feature;

use App\Enums\FbrIntegrationStatus;
use App\Models\User;
use App\Services\CompanyService;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FbrIntegrationPanelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'erp-fbr-system']);
        DB::connection('mysql')->beginTransaction();
    }

    protected function tearDown(): void
    {
        try {
            while (DB::connection('mysql')->transactionLevel() > 0) {
                DB::connection('mysql')->rollBack();
            }
        } finally {
            parent::tearDown();
        }
    }

    private function companyUser(array $scenarios = ['SN001', 'SN002']): array
    {
        $user = User::factory()->create()->fresh();
        $company = app(CompanyService::class)->createOwnedCompany($user, ['company_name' => 'FBR Panel Test']);
        $company->forceFill([
            'ntn_cnic' => '0123456',
            'province' => 'Sindh',
            'address' => 'Karachi',
            'allowed_sandbox_scenarios' => $scenarios,
        ])->save();

        return [$user, $company];
    }

    public function test_fbr_integration_endpoints_require_authentication(): void
    {
        $this->getJson('/api/company/fbr/status')->assertUnauthorized();
        $this->postJson('/api/company/verify-fbr')->assertUnauthorized();
        $this->postJson('/api/company/run-sandbox-certification')->assertUnauthorized();
        $this->postJson('/api/company/production-token')->assertUnauthorized();
    }

    public function test_status_endpoint_returns_assigned_scenarios_and_support_flags(): void
    {
        [$user, $company] = $this->companyUser(['SN001', 'SN003']);

        $response = $this->actingAs($user)->getJson('/api/company/fbr/status')->assertOk();

        $response->assertJsonPath('scenarios.0.id', 'SN001');
        $response->assertJsonPath('scenarios.0.is_supported', true);
        $response->assertJsonPath('scenarios.1.id', 'SN003');
        $response->assertJsonPath('scenarios.1.is_supported', false);
    }

    public function test_empty_assigned_scenarios_blocks_certification(): void
    {
        [$user, $company] = $this->companyUser([]);
        Cache::store(config('fbr.reference_cache_store'))->put('fbr:company-verification:v1:'.$company->id, [
            'verified' => true,
            'status' => 'Active',
            'registration_type' => 'Registered',
            'checked_at' => now()->toIso8601String(),
            'profile_fingerprint' => hash('sha256', json_encode([$company->ntn_cnic, $company->name, $company->province, $company->address], JSON_THROW_ON_ERROR)),
        ], now()->addHours(12));

        $this->actingAs($user)->postJson('/api/company/run-sandbox-certification', [
            'sandbox_token' => 'sandbox-test-token',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['sandbox_token']);

        $status = $this->getJson('/api/company/fbr/status')->assertOk();
        $status->assertJsonPath('scenarios', []);
        $status->assertJsonPath('all_passed', false);
    }

    public function test_sandbox_and_production_credentials_stay_locked_until_verification_and_certification(): void
    {
        [$user, $company] = $this->companyUser();
        $cache = Cache::store(config('fbr.reference_cache_store'));
        $cache->forget('fbr:company-verification:v1:'.$company->id);
        $cache->forget('fbr:sandbox-certification:v1:'.$company->id);

        $this->actingAs($user)->postJson('/api/company/run-sandbox-certification', [
            'sandbox_token' => 'sandbox-test-token', 'scenario_id' => 'SN001',
        ])->assertUnprocessable();

        $this->postJson('/api/company/production-token', [
            'production_token' => 'production-test-token',
        ])->assertUnprocessable();

        $this->assertDatabaseMissing('fbr_integrations', [
            'company_id' => $company->id, 'environment' => 'production',
        ]);
    }

    public function test_sandbox_certification_uses_each_scenario_tax_values_and_calculated_totals(): void
    {
        [$user, $company] = $this->companyUser(['SN001', 'SN002']);
        Cache::store(config('fbr.reference_cache_store'))->put('fbr:company-verification:v1:'.$company->id, [
            'verified' => true,
            'status' => 'Active',
            'registration_type' => 'Registered',
            'checked_at' => now()->toIso8601String(),
            'profile_fingerprint' => hash('sha256', json_encode([$company->ntn_cnic, $company->name, $company->province, $company->address], JSON_THROW_ON_ERROR)),
        ], now()->addHours(12));
        Http::fake([config('fbr.sandbox_submit_url') => Http::response([
            'invoiceNumber' => 'TEST-FBR-123',
            'validationResponse' => ['statusCode' => '00', 'status' => 'Valid', 'invoiceStatuses' => [
                ['statusCode' => '00', 'status' => 'Valid'],
            ]],
        ])]);

        $this->actingAs($user)->postJson('/api/company/run-sandbox-certification', ['sandbox_token' => 'sandbox-test-token'])
            ->assertOk()->assertJsonPath('all_passed', true);

        Http::assertSent(fn ($request) => str_ends_with($request->url(), 'postinvoicedata_sb')
            && $request['scenarioId'] === 'SN001'
            && (float) $request['items'][0]['furtherTax'] === 0.0
            && (float) $request['items'][0]['totalValues'] === 1180.0);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), 'postinvoicedata_sb')
            && $request['scenarioId'] === 'SN002'
            && (float) $request['items'][0]['furtherTax'] === 120.0
            && (float) $request['items'][0]['totalValues'] === 1300.0);
        Http::assertSentCount(2);

        // Once certified, production token can be saved
        $this->postJson('/api/company/production-token', ['production_token' => 'live-prod-token'])
            ->assertOk()
            ->assertJsonPath('saved', true);

        $this->assertDatabaseHas('fbr_integrations', [
            'company_id' => $company->id,
            'environment' => 'production',
        ]);
    }

    public function test_unsupported_assigned_scenario_records_failure_and_blocks_production_token(): void
    {
        [$user, $company] = $this->companyUser(['SN001', 'SN003']);
        Cache::store(config('fbr.reference_cache_store'))->put('fbr:company-verification:v1:'.$company->id, [
            'verified' => true,
            'status' => 'Active',
            'registration_type' => 'Registered',
            'checked_at' => now()->toIso8601String(),
            'profile_fingerprint' => hash('sha256', json_encode([$company->ntn_cnic, $company->name, $company->province, $company->address], JSON_THROW_ON_ERROR)),
        ], now()->addHours(12));

        Http::fake([config('fbr.sandbox_submit_url') => Http::response([
            'invoiceNumber' => 'TEST-FBR-123',
            'validationResponse' => ['statusCode' => '00', 'status' => 'Valid', 'invoiceStatuses' => [
                ['statusCode' => '00', 'status' => 'Valid'],
            ]],
        ])]);

        $response = $this->actingAs($user)->postJson('/api/company/run-sandbox-certification', [
            'sandbox_token' => 'sandbox-test-token',
        ])->assertOk();

        $response->assertJsonPath('all_passed', false);
        $response->assertJsonPath('results.0.scenario_id', 'SN001');
        $response->assertJsonPath('results.0.passed', true);
        $response->assertJsonPath('results.1.scenario_id', 'SN003');
        $response->assertJsonPath('results.1.passed', false);
        $response->assertJsonPath('results.1.error_code', 'UNSUPPORTED');

        // Only SN001 was submitted to sandbox HTTP; SN003 was blocked without sending fake data
        Http::assertSentCount(1);

        // Production token must remain locked
        $this->postJson('/api/company/production-token', ['production_token' => 'live-prod-token'])
            ->assertUnprocessable();
    }

    public function test_single_scenario_run_does_not_mark_certification_as_passed_when_other_scenarios_pending(): void
    {
        [$user, $company] = $this->companyUser(['SN001', 'SN002']);
        Cache::store(config('fbr.reference_cache_store'))->put('fbr:company-verification:v1:'.$company->id, [
            'verified' => true,
            'status' => 'Active',
            'registration_type' => 'Registered',
            'checked_at' => now()->toIso8601String(),
            'profile_fingerprint' => hash('sha256', json_encode([$company->ntn_cnic, $company->name, $company->province, $company->address], JSON_THROW_ON_ERROR)),
        ], now()->addHours(12));

        Http::fake([config('fbr.sandbox_submit_url') => Http::response([
            'invoiceNumber' => 'TEST-FBR-SN001',
            'validationResponse' => ['statusCode' => '00', 'status' => 'Valid', 'invoiceStatuses' => [
                ['statusCode' => '00', 'status' => 'Valid'],
            ]],
        ])]);

        // Run ONLY SN001
        $response = $this->actingAs($user)->postJson('/api/company/run-sandbox-certification', [
            'sandbox_token' => 'sandbox-test-token',
            'scenario_id' => 'SN001',
        ])->assertOk();

        // Must NOT be all passed because SN002 is still pending
        $response->assertJsonPath('all_passed', false);
        $this->assertCount(1, $response->json('results'));

        // Status API must also report all_passed false
        $status = $this->getJson('/api/company/fbr/status')->assertOk();
        $status->assertJsonPath('all_passed', false);

        // Production token cannot be saved
        $this->postJson('/api/company/production-token', ['production_token' => 'live-prod-token'])
            ->assertUnprocessable();
    }

    public function test_changing_company_profile_invalidates_cached_certification_and_locks_production_token(): void
    {
        [$user, $company] = $this->companyUser(['SN001', 'SN002']);
        $cache = Cache::store(config('fbr.reference_cache_store'));
        $cache->put('fbr:company-verification:v1:'.$company->id, [
            'verified' => true,
            'status' => 'Active',
            'registration_type' => 'Registered',
            'checked_at' => now()->toIso8601String(),
            'profile_fingerprint' => hash('sha256', json_encode([$company->ntn_cnic, $company->name, $company->province, $company->address], JSON_THROW_ON_ERROR)),
        ], now()->addHours(12));

        Http::fake([config('fbr.sandbox_submit_url') => Http::response([
            'invoiceNumber' => 'TEST-FBR-ALL',
            'validationResponse' => ['statusCode' => '00', 'status' => 'Valid', 'invoiceStatuses' => [
                ['statusCode' => '00', 'status' => 'Valid'],
            ]],
        ])]);

        // Run full certification successfully
        $this->actingAs($user)->postJson('/api/company/run-sandbox-certification', [
            'sandbox_token' => 'sandbox-test-token',
        ])->assertOk()->assertJsonPath('all_passed', true);

        // Company profile changes (e.g. registered address changed)
        $company->address = 'Updated Address 456';
        $company->save();

        // Status API must immediately fail all_passed because profile fingerprint changed
        $this->getJson('/api/company/fbr/status')->assertOk()->assertJsonPath('all_passed', false);

        // Production token saving must be rejected
        $this->postJson('/api/company/production-token', ['production_token' => 'live-prod-token'])
            ->assertUnprocessable();
    }

    public function test_changing_sandbox_token_invalidates_previous_scenario_results(): void
    {
        [$user, $company] = $this->companyUser(['SN001', 'SN002']);
        Cache::store(config('fbr.reference_cache_store'))->put('fbr:company-verification:v1:'.$company->id, [
            'verified' => true,
            'status' => 'Active',
            'registration_type' => 'Registered',
            'checked_at' => now()->toIso8601String(),
            'profile_fingerprint' => hash('sha256', json_encode([$company->ntn_cnic, $company->name, $company->province, $company->address], JSON_THROW_ON_ERROR)),
        ], now()->addHours(12));

        Http::fake([config('fbr.sandbox_submit_url') => Http::response([
            'invoiceNumber' => 'TEST-FBR-SN',
            'validationResponse' => ['statusCode' => '00', 'status' => 'Valid', 'invoiceStatuses' => [
                ['statusCode' => '00', 'status' => 'Valid'],
            ]],
        ])]);

        // Step 1: Run SN001 with token A
        $this->actingAs($user)->postJson('/api/company/run-sandbox-certification', [
            'sandbox_token' => 'token-A',
            'scenario_id' => 'SN001',
        ])->assertOk()->assertJsonPath('all_passed', false);

        // Step 2: Run SN002 with a DIFFERENT token B
        $response = $this->postJson('/api/company/run-sandbox-certification', [
            'sandbox_token' => 'token-B',
            'scenario_id' => 'SN002',
        ])->assertOk();

        // Results under token-A must NOT be carried over to token-B; all_passed must be false
        $response->assertJsonPath('all_passed', false);
        $results = $response->json('results');
        $this->assertCount(1, $results);
        $this->assertSame('SN002', $results[0]['scenario_id']);
    }

    public function test_company_verification_does_not_fake_success_without_an_authorized_lookup_token(): void
    {
        [$user, $company] = $this->companyUser();
        config(['fbr.verification_token' => null]);
        Http::fake();

        $this->actingAs($user)->postJson('/api/company/verify-fbr')
            ->assertStatus(503)
            ->assertJsonPath('verified', false);

        Http::assertNothingSent();
    }

    public function test_previously_complete_certification_followed_by_single_scenario_run_does_not_count_as_new_complete_certification(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        [$user, $company] = $this->companyUser(['SN001', 'SN002']);
        Cache::store(config('fbr.reference_cache_store'))->put('fbr:company-verification:v1:'.$company->id, [
            'verified' => true,
            'status' => 'Active',
            'registration_type' => 'Registered',
            'checked_at' => now()->toIso8601String(),
            'profile_fingerprint' => hash('sha256', json_encode([$company->ntn_cnic, $company->name, $company->province, $company->address], JSON_THROW_ON_ERROR)),
        ], now()->addHours(12));

        Http::fake([config('fbr.sandbox_submit_url') => Http::response([
            'invoiceNumber' => 'TEST-FBR-SUCCESS',
            'validationResponse' => ['statusCode' => '00', 'status' => 'Valid', 'invoiceStatuses' => [
                ['statusCode' => '00', 'status' => 'Valid'],
            ]],
        ])]);

        // Step 1: Run full certification where all assigned scenarios are executed
        $this->actingAs($user)->postJson('/api/company/run-sandbox-certification', [
            'sandbox_token' => 'sandbox-test-token',
        ])->assertOk()->assertJsonPath('all_passed', true);

        // Verification status and production token saving work
        $this->getJson('/api/company/fbr/status')->assertOk()->assertJsonPath('all_passed', true);
        $this->postJson('/api/company/production-token', ['production_token' => 'prod-token-1'])->assertOk();

        // Step 2: Now run ONLY SN001 (single-scenario retry / incomplete run)
        $incompleteRunResponse = $this->postJson('/api/company/run-sandbox-certification', [
            'sandbox_token' => 'sandbox-test-token',
            'scenario_id' => 'SN001',
        ])->assertOk();

        // Must NOT count as a new complete certification even though SN002 previously passed in cache!
        $incompleteRunResponse->assertJsonPath('all_passed', false);

        // Status endpoint must also report all_passed as false
        $this->getJson('/api/company/fbr/status')->assertOk()->assertJsonPath('all_passed', false);

        // Integration status in database must be revoked back to Configured
        $sandboxIntegration = $company->fresh()->fbrIntegrations()->where('environment', 'sandbox')->first();
        $this->assertSame(FbrIntegrationStatus::Configured, $sandboxIntegration->status);
        $this->assertNull($sandboxIntegration->verified_at);

        // Production token endpoint must be locked again
        $this->postJson('/api/company/production-token', ['production_token' => 'prod-token-2'])->assertUnprocessable();

        // Step 3: Running a full certification again re-certifies the company
        $this->postJson('/api/company/run-sandbox-certification', [
            'sandbox_token' => 'sandbox-test-token',
        ])->assertOk()->assertJsonPath('all_passed', true);

        $this->getJson('/api/company/fbr/status')->assertOk()->assertJsonPath('all_passed', true);
        $this->postJson('/api/company/production-token', ['production_token' => 'prod-token-3'])->assertOk();
    }
}
