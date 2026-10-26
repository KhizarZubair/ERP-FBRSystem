<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Services\CompanyService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FbrReferenceEndpointsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'erp-fbr-system', 'fbr.reference_cache_store' => 'array']);
        DB::connection('mysql')->beginTransaction();
        Cache::store('array')->flush();
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        try {
            $this->travelBack();
            $connection = DB::connection('mysql');
            while ($connection->transactionLevel() > 0) {
                $connection->rollBack();
            }
        } finally {
            parent::tearDown();
        }
    }

    private function company(User $user, ?string $token = 'test-sandbox-only'): Company
    {
        $company = app(CompanyService::class)->createOwnedCompany($user, ['company_name' => 'HS Reference Test']);
        if ($token !== null) {
            $company->fbrIntegrations()->create(['environment' => 'sandbox', 'token' => $token, 'status' => 'configured']);
        }

        return $company;
    }

    private function url(Company $company): string
    {
        return '/api/v1/companies/'.$company->id.'/fbr/hs-codes';
    }

    /** @return array<int, array<string, string>> */
    private function list(): array
    {
        return [['hS_CODE' => '0101.2100', 'description' => 'Pure-bred horses'],
            ['hS_CODE' => '8432.1010', 'description' => 'Agricultural machinery']];
    }

    /** @return array<string, mixed> */
    private function item(): array
    {
        return ['sku' => 'HS-TEST', 'name' => 'Test Item', 'description' => 'Item', 'hs_code' => '0101.2100',
            'unit_of_measurement' => 'Numbers, pieces, units', 'sale_type' => 'Goods at standard rate (default)',
            'tax_rate_percent' => '18', 'fixed_notified_value_or_retail_price' => '0', 'track_stock' => true,
            'reorder_level' => '0', 'is_active' => true,
            'price' => ['price_list' => 'Standard', 'unit_price' => '100', 'currency' => 'PKR',
                'effective_from' => today()->toDateString(), 'effective_to' => null, 'is_active' => true]];
    }

    public function test_search_by_code_and_description_caches_one_authorized_call(): void
    {
        $user = User::factory()->create()->fresh();
        $company = $this->company($user);
        Http::fake([config('fbr.hs_codes_url') => Http::response($this->list())]);
        $this->actingAs($user)->getJson($this->url($company).'?search=horses')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', '0101.2100')->assertJsonPath('meta.source', 'fbr');
        $this->getJson($this->url($company).'?search=8432')->assertOk()->assertJsonPath('data.0.description', 'Agricultural machinery')
            ->assertJsonPath('meta.source', 'cache');
        $this->getJson($this->url($company).'?limit=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.has_more', true);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->method() === 'GET' && $request->hasHeader('Authorization', 'Bearer test-sandbox-only'));
        $this->getJson($this->url($company).'?limit=1000&search='.str_repeat('a', 101))->assertUnprocessable()->assertJsonValidationErrors(['limit', 'search']);
    }

    public function test_missing_token_and_company_access_do_not_call_fbr(): void
    {
        $user = User::factory()->create()->fresh();
        $company = $this->company($user, null);
        $this->getJson($this->url($company))->assertUnauthorized();
        $this->actingAs($user)->getJson($this->url($company))->assertUnprocessable()->assertJsonValidationErrors('hs_code');
        $other = User::factory()->create()->fresh();
        $this->actingAs($other)->getJson($this->url($company))->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_stale_cache_is_used_on_server_failure_with_cooldown(): void
    {
        $user = User::factory()->create()->fresh();
        $company = $this->company($user);
        Http::fake([config('fbr.hs_codes_url') => Http::sequence()->push($this->list())->push(['error' => 'upstream'], 500)]);
        $this->actingAs($user)->getJson($this->url($company))->assertOk();
        $this->travel(2)->days();
        $this->getJson($this->url($company))->assertOk()->assertJsonPath('meta.stale', true)->assertJsonPath('meta.source', 'stale-cache');
        $this->getJson($this->url($company))->assertOk()->assertJsonPath('meta.stale', true);
        Http::assertSentCount(2);
    }

    public function test_token_rotation_and_environment_do_not_reuse_old_cache(): void
    {
        $user = User::factory()->create()->fresh();
        $company = $this->company($user);
        Http::fake([config('fbr.hs_codes_url') => Http::response($this->list())]);
        $this->actingAs($user)->getJson($this->url($company))->assertOk();
        $company->fbrIntegrations()->firstOrFail()->update(['token' => 'rotated-test-token']);
        $this->getJson($this->url($company))->assertOk()->assertJsonPath('meta.source', 'fbr');
        $company->fbrIntegrations()->create(['environment' => 'production', 'token' => 'test-production-only', 'status' => 'configured']);
        $company->update(['fbr_environment' => 'production']);
        $this->getJson($this->url($company))->assertOk()->assertJsonPath('meta.source', 'fbr');
        Http::assertSentCount(3);
    }

    public function test_invalid_token_is_sanitized_and_does_not_use_stale_data(): void
    {
        $user = User::factory()->create()->fresh();
        $company = $this->company($user);
        Http::fake([config('fbr.hs_codes_url') => Http::sequence()->push($this->list())->push(['message' => 'SECRET-UPSTREAM'], 401)]);
        $this->actingAs($user)->getJson($this->url($company))->assertOk();
        $this->travel(2)->days();
        $response = $this->getJson($this->url($company))->assertUnprocessable()->assertJsonValidationErrors('hs_code');
        $this->assertStringNotContainsString('SECRET-UPSTREAM', $response->getContent());
        $this->assertStringNotContainsString('test-sandbox-only', $response->getContent());
        $this->getJson($this->url($company))->assertUnprocessable();
        Http::assertSentCount(2);
    }

    public function test_malformed_response_and_network_failure_return_safe_errors(): void
    {
        $user = User::factory()->create()->fresh();
        $company = $this->company($user);
        Http::fake([config('fbr.hs_codes_url') => Http::response(['error' => 'not a list'])]);
        $this->actingAs($user)->getJson($this->url($company))->assertServiceUnavailable();
        $this->getJson($this->url($company))->assertServiceUnavailable();
        Http::assertSentCount(1);
        Cache::store('array')->flush();
        Http::fake([config('fbr.hs_codes_url') => Http::failedConnection()]);
        $this->getJson($this->url($company))->assertServiceUnavailable();
    }

    public function test_item_requires_valid_reference_code_and_preserves_unchanged_saved_code(): void
    {
        $user = User::factory()->create()->fresh();
        $company = $this->company($user);
        $url = '/api/v1/companies/'.$company->id.'/items';
        Http::fake([
            config('fbr.hs_codes_url') => Http::response($this->list()),
            config('fbr.hs_units_url').'*' => Http::response([
                ['uoM_ID' => 13, 'description' => 'Numbers, pieces, units'],
            ]),
        ]);
        $this->actingAs($user)->postJson($url, [...$this->item(), 'hs_code' => '9999.9999'])->assertUnprocessable()->assertJsonValidationErrors('hs_code');
        $this->postJson($url, [...$this->item(), 'unit_of_measurement' => 'KG'])
            ->assertUnprocessable()->assertJsonValidationErrors('unit_of_measurement');
        $id = $this->postJson($url, $this->item())->assertCreated()->json('data.id');
        $company->fbrIntegrations()->delete();
        $data = $this->item();
        unset($data['price']);
        $this->putJson($url.'/'.$id, [...$data, 'name' => 'Changed name'])->assertOk();
        $this->putJson($url.'/'.$id, [...$data, 'hs_code' => '8432.1010'])->assertUnprocessable()->assertJsonValidationErrors('hs_code');
    }

    public function test_rate_sro_and_buyer_reference_apis_are_scoped_normalized_and_cached(): void
    {
        $user = User::factory()->create()->fresh();
        $company = $this->company($user);
        Http::fake([
            config('fbr.rates_url').'*' => Http::response([
                ['ratE_ID' => 734, 'ratE_DESC' => '18%', 'ratE_VALUE' => 18],
            ]),
            config('fbr.sro_schedules_url').'*' => Http::response([
                ['srO_ID' => 8, 'srO_DESC' => '5th Schedule'],
            ]),
            config('fbr.sro_items_url').'*' => Http::response([
                ['srO_ITEM_ID' => 17853, 'srO_ITEM_DESC' => '50'],
            ]),
            config('fbr.statl_url') => Http::response(['status code' => '00', 'status' => 'Active']),
            config('fbr.registration_type_url') => Http::response([
                'statuscode' => '00', 'REGISTRATION_NO' => '0788762', 'REGISTRATION_TYPE' => 'Registered',
            ]),
        ]);
        $base = '/api/v1/companies/'.$company->id.'/fbr';
        $this->actingAs($user)->getJson($base.'/rates?date=24-Feb-2026&transaction_type_id=18&province_id=8')
            ->assertOk()->assertJsonPath('data.0.code', '734')->assertJsonPath('data.0.description', '18%');
        $this->getJson($base.'/sro-schedules?date=24-Feb-2026&rate_id=734&province_id=8')
            ->assertOk()->assertJsonPath('data.0.code', '8');
        $this->getJson($base.'/sro-items?date=2026-02-24&sro_id=8')
            ->assertOk()->assertJsonPath('data.0.code', '17853');
        $buyerUrl = $base.'/buyer-verification?registration_number=07887621&date=2026-02-24';
        $this->getJson($buyerUrl)->assertOk()->assertJsonPath('data.active', true)
            ->assertJsonPath('data.registration_type', 'Registered')->assertJsonPath('data.source', 'fbr');
        $this->getJson($buyerUrl)->assertOk()->assertJsonPath('data.source', 'cache');
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/statl')
            && $request->method() === 'GET'
            && $request['regno'] === '07887621'
            && $request['date'] === '2026-02-24');
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/Get_Reg_Type')
            && $request->method() === 'GET'
            && $request['Registration_No'] === '07887621');

        $other = User::factory()->create()->fresh();
        $this->actingAs($other)->getJson($buyerUrl)->assertNotFound();
        Http::assertSentCount(5);
    }
}
