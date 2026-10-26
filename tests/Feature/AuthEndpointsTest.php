<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Services\AuthService;
use App\Services\CompanyService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class AuthEndpointsTest extends TestCase
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

    private function registration(): array
    {
        return [
            'company_name' => 'Auth Test Company',
            'name' => 'Auth Test Owner',
            'email' => 'test-'.bin2hex(random_bytes(8)).'@example.invalid',
            'phone' => '03000000000',
            'password' => 'Auth-test-password-123',
            'password_confirmation' => 'Auth-test-password-123',
            'province' => 'Sindh',
            'ntn_cnic' => '0123456',
            'details_confirmed' => true,
        ];
    }

    public function test_registration_creates_user_company_owner_and_authenticated_response(): void
    {
        $data = $this->registration();
        $data['email'] = strtoupper($data['email']);
        $response = $this->postJson('/api/v1/auth/register', [...$data, 'role' => 'admin', 'is_active' => false]);
        $response->assertCreated()->assertJsonPath('data.email', strtolower($data['email']))
            ->assertJsonPath('data.companies.0.role', 'owner')
            ->assertJsonMissingPath('data.password')->assertJsonMissingPath('data.remember_token');

        $user = User::where('email', strtolower($data['email']))->firstOrFail();
        $this->assertTrue(Hash::check($data['password'], $user->password));
        $this->assertTrue($user->is_active);
        $this->assertDatabaseHas('company_user', ['user_id' => $user->id, 'role' => 'owner', 'is_active' => 1]);
        $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.id', $user->id);
    }

    public function test_invalid_registration_does_not_create_records(): void
    {
        $data = $this->registration();
        $this->postJson('/api/v1/auth/register', [...$data, 'password_confirmation' => 'different', 'province' => 'Invalid province'])
            ->assertUnprocessable()->assertJsonValidationErrors(['password', 'province']);
        $this->assertDatabaseMissing('users', ['email' => $data['email']]);
    }

    public function test_duplicate_email_is_rejected(): void
    {
        $data = $this->registration();
        User::factory()->create(['email' => $data['email']]);
        $this->postJson('/api/v1/auth/register', $data)->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_login_and_logout_protect_current_user_endpoint(): void
    {
        $data = $this->registration();
        $user = app(AuthService::class)->register($data);
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->postJson('/api/v1/auth/login', ['email' => strtoupper($data['email']), 'password' => 'incorrect'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson('/api/v1/auth/login', ['email' => strtoupper($data['email']), 'password' => $data['password']])
            ->assertOk()->assertJsonPath('data.id', $user->id);
        $this->postJson('/api/v1/auth/logout')->assertOk();
        Auth::forgetGuards();
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_inactive_user_and_membership_cannot_login(): void
    {
        $data = $this->registration();
        $user = app(AuthService::class)->register($data);
        $user->forceFill(['is_active' => false])->save();
        $this->postJson('/api/v1/auth/login', ['email' => $data['email'], 'password' => $data['password']])->assertUnprocessable();
        $user->forceFill(['is_active' => true])->save();
        $user->companies()->updateExistingPivot($user->companies()->first()->id, ['is_active' => false]);
        $this->postJson('/api/v1/auth/login', ['email' => $data['email'], 'password' => $data['password']])->assertUnprocessable();
        Auth::forgetGuards();
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_login_rate_limit_blocks_repeated_attempts(): void
    {
        $email = $this->registration()['email'];
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', ['email' => $email, 'password' => 'wrong'])->assertUnprocessable();
        }
        $this->postJson('/api/v1/auth/login', ['email' => $email, 'password' => 'wrong'])->assertTooManyRequests();
    }

    public function test_registration_rolls_back_user_and_company_on_membership_failure(): void
    {
        $data = $this->registration();
        $before = Company::count();
        $mock = $this->mock(CompanyService::class);
        $mock->shouldReceive('createOwnedCompany')->once()->andReturnUsing(function () use ($data): void {
            Company::create(['name' => $data['company_name']]);
            throw new RuntimeException('Simulated membership failure');
        });

        try {
            app(AuthService::class)->register($data);
            $this->fail('Expected registration failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated membership failure', $exception->getMessage());
        }
        $this->assertDatabaseMissing('users', ['email' => $data['email']]);
        $this->assertSame($before, Company::count());
    }

    public function test_password_change_requires_authentication_and_valid_current_password(): void
    {
        $payload = ['current_password' => 'Old-password-123', 'password' => 'New-password-456', 'password_confirmation' => 'New-password-456'];
        $this->putJson('/api/v1/auth/password', $payload)->assertUnauthorized();
        $user = User::factory()->create(['password' => 'Old-password-123', 'is_active' => true]);
        $this->actingAs($user, 'web');
        $this->putJson('/api/v1/auth/password', [...$payload, 'current_password' => 'incorrect'])->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->putJson('/api/v1/auth/password', [...$payload, 'password_confirmation' => 'different'])->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->assertTrue(Hash::check('Old-password-123', $user->fresh()->password));
    }

    public function test_single_user_mode_blocks_second_user_registration_and_company_creation(): void
    {
        config(['erp.single_user_mode' => true]);
        $user = User::query()->first();
        if ($user === null) {
            $user = User::factory()->create();
        }

        $userCount = User::query()->count();
        $companyCount = Company::query()->count();

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Second Master',
            'email' => 'second-master-'.bin2hex(random_bytes(6)).'@example.invalid',
            'phone' => '03001234567',
            'password' => 'Master-password-123',
            'password_confirmation' => 'Master-password-123',
        ])->assertForbidden();

        try {
            User::query()->create([
                'name' => 'Direct Second User',
                'email' => 'direct-second-'.bin2hex(random_bytes(6)).'@example.invalid',
                'password' => 'Another-password-123',
            ]);
            $this->fail('Expected a second user to be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertSame('System restricted: only one master user is allowed.', $exception->getMessage());
        }

        $payload = [
            'name' => 'Single Company',
            'seller_ntn' => '1234567',
            'seller_cnic' => '3520112345671',
            'province' => 'Sindh',
            'address' => 'Test address',
            'email' => 'seller-'.bin2hex(random_bytes(5)).'@example.invalid',
            'phone' => '03001234567',
        ];
        $response = $this->actingAs($user)->postJson('/api/v1/companies', $payload);
        if ($companyCount === 0) {
            $response->assertCreated();
            $companyCount++;
        } else {
            $response->assertStatus(409);
        }

        $this->actingAs($user)->postJson('/api/v1/companies', [
            ...$payload,
            'name' => 'Second Company',
            'email' => 'second-seller-'.bin2hex(random_bytes(5)).'@example.invalid',
        ])->assertStatus(409);

        $this->assertSame($userCount, User::query()->count());
        $this->assertSame($companyCount, Company::query()->count());
    }

    public function test_password_change_updates_only_signed_in_user_and_rejects_old_login_password(): void
    {
        $data = $this->registration();
        $user = app(AuthService::class)->register($data);
        $other = User::factory()->create(['password' => 'Other-password-123']);
        $this->postJson('/api/v1/auth/login', ['email' => $data['email'], 'password' => $data['password']])->assertOk();
        $this->putJson('/api/v1/auth/password', ['current_password' => $data['password'], 'password' => 'New-password-456', 'password_confirmation' => 'New-password-456', 'user_id' => $other->id])
            ->assertOk()->assertJsonMissingPath('password');
        $this->assertTrue(Hash::check('New-password-456', $user->fresh()->password));
        $this->assertTrue(Hash::check('Other-password-123', $other->fresh()->password));
        $this->getJson('/api/v1/auth/me')->assertOk();
        $this->postJson('/api/v1/auth/logout')->assertOk();
        Auth::forgetGuards();
        $this->postJson('/api/v1/auth/login', ['email' => $data['email'], 'password' => $data['password']])->assertUnprocessable();
        $this->postJson('/api/v1/auth/login', ['email' => $data['email'], 'password' => 'New-password-456'])->assertOk();
    }
}
