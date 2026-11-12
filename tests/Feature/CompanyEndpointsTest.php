<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CompanyService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CompanyEndpointsTest extends TestCase
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
            $connection = DB::connection('mysql');
            while ($connection->transactionLevel() > 0) {
                $connection->rollBack();
            }
        } finally {
            parent::tearDown();
        }
    }

    private function payload(): array
    {
        return ['name' => 'Updated Seller', 'seller_ntn' => '0123456', 'seller_cnic' => '3520112345671', 'province' => 'Sindh', 'address' => 'Test address',
            'invoice_series' => 'INV', 'currency' => 'PKR', 'fbr_environment' => 'sandbox',
            'business_activity' => ['Manufacturer', 'Retailer'], 'sector' => ['All Other Sectors', 'Steel'],
            'allowed_sandbox_scenarios' => ['SN001'], 'is_sales_tax_profile_retailer' => true];
    }

    public function test_owner_updates_profile_and_encrypted_credentials_without_exposing_token(): void
    {
        $user = User::factory()->create()->fresh();
        $company = app(CompanyService::class)->createOwnedCompany($user, ['company_name' => 'Review']);
        $url = '/api/v1/companies/'.$company->id;
        $this->actingAs($user)->putJson($url, [...$this->payload(), 'fbr_environment' => 'production', 'production_token' => 'review-production-only'])->assertOk();
        $this->putJson($url, [...$this->payload(), 'sandbox_token' => 'review-token-only', 'is_active' => false])->assertOk()->assertJsonPath('data.credentials.sandbox.configured', true)->assertJsonMissingPath('data.sandbox_token')->assertJsonMissingPath('data.credentials.sandbox.token');
        $this->assertSame(['Manufacturer', 'Retailer'], $company->fresh()->business_activity);
        $this->assertSame(['All Other Sectors', 'Steel'], $company->fresh()->sector);
        $this->assertTrue($company->fresh()->is_sales_tax_profile_retailer);
        $integration = $company->fbrIntegrations()->where('environment', 'sandbox')->firstOrFail();
        $this->assertNotSame('review-token-only', $integration->getRawOriginal('token'));
        $this->assertSame('review-token-only', $integration->token);
        $this->assertSame('review-production-only', $company->fbrIntegrations()->where('environment', 'production')->firstOrFail()->token);
        $this->assertTrue($company->fresh()->is_active);
        $this->putJson($url, [...$this->payload(), 'sandbox_token' => ''])->assertOk();
        $this->assertSame('review-token-only', $integration->fresh()->token);
        $this->getJson($url)->assertOk()->assertJsonPath('data.name', 'Updated Seller')->assertJsonPath('data.is_sales_tax_profile_retailer', true);
    }

    public function test_other_company_and_member_updates_are_denied(): void
    {
        $owner = User::factory()->create()->fresh();
        $other = User::factory()->create()->fresh();
        $company = app(CompanyService::class)->createOwnedCompany($owner, ['company_name' => 'Private']);
        $url = '/api/v1/companies/'.$company->id;
        $this->actingAs($other)->getJson($url)->assertNotFound();
        $this->putJson($url, $this->payload())->assertNotFound();
        $company->users()->attach($other->id, ['role' => 'member', 'is_active' => true]);
        $this->getJson($url)->assertOk()->assertJsonPath('data.can_edit', false);
        $this->putJson($url, $this->payload())->assertForbidden();
        $company->users()->updateExistingPivot($other->id, ['is_active' => false]);
        $this->getJson($url)->assertNotFound();
    }

    public function test_invalid_seller_identity_and_unauthenticated_access_are_rejected(): void
    {
        $user = User::factory()->create()->fresh();
        $company = app(CompanyService::class)->createOwnedCompany($user, ['company_name' => 'Review']);
        $url = '/api/v1/companies/'.$company->id;
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs($user)->putJson($url, [...$this->payload(), 'seller_ntn' => '123', 'province' => 'Wrong', 'address' => ''])->assertUnprocessable()->assertJsonValidationErrors(['seller_ntn', 'province', 'address']);
        $this->assertSame('Review', $company->fresh()->name);
        $user->forceFill(['is_active' => false])->save();
        $this->getJson($url)->assertForbidden();
    }

    public function test_company_email_is_separate_from_owner_login_and_is_normalized(): void
    {
        $user = User::factory()->create(['password' => 'Email-test-password-123'])->fresh();
        $oldEmail = $user->email;
        $company = app(CompanyService::class)->createOwnedCompany($user, ['company_name' => 'Email Review']);
        $newEmail = 'changed-'.bin2hex(random_bytes(8)).'@example.invalid';
        $this->actingAs($user, 'web')->putJson('/api/v1/companies/'.$company->id, [...$this->payload(), 'email' => strtoupper($newEmail)])
            ->assertOk()->assertJsonPath('data.email', $newEmail);
        $this->assertSame($oldEmail, $user->fresh()->email);
        $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.email', $oldEmail);
        $this->postJson('/api/v1/auth/logout')->assertOk();
        Auth::forgetGuards();
        $this->postJson('/api/v1/auth/login', ['email' => $newEmail, 'password' => 'Email-test-password-123'])->assertUnprocessable();
        $this->postJson('/api/v1/auth/login', ['email' => $oldEmail, 'password' => 'Email-test-password-123'])->assertOk();
    }

    public function test_duplicate_or_blank_company_email_does_not_change_profile_or_login(): void
    {
        $user = User::factory()->create()->fresh();
        $other = User::factory()->create()->fresh();
        $company = app(CompanyService::class)->createOwnedCompany($user, ['company_name' => 'Email Review']);
        $oldEmail = $user->email;
        foreach (['', 'not-an-email'] as $email) {
            $this->actingAs($user)->putJson('/api/v1/companies/'.$company->id, [...$this->payload(), 'email' => $email])->assertUnprocessable()->assertJsonValidationErrors('email');
            $this->assertSame($oldEmail, $user->fresh()->email);
            $this->assertSame($oldEmail, $company->fresh()->email);
            $this->assertSame('Email Review', $company->fresh()->name);
        }
        $this->actingAs($user)->putJson('/api/v1/companies/'.$company->id, [...$this->payload(), 'email' => $other->email])->assertOk();
        $this->assertSame($oldEmail, $user->fresh()->email);
    }

    public function test_only_selected_environment_token_can_be_updated(): void
    {
        $owner = User::factory()->create()->fresh();
        $company = app(CompanyService::class)->createOwnedCompany($owner, ['company_name' => 'Token isolation']);
        $url = '/api/v1/companies/'.$company->id;
        $this->actingAs($owner)->putJson($url, [...$this->payload(), 'production_token' => 'inactive-token'])->assertUnprocessable()->assertJsonValidationErrors('production_token');
        $this->putJson($url, [...$this->payload(), 'sandbox_token' => 'sandbox-only'])->assertOk();
        $this->putJson($url, [...$this->payload(), 'fbr_environment' => 'production', 'sandbox_token' => 'inactive-token'])->assertUnprocessable()->assertJsonValidationErrors('sandbox_token');
        $this->putJson($url, [...$this->payload(), 'fbr_environment' => 'production', 'production_token' => 'production-only'])->assertOk();
        $this->assertSame('sandbox-only', $company->fbrIntegrations()->where('environment', 'sandbox')->firstOrFail()->token);
        $this->assertSame('production-only', $company->fbrIntegrations()->where('environment', 'production')->firstOrFail()->token);
    }

    public function test_user_can_create_additional_company_with_isolated_owner_membership(): void
    {
        $owner = User::factory()->create()->fresh();
        $first = app(CompanyService::class)->createOwnedCompany($owner, ['company_name' => 'First Company']);
        $response = $this->actingAs($owner)->postJson('/api/v1/companies', [
            'name' => 'Second Company', 'seller_ntn' => '7654321', 'seller_cnic' => '3520112345671', 'province' => 'Sindh',
            'address' => 'Second address', 'email' => 'second@example.invalid', 'phone' => '+92 300 1234567',
        ])->assertCreated()->assertJsonPath('data.name', 'Second Company');
        $secondId = $response->json('data.id');

        $this->assertNotSame($first->id, $secondId);
        $this->assertSame('owner', $owner->companies()->findOrFail($secondId)->pivot->role->value);
        $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonCount(2, 'data.companies');
    }

    public function test_owner_manages_existing_registered_members_but_member_cannot(): void
    {
        $owner = User::factory()->create()->fresh();
        $member = User::factory()->create()->fresh();
        $company = app(CompanyService::class)->createOwnedCompany($owner, ['company_name' => 'Membership Company']);
        $url = '/api/v1/companies/'.$company->id.'/members';

        $added = $this->actingAs($owner)->postJson($url, ['email' => strtoupper($member->email), 'role' => 'member'])
            ->assertCreated()->assertJsonPath('data.role', 'member')->assertJsonPath('data.is_active', true)->json('data');
        $this->getJson($url)->assertOk()->assertJsonCount(2, 'data');
        $this->putJson($url.'/'.$added['id'], ['role' => 'member', 'is_active' => false])
            ->assertOk()->assertJsonPath('data.is_active', false);
        $this->putJson($url.'/'.$added['id'], ['role' => 'member', 'is_active' => true])->assertOk();

        $this->actingAs($member)->getJson($url)->assertForbidden();
        $this->postJson($url, ['email' => User::factory()->create()->email, 'role' => 'member'])->assertForbidden();
        $this->actingAs($owner)->deleteJson($url.'/'.$added['id'])->assertOk();
        $this->assertFalse($company->users()->whereKey($member->id)->exists());
        $this->deleteJson($url.'/'.$owner->id)->assertUnprocessable();
    }

    public function test_unregistered_or_inactive_accounts_cannot_be_added_as_members(): void
    {
        $owner = User::factory()->create()->fresh();
        $inactive = User::factory()->create(['is_active' => false])->fresh();
        $company = app(CompanyService::class)->createOwnedCompany($owner, ['company_name' => 'Membership Validation']);
        $url = '/api/v1/companies/'.$company->id.'/members';

        $this->actingAs($owner)->postJson($url, ['email' => 'missing@example.invalid', 'role' => 'member'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson($url, ['email' => $inactive->email, 'role' => 'member'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
    }
}
