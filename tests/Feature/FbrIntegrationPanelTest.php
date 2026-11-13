<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CompanyService;
use App\Services\FbrInvoiceService;
use Illuminate\Http\Client\ConnectionException;
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

    private function companyUser(bool $withProfile = true): array
    {
        $user = User::factory()->create()->fresh();
        $company = app(CompanyService::class)->createOwnedCompany($user, ['company_name' => 'FBR Panel Test']);
        $company->forceFill([
            'ntn_cnic' => '0123456',
            'province' => 'Sindh',
            'address' => 'Karachi',
            'business_activity' => $withProfile ? 'Service Provider' : null,
            'sector' => $withProfile ? 'Services' : null,
            // This legacy/manual list must not override the Section 10 mapping.
            'allowed_sandbox_scenarios' => ['SN001', 'SN099'],
        ])->save();

        return [$user, $company];
    }

    private function fakeVerificationAndSubmission(): void
    {
        Http::fake([
            config('fbr.statl_url') => Http::response(['status code' => '00', 'status' => 'Active']),
            config('fbr.registration_type_url') => Http::response([
                'statuscode' => '00',
                'REGISTRATION_NO' => '0123456',
                'REGISTRATION_TYPE' => 'Registered',
            ]),
            config('fbr.sandbox_submit_url') => Http::response([
                'invoiceNumber' => 'TEST-FBR-123',
                'validationResponse' => ['statusCode' => '00', 'status' => 'Valid', 'invoiceStatuses' => [
                    ['statusCode' => '00', 'status' => 'Valid'],
                ]],
            ]),
        ]);
    }

    private function saveAndVerify($user, string $token = 'sandbox-test-token'): void
    {
        $this->actingAs($user)->postJson('/api/company/sandbox-token', ['sandbox_token' => $token])->assertOk();
        $this->postJson('/api/company/verify-fbr')->assertOk()->assertJsonPath('verified', true);
    }

    public function test_status_uses_official_activity_sector_mapping_not_manual_scenario_override(): void
    {
        [$user] = $this->companyUser();
        $response = $this->actingAs($user)->getJson('/api/company/fbr/status')->assertOk();
        $response->assertJsonPath('scenarios.0.id', 'SN018')
            ->assertJsonPath('scenarios.1.id', 'SN019');
        $this->assertCount(2, $response->json('scenarios'));
    }

    public function test_verification_requires_company_profile_and_saved_company_sandbox_token(): void
    {
        [$user] = $this->companyUser(false);
        Http::fake();

        $this->actingAs($user)->postJson('/api/company/verify-fbr')
            ->assertUnprocessable()->assertJsonValidationErrors('business_activity');
        Http::assertNothingSent();

        [$user] = $this->companyUser();
        $this->actingAs($user)->postJson('/api/company/verify-fbr')
            ->assertUnprocessable()->assertJsonPath('verified', false);
        Http::assertNothingSent();
    }

    public function test_verification_uses_the_saved_company_token_and_persists_it_encrypted(): void
    {
        [$user, $company] = $this->companyUser();
        $this->fakeVerificationAndSubmission();
        $this->actingAs($user)->postJson('/api/company/sandbox-token', [
            'sandbox_token' => 'company-sandbox-secret',
        ])->assertOk();

        $this->postJson('/api/company/verify-fbr')->assertOk()->assertJsonPath('verified', true);
        $this->getJson('/api/company/fbr/status')->assertOk()
            ->assertJsonPath('verified', true)->assertJsonPath('sandbox_configured', true);

        $integration = $company->fresh()->fbrIntegrations()->where('environment', 'sandbox')->firstOrFail();
        $this->assertSame('company-sandbox-secret', $integration->token);
        $this->assertDatabaseMissing('fbr_integrations', ['company_id' => $company->id, 'token' => 'company-sandbox-secret']);
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer company-sandbox-secret'));
    }

    public function test_verification_identifies_the_fbr_endpoint_that_times_out(): void
    {
        [$user] = $this->companyUser();
        Http::fake([
            config('fbr.statl_url') => Http::response(['status code' => '00', 'status' => 'Active']),
            config('fbr.registration_type_url') => function () {
                throw new ConnectionException('cURL error 28: Operation timed out after 12002 milliseconds');
            },
        ]);

        $this->actingAs($user)->postJson('/api/company/sandbox-token', ['sandbox_token' => 'sandbox-test-token'])->assertOk();
        $this->postJson('/api/company/verify-fbr')
            ->assertStatus(503)
            ->assertJsonPath('message', 'FBR Get_Reg_Type verification request could not be completed.')
            ->assertJsonPath('errors.get_reg_type.0', 'FBR Get_Reg_Type endpoint did not respond before the 12-second timeout. This indicates an FBR gateway or network timeout, not a confirmed invalid token. Retry later; if it continues, contact PRAL.');
    }

    public function test_certification_rejects_a_token_different_from_the_verified_saved_token(): void
    {
        [$user] = $this->companyUser();
        $this->fakeVerificationAndSubmission();
        $this->saveAndVerify($user, 'verified-token');

        $this->postJson('/api/company/run-sandbox-certification', ['sandbox_token' => 'different-token'])
            ->assertUnprocessable();
        Http::assertNotSent(fn ($request) => str_ends_with($request->url(), 'postinvoicedata_sb'));
    }

    public function test_full_applicable_sandbox_suite_must_pass_before_production_token_is_unlocked(): void
    {
        [$user, $company] = $this->companyUser();
        $this->fakeVerificationAndSubmission();
        $this->saveAndVerify($user);

        $response = $this->postJson('/api/company/run-sandbox-certification')->assertOk()
            ->assertJsonPath('all_passed', true);
        $this->assertSame(['SN018', 'SN019'], array_column($response->json('results'), 'scenario_id'));
        Http::assertSentCount(4); // two taxpayer lookups + two applicable sandbox scenarios

        $this->getJson('/api/company/fbr/status')->assertOk()->assertJsonPath('all_passed', true);
        $this->postJson('/api/company/production-token', ['production_token' => 'production-secret'])
            ->assertOk()->assertJsonPath('saved', true);
        $this->assertDatabaseHas('fbr_integrations', ['company_id' => $company->id, 'environment' => 'production']);
    }

    public function test_sandbox_token_rotation_invalidates_verification_certification_and_production_unlock(): void
    {
        [$user, $company] = $this->companyUser();
        $this->fakeVerificationAndSubmission();
        $this->saveAndVerify($user);

        $this->postJson('/api/company/run-sandbox-certification')->assertOk()->assertJsonPath('all_passed', true);
        $this->postJson('/api/company/production-token', ['production_token' => 'production-secret'])->assertOk();

        $this->postJson('/api/company/sandbox-token', ['sandbox_token' => 'rotated-token'])->assertOk();
        $this->assertSame(1, $company->fresh()->fbrIntegrations()->where('environment', 'sandbox')->count());
        $this->assertSame('rotated-token', $company->fresh()->fbrIntegrations()->where('environment', 'sandbox')->firstOrFail()->token);
        $this->getJson('/api/company/fbr/status')->assertOk()
            ->assertJsonPath('verified', false)->assertJsonPath('all_passed', false)
            ->assertJsonPath('production_configured', false);
        $this->postJson('/api/company/production-token', ['production_token' => 'another-production-secret'])
            ->assertUnprocessable();
        $this->assertDatabaseMissing('fbr_integrations', ['company_id' => $company->id, 'environment' => 'production']);
    }

    public function test_profile_change_invalidates_previous_verification_and_certification(): void
    {
        [$user, $company] = $this->companyUser();
        $this->fakeVerificationAndSubmission();
        $this->saveAndVerify($user);
        $this->postJson('/api/company/run-sandbox-certification')->assertOk()->assertJsonPath('all_passed', true);
        $this->postJson('/api/company/production-token', ['production_token' => 'production-secret'])->assertOk();

        $company->forceFill(['sector' => 'FMCG', 'fbr_environment' => 'production'])->save();
        $this->getJson('/api/company/fbr/status')->assertOk()
            ->assertJsonPath('verified', false)->assertJsonPath('all_passed', false)
            ->assertJsonPath('production_configured', false);
        $this->assertSame([], app(FbrInvoiceService::class)->environments($company->fresh()));
        $this->postJson('/api/company/production-token', ['production_token' => 'production-secret'])
            ->assertUnprocessable();
    }

    public function test_fbr_integration_endpoints_require_authentication(): void
    {
        $this->getJson('/api/company/fbr/status')->assertUnauthorized();
        $this->postJson('/api/company/verify-fbr')->assertUnauthorized();
        $this->postJson('/api/company/sandbox-token')->assertUnauthorized();
        $this->postJson('/api/company/run-sandbox-certification')->assertUnauthorized();
        $this->postJson('/api/company/production-token')->assertUnauthorized();
    }
}
