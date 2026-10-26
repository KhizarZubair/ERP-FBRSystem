<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Item;
use App\Models\User;
use App\Services\CompanyService;
use App\Services\CustomerService;
use App\Services\ItemPriceService;
use App\Services\ItemService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ItemEndpointsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'erp-fbr-system']);
        DB::connection('mysql')->beginTransaction();
        config(['fbr.reference_cache_store' => 'array']);
        Cache::store('array')->flush();
        Http::preventStrayRequests();
        Http::fake([
            config('fbr.hs_codes_url') => Http::response([['hS_CODE' => '0101.2100', 'description' => 'Test reference classification']]),
            config('fbr.hs_units_url').'*' => Http::response([['uoM_ID' => 13, 'description' => 'Numbers, pieces, units']]),
        ]);
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
        $company = app(CompanyService::class)->createOwnedCompany($user, ['company_name' => 'Item CRUD Test']);
        $company->fbrIntegrations()->create(['environment' => 'sandbox', 'token' => 'test-reference-token', 'status' => 'configured']);

        return $company;
    }

    /** @return array<string, mixed> */
    private function price(string $list = 'Standard', ?string $from = null, ?string $to = null): array
    {
        return ['price_list' => $list, 'unit_price' => '1234.123456', 'currency' => 'PKR',
            'effective_from' => $from ?? today()->toDateString(), 'effective_to' => $to, 'is_active' => true];
    }

    /** @return array<string, mixed> */
    private function payload(string $sku = 'TEST-SKU'): array
    {
        return ['sku' => $sku, 'name' => 'Test Item', 'description' => 'Test description', 'hs_code' => '0101.2100',
            'unit_of_measurement' => 'Numbers, pieces, units', 'sale_type' => 'Goods at standard rate (default)',
            'tax_rate_percent' => '18', 'fixed_notified_value_or_retail_price' => '0',
            'sro_schedule_no' => null, 'sro_item_serial_no' => null, 'track_stock' => true,
            'reorder_level' => '10', 'is_active' => true, 'price' => $this->price()];
    }

    private function url(Company $company, ?int $item = null): string
    {
        return '/api/v1/companies/'.$company->id.'/items'.($item === null ? '' : '/'.$item);
    }

    public function test_uom_and_sale_type_references_are_scoped_cached_and_hs_filtered(): void
    {
        $user = User::factory()->create()->fresh();
        $company = $this->company($user);
        Http::fake([
            config('fbr.units_url') => Http::response([['uoM_ID' => 13, 'description' => 'KG'], ['uoM_ID' => 77, 'description' => 'Square Metre']]),
            config('fbr.hs_units_url').'*' => Http::response([['uoM_ID' => 13, 'description' => 'KG']]),
            config('fbr.sale_types_url') => Http::response([['transactioN_TYPE_ID' => 18, 'transactioN_DESC' => 'Goods at standard rate (default)']]),
        ]);
        $base = '/api/v1/companies/'.$company->id.'/fbr/';
        $this->getJson($base.'units')->assertUnauthorized();
        $this->actingAs($user);
        $this->getJson($base.'units')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.code', '13')->assertJsonPath('data.0.description', 'KG');
        $this->getJson($base.'units?hs_code=0101.2100')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson($base.'sale-types')->assertOk()->assertJsonPath('data.0.description', 'Goods at standard rate (default)');
        $this->getJson($base.'units')->assertOk()->assertJsonPath('meta.source', 'cache');
        Http::assertSentCount(3);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'HS_UOM') && $request['hs_code'] === '0101.2100' && (int) $request['annexure_id'] === 3);
        $this->getJson($base.'units?hs_code=wrong')->assertUnprocessable();
        $withoutToken = app(CompanyService::class)->createOwnedCompany($user, ['company_name' => 'No reference token']);
        $this->getJson('/api/v1/companies/'.$withoutToken->id.'/fbr/units')->assertUnprocessable();
        $this->actingAs(User::factory()->create()->fresh())->getJson($base.'sale-types')->assertNotFound();
        Http::assertSentCount(3);
    }

    public function test_product_tax_defaults_and_exempt_label_are_saved_with_required_initial_price(): void
    {
        $user = User::factory()->create()->fresh();
        $company = $this->company($user);
        $data = [...$this->payload('PRODUCT-MASTER'), 'tax_rate_percent' => '0', 'tax_rate_label' => 'Exempt',
            'extra_tax' => '12.500001', 'fed_payable' => '7.250001'];
        $this->actingAs($user);
        $id = $this->postJson($this->url($company), $data)->assertCreated()->assertJsonPath('data.tax_rate_label', 'Exempt')
            ->assertJsonPath('data.extra_tax', '12.500001')->assertJsonPath('data.fed_payable', '7.250001')->json('data.id');
        $this->getJson($this->url($company, $id))->assertOk()->assertJsonPath('data.extra_tax', '12.500001');
        $this->putJson($this->url($company, $id), [...$data, 'extra_tax' => '-1'])->assertUnprocessable()->assertJsonValidationErrors('extra_tax');
        $this->assertSame(1, Item::findOrFail($id)->prices()->count());
        Http::assertSentCount(2);
    }

    public function test_item_creation_requires_a_positive_initial_price(): void
    {
        $user = User::factory()->create()->fresh();
        $company = $this->company($user);
        $data = $this->payload('NO-PRICE');
        unset($data['price']);
        $this->actingAs($user)->postJson($this->url($company), $data)->assertUnprocessable()->assertJsonValidationErrors('price');
        $data['price'] = [...$this->price(), 'unit_price' => '0'];
        $this->postJson($this->url($company), $data)->assertUnprocessable()->assertJsonValidationErrors('price.unit_price');
        $this->assertSame(0, $company->items()->count());
        Http::assertNothingSent();
    }

    public function test_item_requires_a_selected_valid_hs_code_and_existing_code_cannot_be_cleared(): void
    {
        $user = User::factory()->create()->fresh();
        $company = $this->company($user);
        $this->actingAs($user);
        foreach ([null, '', 'omitted', '9999.9999'] as $index => $code) {
            $data = $this->payload('NO-HS-'.$index);
            if ($code === 'omitted') {
                unset($data['hs_code']);
            } else {
                $data['hs_code'] = $code;
            }
            $this->postJson($this->url($company), $data)->assertUnprocessable()->assertJsonValidationErrors('hs_code');
        }
        $item = app(ItemService::class)->save($company, $this->payload('CLEAR-HS'));
        $data = $this->payload('CLEAR-HS');
        $data['hs_code'] = '';
        $this->putJson($this->url($company, $item->id), $data)->assertUnprocessable()->assertJsonValidationErrors('hs_code');
        $this->assertSame('0101.2100', $item->fresh()->hs_code);
        Http::assertSentCount(1);
    }

    public function test_item_and_initial_price_crud_and_status(): void
    {
        $user = User::factory()->create()->fresh();
        $company = $this->company($user);
        $this->actingAs($user);
        $response = $this->postJson($this->url($company), [...$this->payload(), 'company_id' => 999999])->assertCreated()
            ->assertJsonPath('data.hs_code', '0101.2100')->assertJsonPath('data.tax_rate_label', '18%')->assertJsonMissingPath('data.company_id');
        $id = $response->json('data.id');
        $item = Item::findOrFail($id);
        $this->assertSame($company->id, $item->company_id);
        $this->assertSame(1, $item->prices()->count());
        $this->getJson($this->url($company, $id))->assertOk()->assertJsonPath('data.name', 'Test Item');
        $data = $this->payload();
        unset($data['price']);
        $this->putJson($this->url($company, $id), [...$data, 'name' => 'Updated Item', 'tax_rate_percent' => '5.5'])
            ->assertOk()->assertJsonPath('data.name', 'Updated Item')->assertJsonPath('data.tax_rate_label', '5.5%');
        $this->patchJson($this->url($company, $id).'/status', ['is_active' => false, 'sku' => 'Spoof'])->assertOk()
            ->assertJsonPath('data.is_active', false)->assertJsonPath('data.sku', 'TEST-SKU');
        $this->deleteJson($this->url($company, $id))->assertOk();
        $this->assertSame(0, DB::table('item_prices')->where('item_id', $id)->count());
        $this->getJson($this->url($company, $id))->assertNotFound();
    }

    public function test_price_create_read_edit_and_delete_with_exact_decimal_storage(): void
    {
        $user = User::factory()->create()->fresh();
        $company = $this->company($user);
        $item = app(ItemService::class)->save($company, $this->payload());
        $base = $this->url($company, $item->id).'/prices';
        $this->actingAs($user);
        $response = $this->postJson($base, [...$this->price('Wholesale'), 'company_id' => 999999, 'item_id' => 999999])
            ->assertCreated()->assertJsonPath('data.unit_price', '1234.123456');
        $id = $response->json('data.id');
        $this->getJson($base.'/'.$id)->assertOk()->assertJsonPath('data.price_list', 'Wholesale');
        $this->putJson($base.'/'.$id, [...$this->price('Wholesale'), 'unit_price' => '0.000001'])->assertOk()->assertJsonPath('data.unit_price', '0.000001');
        $this->getJson($base)->assertOk()->assertJsonCount(2, 'data');
        $this->deleteJson($base.'/'.$id)->assertOk();
        $this->getJson($base.'/'.$id)->assertNotFound();
    }

    public function test_active_price_overlap_and_date_bounds_are_rejected(): void
    {
        $user = User::factory()->create()->fresh();
        $company = $this->company($user);
        $item = app(ItemService::class)->save($company, $this->payload());
        $base = $this->url($company, $item->id).'/prices';
        $this->actingAs($user);
        $this->postJson($base, $this->price('Standard', today()->addDay()->toDateString()))->assertUnprocessable()->assertJsonValidationErrors('effective_from');
        $this->postJson($base, $this->price('Wholesale', '2026-10-20', '2026-10-19'))->assertUnprocessable()->assertJsonValidationErrors('effective_to');
        $first = $item->prices()->firstOrFail();
        $this->putJson($base.'/'.$first->id, $this->price('Standard', today()->toDateString(), today()->toDateString()))->assertOk();
        $this->postJson($base, $this->price('Standard', today()->toDateString(), today()->toDateString()))->assertUnprocessable();
        $this->postJson($base, $this->price('Standard', today()->addDay()->toDateString()))->assertCreated();
        $this->assertSame(2, $item->prices()->count());
    }

    public function test_reactivating_overlapping_price_and_duplicate_inactive_dates_are_rejected(): void
    {
        $user = User::factory()->create()->fresh();
        $company = $this->company($user);
        $item = app(ItemService::class)->save($company, $this->payload());
        $base = $this->url($company, $item->id).'/prices';
        $this->actingAs($user);
        $draft = [...$this->price('Standard', today()->addDay()->toDateString()), 'is_active' => false];
        $id = $this->postJson($base, $draft)->assertCreated()->json('data.id');
        $this->postJson($base, $draft)->assertUnprocessable()->assertJsonValidationErrors('effective_from');
        $this->putJson($base.'/'.$id, [...$draft, 'is_active' => true])->assertUnprocessable()->assertJsonValidationErrors('effective_from');
        $this->assertFalse($item->prices()->findOrFail($id)->is_active);
    }

    public function test_tenant_and_item_price_isolation_and_owner_permissions(): void
    {
        $owner = User::factory()->create()->fresh();
        $outsider = User::factory()->create()->fresh();
        $company = $this->company($owner);
        $other = $this->company($outsider);
        $item = app(ItemService::class)->save($company, $this->payload());
        $otherItem = app(ItemService::class)->save($other, $this->payload());
        $price = $item->prices()->firstOrFail();
        $this->actingAs($outsider);
        $this->getJson($this->url($company))->assertNotFound();
        $this->postJson($this->url($company), $this->payload())->assertNotFound();
        $this->getJson($this->url($other, $item->id))->assertNotFound();
        $this->deleteJson($this->url($other, $item->id))->assertNotFound();
        $this->getJson($this->url($other, $otherItem->id).'/prices/'.$price->id)->assertNotFound();
        $this->putJson($this->url($other, $otherItem->id).'/prices/'.$price->id, $this->price())->assertNotFound();
        $this->deleteJson($this->url($other, $otherItem->id).'/prices/'.$price->id)->assertNotFound();
        $company->users()->attach($outsider->id, ['role' => 'member', 'is_active' => true]);
        $this->getJson($this->url($company))->assertOk();
        $this->getJson($this->url($company, $item->id).'/prices')->assertOk();
        $this->postJson($this->url($company), $this->payload())->assertForbidden();
        $this->deleteJson($this->url($company, $item->id))->assertForbidden();
        $this->postJson($this->url($company, $item->id).'/prices', $this->price('Wholesale'))->assertForbidden();
        $this->putJson($this->url($company, $item->id).'/prices/'.$price->id, $this->price())->assertForbidden();
        $this->deleteJson($this->url($company, $item->id).'/prices/'.$price->id)->assertForbidden();
        $company->users()->updateExistingPivot($outsider->id, ['is_active' => false]);
        $this->getJson($this->url($company))->assertNotFound();
        $this->actingAs($owner);
        $owner->forceFill(['is_active' => false])->save();
        $this->getJson($this->url($company))->assertForbidden();
    }

    public function test_invalid_item_price_precision_and_pagination_are_rejected_without_partial_create(): void
    {
        $user = User::factory()->create()->fresh();
        $company = $this->company($user);
        $this->getJson($this->url($company))->assertUnauthorized();
        $this->actingAs($user);
        $data = [...$this->payload(), 'hs_code' => 'wrong', 'tax_rate_percent' => '101', 'reorder_level' => '-1',
            'price' => [...$this->price(), 'unit_price' => '1.1234567']];
        $this->postJson($this->url($company), $data)->assertUnprocessable()->assertJsonValidationErrors(['hs_code', 'tax_rate_percent', 'reorder_level', 'price.unit_price']);
        $this->assertSame(0, $company->items()->count());
        $this->postJson($this->url($company), [...$this->payload(), 'price' => [...$this->price(), 'unit_price' => '100000000000000']])
            ->assertUnprocessable()->assertJsonValidationErrors('price.unit_price');
        $this->getJson($this->url($company).'?per_page=101&sort=wrong&page=0')->assertUnprocessable()->assertJsonValidationErrors(['per_page', 'sort', 'page']);
    }

    public function test_duplicate_sku_is_scoped_to_company(): void
    {
        $user = User::factory()->create()->fresh();
        $company = $this->company($user);
        $other = $this->company($user);
        $this->actingAs($user);
        $this->postJson($this->url($company), $this->payload())->assertCreated();
        $this->postJson($this->url($company), $this->payload())->assertUnprocessable()->assertJsonValidationErrors('sku');
        $this->postJson($this->url($other), $this->payload())->assertCreated();
    }

    public function test_current_prices_ignore_future_expired_and_inactive_records_and_queries_are_bounded(): void
    {
        $user = User::factory()->create()->fresh();
        $company = $this->company($user);
        $service = app(ItemService::class);
        for ($i = 1; $i <= 18; $i++) {
            $service->save($company, [...$this->payload('ROW-'.$i), 'name' => 'Item '.str_pad((string) $i, 2, '0', STR_PAD_LEFT)]);
        }
        $this->actingAs($user);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->getJson($this->url($company).'?per_page=5&page=2')->assertOk()->assertJsonCount(5, 'data')->assertJsonPath('meta.total', 18)
            ->assertJsonPath('data.0.name', 'Item 06')->assertJsonPath('data.0.current_unit_price', '1234.123456')->assertJsonPath('data.0.on_hand', '0');
        $this->assertLessThanOrEqual(5, count(DB::getQueryLog()));
        DB::disableQueryLog();
        $item = $company->items()->firstOrFail();
        $prices = app(ItemPriceService::class);
        $prices->save($item, $this->price('Wholesale', today()->addDay()->toDateString()));
        $prices->save($item, $this->price('Wholesale', today()->subDays(3)->toDateString(), today()->subDay()->toDateString()));
        $prices->save($item, [...$this->price('Wholesale'), 'is_active' => false]);
        $this->getJson($this->url($company).'?search=ROW-1&price_list=Wholesale')->assertOk()->assertJsonPath('data.0.current_unit_price', null);
        $this->getJson($this->url($company).'?search=%25')->assertOk()->assertJsonCount(0, 'data');
        $this->patchJson($this->url($company, $item->id).'/status', ['is_active' => false])->assertOk();
        $this->getJson($this->url($company).'?is_active=0')->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_invoice_and_stock_history_block_item_deletion(): void
    {
        $user = User::factory()->create()->fresh();
        $company = $this->company($user);
        $item = app(ItemService::class)->save($company, $this->payload());
        $company->stockMovements()->create(['item_id' => $item->id, 'movement_date' => today()->toDateString(), 'document_number' => 'CRUD-OPEN',
            'movement_type' => 'opening', 'quantity' => '1', 'idempotency_key' => Str::uuid()->toString()]);
        $this->actingAs($user)->deleteJson($this->url($company, $item->id))->assertUnprocessable()->assertJsonValidationErrors('item');
        $this->assertSame(1, $item->prices()->count());
        $this->patchJson($this->url($company, $item->id).'/status', ['is_active' => false])->assertOk();
    }

    public function test_invoice_history_is_preserved_when_item_and_prices_change(): void
    {
        $user = User::factory()->create()->fresh();
        $company = $this->company($user);
        $item = app(ItemService::class)->save($company, $this->payload());
        $customer = app(CustomerService::class)->save($company, ['code' => 'INVOICE-BUYER', 'name' => 'Invoice Buyer', 'registration_type' => 'Registered', 'province' => 'Sindh', 'address' => 'Address', 'is_active' => true]);
        $invoice = $company->invoices()->create(['customer_id' => $customer->id, 'number' => 'ITEM-CRUD-INVOICE', 'invoice_date' => today()->toDateString()]);
        $line = $invoice->items()->make(['item_id' => $item->id, 'line_number' => 1, 'sku' => $item->sku, 'product_description' => $item->description, 'hs_code' => $item->hs_code, 'unit_of_measurement' => $item->unit_of_measurement, 'sale_type' => $item->sale_type, 'quantity' => '1', 'unit_price' => '1234.123456']);
        $line->company_id = $company->id;
        $line->save();
        $this->actingAs($user)->deleteJson($this->url($company, $item->id))->assertUnprocessable()->assertJsonValidationErrors('item');
        $data = $this->payload();
        unset($data['price']);
        $this->putJson($this->url($company, $item->id), [...$data, 'description' => 'New description'])->assertOk();
        $price = $item->prices()->firstOrFail();
        $this->putJson($this->url($company, $item->id).'/prices/'.$price->id, [...$this->price(), 'unit_price' => '2000'])->assertOk();
        $this->assertSame('Test description', $line->fresh()->product_description);
        $this->assertSame('1234.123456', $line->fresh()->unit_price);
    }

    public function test_item_creation_rolls_back_if_price_persistence_fails(): void
    {
        $user = User::factory()->create()->fresh();
        $company = $this->company($user);
        $before = $company->items()->count();
        try {
            app(ItemService::class)->save($company, [...$this->payload(), 'price' => [...$this->price(), 'unit_price' => '-1']]);
            $this->fail('The database should reject a negative price.');
        } catch (QueryException $exception) {
            $this->assertSame($before, $company->items()->count());
        }
    }
}
