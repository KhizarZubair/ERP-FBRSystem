<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use App\Services\CompanyService;
use App\Services\CustomerService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CustomerEndpointsTest extends TestCase
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

    private function company(User $user): Company
    {
        return app(CompanyService::class)->createOwnedCompany($user, ['company_name' => 'Customer CRUD Test']);
    }

    /** @return array<string, mixed> */
    private function payload(string $code = 'TEST-001'): array
    {
        return ['code' => $code, 'name' => 'Test Buyer', 'registration_type' => 'Registered',
            'ntn_cnic' => '0123456', 'province' => 'Sindh', 'city' => 'Karachi', 'address' => 'Test billing address',
            'email' => 'buyer@example.com', 'phone' => '+92 300 0000000', 'is_active' => true];
    }

    private function url(Company $company, ?int $customer = null): string
    {
        return '/api/v1/companies/'.$company->id.'/customers'.($customer === null ? '' : '/'.$customer);
    }

    public function test_owner_can_create_read_update_change_status_and_delete(): void
    {
        $user = User::factory()->create()->fresh();
        $company = $this->company($user);
        $this->actingAs($user);
        $created = $this->postJson($this->url($company), [...$this->payload(), 'company_id' => 999999])->assertCreated()
            ->assertJsonPath('data.ntn_cnic', '0123456')->assertJsonMissingPath('data.company_id');
        $id = $created->json('data.id');
        $this->assertSame($company->id, Customer::findOrFail($id)->company_id);
        $this->getJson($this->url($company, $id))->assertOk()->assertJsonPath('data.name', 'Test Buyer');
        $this->putJson($this->url($company, $id), [...$this->payload(), 'name' => 'Updated Buyer'])->assertOk()->assertJsonPath('data.name', 'Updated Buyer');
        $this->patchJson($this->url($company, $id).'/status', ['is_active' => false, 'name' => 'Spoof'])->assertOk()
            ->assertJsonPath('data.is_active', false)->assertJsonPath('data.name', 'Updated Buyer');
        $this->getJson($this->url($company))->assertOk()->assertJsonPath('meta.total', 1);
        $this->deleteJson($this->url($company, $id))->assertOk();
        $this->getJson($this->url($company, $id))->assertNotFound();
        $this->assertNull(Customer::find($id));
    }

    public function test_automatic_codes_unique_per_company_and_duplicate_validation(): void
    {
        $user = User::factory()->create()->fresh();
        $company = $this->company($user);
        $other = $this->company($user);
        $this->actingAs($user);
        $first = $this->postJson($this->url($company), [...$this->payload(), 'code' => ''])->assertCreated()->json('data.code');
        $second = $this->postJson($this->url($company), [...$this->payload(), 'code' => null])->assertCreated()->json('data.code');
        $this->assertNotSame($first, $second);
        $this->postJson($this->url($company), $this->payload($first))->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->postJson($this->url($other), $this->payload($first))->assertCreated();
    }

    public function test_cross_company_customer_access_and_mutations_are_denied(): void
    {
        $owner = User::factory()->create()->fresh();
        $otherUser = User::factory()->create()->fresh();
        $company = $this->company($owner);
        $other = $this->company($otherUser);
        $customer = app(CustomerService::class)->save($company, $this->payload());
        $this->actingAs($otherUser);
        $this->getJson($this->url($company))->assertNotFound();
        $this->postJson($this->url($company), $this->payload())->assertNotFound();
        $this->getJson($this->url($other, $customer->id))->assertNotFound();
        $this->putJson($this->url($other, $customer->id), $this->payload())->assertNotFound();
        $this->patchJson($this->url($other, $customer->id).'/status', ['is_active' => false])->assertNotFound();
        $this->deleteJson($this->url($other, $customer->id))->assertNotFound();
        $this->assertSame('Test Buyer', $customer->fresh()->name);
    }

    public function test_member_read_access_and_inactive_access_boundaries(): void
    {
        $owner = User::factory()->create()->fresh();
        $member = User::factory()->create()->fresh();
        $company = $this->company($owner);
        $customer = app(CustomerService::class)->save($company, $this->payload());
        $company->users()->attach($member->id, ['role' => 'member', 'is_active' => true]);
        $this->actingAs($member)->getJson($this->url($company))->assertOk();
        $this->getJson($this->url($company, $customer->id))->assertOk();
        $this->postJson($this->url($company), $this->payload())->assertForbidden();
        $this->putJson($this->url($company, $customer->id), $this->payload())->assertForbidden();
        $this->patchJson($this->url($company, $customer->id).'/status', ['is_active' => false])->assertForbidden();
        $this->deleteJson($this->url($company, $customer->id))->assertForbidden();
        $company->users()->updateExistingPivot($member->id, ['is_active' => false]);
        $this->getJson($this->url($company))->assertNotFound();
        $this->actingAs($owner);
        $company->forceFill(['is_active' => false])->save();
        $this->getJson($this->url($company))->assertNotFound();
        $company->forceFill(['is_active' => true])->save();
        $owner->forceFill(['is_active' => false])->save();
        $this->getJson($this->url($company))->assertForbidden();
    }

    public function test_validation_bounds_and_unauthenticated_access(): void
    {
        $user = User::factory()->create()->fresh();
        $company = $this->company($user);
        $this->getJson($this->url($company))->assertUnauthorized();
        $this->postJson($this->url($company), $this->payload())->assertUnauthorized();
        $this->actingAs($user)->postJson($this->url($company), [...$this->payload(), 'name' => '', 'ntn_cnic' => '123',
            'registration_type' => 'Wrong', 'province' => 'Wrong', 'email' => 'Wrong', 'address' => '', 'is_active' => 'Wrong'])
            ->assertUnprocessable()->assertJsonValidationErrors(['name', 'ntn_cnic', 'registration_type', 'province', 'email', 'address', 'is_active']);
        $this->getJson($this->url($company).'?per_page=1000&sort=unexpected&page=0')->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page', 'sort', 'page']);
        $this->assertSame(0, $company->customers()->count());
    }

    public function test_paginated_search_filters_and_bounded_query_count(): void
    {
        $user = User::factory()->create()->fresh();
        $company = $this->company($user);
        $service = app(CustomerService::class);
        for ($i = 1; $i <= 20; $i++) {
            $service->save($company, [...$this->payload('ROW-'.$i), 'name' => 'Buyer '.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'registration_type' => $i % 2 === 0 ? 'Registered' : 'Unregistered']);
        }
        $this->actingAs($user);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->getJson($this->url($company).'?per_page=5&page=2')->assertOk()->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.total', 20)->assertJsonPath('meta.last_page', 4)->assertJsonPath('data.0.name', 'Buyer 06');
        $this->assertLessThanOrEqual(5, count(DB::getQueryLog()));
        DB::disableQueryLog();
        $this->getJson($this->url($company).'?search=Buyer%2002&registration_type=Registered')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.code', 'ROW-2');
        $this->getJson($this->url($company).'?search=%25')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson($this->url($company).'?registration_type=Unregistered')->assertOk()->assertJsonPath('meta.total', 10);
    }

    public function test_registered_customer_requires_valid_ntn_or_cnic_and_accepts_eight_digit_ntn(): void
    {
        $user = User::factory()->create()->fresh();
        $company = $this->company($user);
        $this->actingAs($user);

        $this->postJson($this->url($company), [...$this->payload('REGISTERED-NULL'), 'ntn_cnic' => null])
            ->assertUnprocessable()->assertJsonValidationErrors('ntn_cnic');
        $this->postJson($this->url($company), [...$this->payload('REGISTERED-8'), 'ntn_cnic' => '12345678'])
            ->assertCreated()->assertJsonPath('data.ntn_cnic', '12345678');
        $this->postJson($this->url($company), [...$this->payload('UNREGISTERED-NTN'), 'ntn_cnic' => '1234567', 'registration_type' => 'Unregistered'])
            ->assertCreated();
        $this->postJson($this->url($company), [...$this->payload('UNREGISTERED-NULL'), 'ntn_cnic' => null, 'registration_type' => 'Unregistered'])
            ->assertCreated()->assertJsonPath('data.ntn_cnic', null);
    }

    public function test_customer_with_invoice_cannot_be_deleted_but_can_be_deactivated(): void
    {
        $user = User::factory()->create()->fresh();
        $company = $this->company($user);
        $customer = app(CustomerService::class)->save($company, $this->payload());
        $company->invoices()->create(['customer_id' => $customer->id, 'number' => 'CRUD-TEST', 'invoice_date' => '2026-10-12']);
        $this->actingAs($user)->deleteJson($this->url($company, $customer->id))->assertUnprocessable()->assertJsonValidationErrors('customer');
        $this->assertNotNull($customer->fresh());
        $this->patchJson($this->url($company, $customer->id).'/status', ['is_active' => false])->assertOk()->assertJsonPath('data.is_active', false);
    }
}
