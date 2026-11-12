<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\User;
use App\Services\CompanyService;
use App\Services\CustomerService;
use App\Services\ItemPriceService;
use App\Services\ItemService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class InvoiceStockOverviewTest extends TestCase
{
    private User $owner;

    private Company $workspace;

    private Customer $buyer;

    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'erp-fbr-system']);
        DB::connection('mysql')->beginTransaction();
        $this->owner = User::factory()->create()->fresh();
        $this->workspace = app(CompanyService::class)->createOwnedCompany($this->owner, ['company_name' => 'Invoice Stock Tests']);
        $this->workspace->forceFill(['ntn_cnic' => '0123456', 'province' => 'Sindh', 'address' => 'Seller address'])->save();
        $this->buyer = app(CustomerService::class)->save($this->workspace, ['code' => 'BUYER', 'name' => 'Original Buyer',
            'registration_type' => 'Registered', 'ntn_cnic' => '0765432', 'province' => 'Sindh', 'address' => 'Buyer address', 'is_active' => true]);
        $this->item = app(ItemService::class)->save($this->workspace, ['sku' => 'ITEM', 'name' => 'Original Item', 'description' => 'Original Description',
            'hs_code' => '1006.3000', 'unit_of_measurement' => 'KG', 'sale_type' => 'Goods at standard rate',
            'tax_rate_percent' => '18', 'track_stock' => true, 'reorder_level' => '5', 'is_active' => true]);
        $this->actingAs($this->owner);
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

    private function base(string $suffix): string
    {
        return '/api/v1/companies/'.$this->workspace->id.'/'.$suffix;
    }

    /** @return array<string, mixed> */
    private function invoicePayload(): array
    {
        // scenario_id is required when company is in sandbox mode and has assigned scenarios.
        // SN001 (Standard Rated Sale) is always in the auto-mapped base set for any company.
        return ['customer_id' => $this->buyer->id, 'invoice_date' => '2026-10-12', 'scenario_id' => 'SN001', 'lines' => [
            ['item_id' => $this->item->id, 'quantity' => '2.500000', 'unit_price' => '10.123456', 'tax_rate_percent' => '18', 'discount' => '0.1'],
        ]];
    }

    /** @return array<string, mixed> */
    private function movement(string $type = 'receipt', string $quantity = '10'): array
    {
        return ['item_id' => $this->item->id, 'movement_date' => '2026-10-10', 'document_number' => 'STOCK-TEST',
            'movement_type' => $type, 'quantity' => $quantity, 'idempotency_key' => (string) Str::uuid(), 'notes' => 'Test movement'];
    }

    public function test_invoice_crud_exact_totals_snapshots_and_no_stock_side_effect(): void
    {
        $payload = $this->invoicePayload();
        $created = $this->postJson($this->base('invoices'), [...$payload, 'company_id' => 999, 'total_values' => '1', 'fbr_status' => 'accepted'])->assertCreated()
            ->assertJsonPath('data.value_sales_excluding_st', '25.208640')->assertJsonPath('data.sales_tax_applicable', '4.537555')
            ->assertJsonPath('data.total_values', '29.746195')->assertJsonPath('data.fbr_status', 'not_submitted')
            ->assertJsonPath('data.status', 'draft')->assertJsonPath('data.buyer_business_name', 'Original Buyer')->assertJsonCount(1, 'data.lines');
        $id = $created->json('data.id');
        $this->assertSame(0, $this->workspace->stockMovements()->count());
        $this->assertSame($this->owner->id, Invoice::findOrFail($id)->created_by);
        $this->getJson($this->base('invoices/'.$id))->assertOk()->assertJsonPath('data.lines.0.product_description', 'Original Description');
        $this->buyer->forceFill(['name' => 'Changed Buyer'])->save();
        $this->item->forceFill(['description' => 'Changed Description', 'hs_code' => '9999.0000'])->save();
        $this->workspace->forceFill(['name' => 'Changed Seller'])->save();
        $updated = $this->putJson($this->base('invoices/'.$id), [...$payload, 'version' => $created->json('data.version'), 'notes' => 'Updated',
            'lines' => [[...$payload['lines'][0], 'id' => $created->json('data.lines.0.id')]]])->assertOk()
            ->assertJsonPath('data.buyer_business_name', 'Original Buyer')->assertJsonPath('data.seller_business_name', 'Invoice Stock Tests')
            ->assertJsonPath('data.lines.0.product_description', 'Original Description')->assertJsonPath('data.lines.0.hs_code', '1006.3000');
        $this->deleteJson($this->base('invoices/'.$id), ['version' => $updated->json('data.version')])->assertOk();
        $this->getJson($this->base('invoices/'.$id))->assertNotFound();
        $this->assertSame(0, $this->workspace->invoices()->count());
    }

    public function test_invoice_number_sequence_and_stale_edit_delete_conflicts(): void
    {
        $a = $this->postJson($this->base('invoices'), $this->invoicePayload())->assertCreated()->json('data');
        $b = $this->postJson($this->base('invoices'), $this->invoicePayload())->assertCreated()->json('data');
        $this->assertNotSame($a['number'], $b['number']);
        $this->putJson($this->base('invoices/'.$a['id']), [...$this->invoicePayload(), 'version' => $a['version'], 'notes' => 'New version'])->assertOk();
        $this->putJson($this->base('invoices/'.$a['id']), [...$this->invoicePayload(), 'version' => $a['version']])->assertConflict();
        $this->deleteJson($this->base('invoices/'.$a['id']), ['version' => $a['version']])->assertConflict();
        $this->assertSame(2, $this->workspace->invoices()->count());
    }

    public function test_invoice_validation_rollback_and_protected_status(): void
    {
        $payload = $this->invoicePayload();
        $this->postJson($this->base('invoices'), [...$payload, 'lines' => []])->assertUnprocessable();
        $this->postJson($this->base('invoices'), [...$payload, 'lines' => [[...$payload['lines'][0], 'quantity' => '0']]])->assertUnprocessable();
        $this->postJson($this->base('invoices'), [...$payload, 'lines' => [[...$payload['lines'][0], 'discount' => '999']]])->assertUnprocessable()->assertJsonValidationErrors('lines.0.discount');
        $this->postJson($this->base('invoices'), [...$payload, 'lines' => [[...$payload['lines'][0], 'sales_tax_withheld_at_source' => '50']]])->assertUnprocessable();
        $this->postJson($this->base('invoices'), [...$payload, 'due_date' => '2026-10-01'])->assertUnprocessable();
        $this->postJson($this->base('invoices'), [...$payload, 'lines' => [[...$payload['lines'][0], 'unit_price' => '0.0000001']]])->assertUnprocessable();
        $this->postJson($this->base('invoices'), [...$payload, 'lines' => [[...$payload['lines'][0], 'quantity' => '999999999', 'unit_price' => '999999999']]])->assertUnprocessable();
        $this->assertSame(0, $this->workspace->invoices()->count());
        $created = $this->postJson($this->base('invoices'), $payload)->assertCreated()->json('data');
        Invoice::findOrFail($created['id'])->forceFill(['fbr_status' => 'accepted'])->save();
        $record = $this->getJson($this->base('invoices/'.$created['id']))->assertOk()->assertJsonPath('data.editable', false)->json('data');
        $this->putJson($this->base('invoices/'.$record['id']), [...$payload, 'version' => $record['version']])->assertUnprocessable();
        $this->deleteJson($this->base('invoices/'.$record['id']), ['version' => $record['version']])->assertUnprocessable();
    }

    public function test_company_isolation_and_member_permissions(): void
    {
        $created = $this->postJson($this->base('invoices'), $this->invoicePayload())->assertCreated()->json('data');
        $other = User::factory()->create()->fresh();
        $otherCompany = app(CompanyService::class)->createOwnedCompany($other, ['company_name' => 'Other Workspace']);
        $otherBase = '/api/v1/companies/'.$otherCompany->id;
        $this->actingAs($other)->getJson($this->base('invoices'))->assertNotFound();
        $this->getJson($this->base('overview'))->assertNotFound();
        $this->getJson($this->base('stock/movements'))->assertNotFound();
        $this->getJson($otherBase.'/invoices/'.$created['id'])->assertNotFound();
        $this->postJson($otherBase.'/invoices', $this->invoicePayload())->assertNotFound();
        $this->postJson($otherBase.'/stock/movements', $this->movement())->assertNotFound();
        $this->workspace->users()->attach($other->id, ['role' => 'member', 'is_active' => true]);
        $this->getJson($this->base('invoices'))->assertOk();
        $this->getJson($this->base('overview'))->assertOk();
        $this->getJson($this->base('stock/balances'))->assertOk();
        $this->postJson($this->base('invoices'), $this->invoicePayload())->assertForbidden();
        $this->deleteJson($this->base('invoices/'.$created['id']), ['version' => $created['version']])->assertForbidden();
        $this->postJson($this->base('stock/movements'), $this->movement())->assertForbidden();
    }

    public function test_inactive_items_buyers_and_foreign_line_ids_rejected(): void
    {
        $this->item->forceFill(['is_active' => false])->save();
        $this->postJson($this->base('invoices'), $this->invoicePayload())->assertUnprocessable();
        $this->item->forceFill(['is_active' => true])->save();
        $this->buyer->forceFill(['is_active' => false])->save();
        $this->postJson($this->base('invoices'), $this->invoicePayload())->assertUnprocessable();
        $this->buyer->forceFill(['is_active' => true])->save();
        $first = $this->postJson($this->base('invoices'), $this->invoicePayload())->assertCreated()->json('data');
        $second = $this->postJson($this->base('invoices'), $this->invoicePayload())->assertCreated()->json('data');
        $payload = $this->invoicePayload();
        $this->putJson($this->base('invoices/'.$first['id']), [...$payload, 'version' => $first['version'],
            'lines' => [[...$payload['lines'][0], 'id' => $second['lines'][0]['id']]]])->assertUnprocessable();
    }

    public function test_stock_signed_quantities_idempotent_retry_and_reversal(): void
    {
        $opening = $this->movement('opening', '10.123456');
        $id = $this->postJson($this->base('stock/movements'), $opening)->assertCreated()->assertJsonPath('data.quantity', '10.123456')->json('data.id');
        $this->postJson($this->base('stock/movements'), $opening)->assertCreated()->assertJsonPath('data.id', $id);
        $this->postJson($this->base('stock/movements'), [...$opening, 'quantity' => '20'])->assertConflict();
        $this->postJson($this->base('stock/movements'), $this->movement('opening'))->assertUnprocessable();
        $this->postJson($this->base('stock/movements'), $this->movement('issue', '3'))->assertCreated()->assertJsonPath('data.quantity', '-3.000000');
        $this->postJson($this->base('stock/movements'), $this->movement('adjustment', '-2'))->assertCreated()->assertJsonPath('data.quantity', '-2.000000');
        $this->getJson($this->base('stock/balances'))->assertOk()->assertJsonPath('data.0.available', '5.123456');
        $rev = $this->postJson($this->base('stock/movements/'.$id.'/reverse'), ['notes' => 'Correct opening'])->assertOk()->assertJsonPath('data.quantity', '-10.123456')->json('data.id');
        $this->postJson($this->base('stock/movements/'.$id.'/reverse'), ['notes' => 'Retry'])->assertOk()->assertJsonPath('data.id', $rev);
        $this->postJson($this->base('stock/movements/'.$rev.'/reverse'), ['notes' => 'Not allowed'])->assertUnprocessable();
        $this->assertSame(4, $this->workspace->stockMovements()->count());
        $this->getJson($this->base('stock/balances'))->assertOk()->assertJsonPath('data.0.available', '-5.000000');
    }

    public function test_stock_history_running_balance_ignores_date_and_type_filter_boundaries(): void
    {
        $this->postJson($this->base('stock/movements'), [...$this->movement('opening'), 'movement_date' => '2026-10-01'])->assertCreated();
        $this->postJson($this->base('stock/movements'), [...$this->movement('receipt', '5'), 'movement_date' => '2026-10-02'])->assertCreated();
        $this->postJson($this->base('stock/movements'), [...$this->movement('issue', '2'), 'movement_date' => '2026-10-03'])->assertCreated();
        $this->getJson($this->base('stock/movements').'?from=2026-10-03&movement_type=issue')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.running_balance', '13.000000');
        $this->getJson($this->base('stock/movements').'?per_page=1&page=2')->assertOk()->assertJsonPath('total', 3)->assertJsonPath('data.0.running_balance', '15.000000');
        $this->postJson($this->base('stock/movements'), [...$this->movement('adjustment', '2'), 'movement_date' => '2026-10-02'])->assertCreated();
        $this->getJson($this->base('stock/movements').'?from=2026-10-03')->assertOk()->assertJsonPath('data.0.running_balance', '15.000000');
    }

    public function test_stock_validation_nontracked_and_future_entries(): void
    {
        $this->postJson($this->base('stock/movements'), $this->movement('receipt', '0'))->assertUnprocessable();
        $this->postJson($this->base('stock/movements'), $this->movement('receipt', '-1'))->assertUnprocessable();
        $this->postJson($this->base('stock/movements'), $this->movement('issue', '-1'))->assertUnprocessable();
        $this->postJson($this->base('stock/movements'), [...$this->movement(), 'movement_date' => '2099-01-01'])->assertUnprocessable();
        $this->postJson($this->base('stock/movements'), [...$this->movement(), 'movement_type' => 'reversal'])->assertUnprocessable();
        $this->item->forceFill(['track_stock' => false])->save();
        $this->postJson($this->base('stock/movements'), $this->movement())->assertUnprocessable();
        $this->assertSame(0, $this->workspace->stockMovements()->count());
    }

    public function test_dynamic_overview_updates_from_invoices_and_stock(): void
    {
        $this->getJson($this->base('overview'))->assertOk()->assertJsonPath('data.invoice_count', 0)->assertJsonPath('data.low_stock_count', 1);
        $created = $this->postJson($this->base('invoices'), $this->invoicePayload())->assertCreated()->json('data');
        $this->postJson($this->base('stock/movements'), $this->movement())->assertCreated();
        $this->getJson($this->base('overview'))->assertOk()->assertJsonPath('data.invoice_count', 1)->assertJsonPath('data.draft_count', 1)
            ->assertJsonPath('data.accepted_count', 0)->assertJsonPath('data.saved_total', '29.746195')
            ->assertJsonPath('data.saved_tax', '4.537555')->assertJsonPath('data.low_stock_count', 0)
            ->assertJsonPath('data.recent_invoices.0.number', $created['number']);
        Invoice::findOrFail($created['id'])->forceFill(['status' => 'cancelled'])->save();
        $this->getJson($this->base('overview'))->assertOk()->assertJsonPath('data.saved_total', '0.000000')->assertJsonPath('data.draft_count', 0);
    }

    public function test_bounded_queries_paginated_filters_and_effective_price_lookup(): void
    {
        $price = ['price_list' => 'Standard', 'unit_price' => '25.5', 'currency' => 'PKR', 'effective_from' => '2026-01-01', 'effective_to' => '2026-06-30', 'is_active' => true];
        app(ItemPriceService::class)->save($this->item, $price);
        app(ItemPriceService::class)->save($this->item, [...$price, 'unit_price' => '30', 'effective_from' => '2026-07-01', 'effective_to' => null]);
        $this->getJson($this->base('invoices/lookups/items').'?price_date=2026-03-01')->assertOk()->assertJsonPath('data.0.current_unit_price', '25.500000');
        for ($i = 0; $i < 6; $i++) {
            $this->postJson($this->base('invoices'), $this->invoicePayload())->assertCreated();
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->getJson($this->base('invoices').'?per_page=2&page=2&status=draft&sort=number&direction=asc')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 6);
        $this->assertLessThanOrEqual(6, count(DB::getQueryLog()));
        DB::flushQueryLog();
        $this->getJson($this->base('overview'))->assertOk();
        $this->assertLessThanOrEqual(10, count(DB::getQueryLog()));
        DB::disableQueryLog();
        $this->getJson($this->base('invoices').'?search=%25')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson($this->base('invoices').'?sort=unsafe&per_page=500&page=0')->assertUnprocessable();
    }

    public function test_unauthenticated_endpoints_and_one_sided_date_filters(): void
    {
        $this->getJson($this->base('invoices').'?to=2026-10-12')->assertOk();
        $this->getJson($this->base('stock/movements').'?to=2026-10-12')->assertOk();
        auth()->forgetGuards();
        foreach (['invoices', 'overview', 'stock/balances', 'stock/movements', 'invoices/lookups/items'] as $suffix) {
            $this->getJson($this->base($suffix))->assertUnauthorized();
        }
        $this->postJson($this->base('invoices'), $this->invoicePayload())->assertUnauthorized();
        $this->postJson($this->base('stock/movements'), $this->movement())->assertUnauthorized();
    }

    public function test_posted_stock_history_protects_invoice_and_linked_movement(): void
    {
        $created = $this->postJson($this->base('invoices'), $this->invoicePayload())->assertCreated()->json('data');
        $movement = $this->workspace->stockMovements()->make([
            ...$this->movement('issue', '-2.5'), 'invoice_item_id' => $created['lines'][0]['id'],
        ]);
        $movement->forceFill(['created_by' => $this->owner->id])->save();
        $record = $this->getJson($this->base('invoices/'.$created['id']))->assertOk()->assertJsonPath('data.editable', false)->json('data');
        $this->getJson($this->base('invoices'))->assertOk()->assertJsonPath('data.0.editable', false);
        $this->putJson($this->base('invoices/'.$created['id']), [...$this->invoicePayload(), 'version' => $record['version']])->assertUnprocessable();
        $this->deleteJson($this->base('invoices/'.$created['id']), ['version' => $record['version']])->assertUnprocessable();
        $this->postJson($this->base('stock/movements/'.$movement->id.'/reverse'), ['notes' => 'Not permitted'])->assertUnprocessable();
        $this->assertNotNull(Invoice::find($created['id']));
    }

    public function test_preview_calculates_without_database_writes_or_external_requests(): void
    {
        Http::fake();
        $sequence = $this->workspace->fresh()->getAttribute('next_invoice_number');
        $preview = $this->postJson($this->base('invoices/preview'), $this->invoicePayload())->assertOk()
            ->assertJsonPath('data.id', null)->assertJsonPath('data.number', 'Assigned on save')
            ->assertJsonPath('data.total_values', '29.746195')->assertJsonPath('data.lines.0.hs_code', '1006.3000');
        $this->assertSame(0, $this->workspace->invoices()->count());
        $this->assertSame(0, $this->workspace->stockMovements()->count());
        $this->assertSame($sequence, $this->workspace->fresh()->getAttribute('next_invoice_number'));
        Http::assertNothingSent();
        $created = $this->postJson($this->base('invoices'), $this->invoicePayload())->assertCreated();
        $this->assertSame($preview->json('data.total_values'), $created->json('data.total_values'));
    }

    public function test_local_approval_requires_confirmation_and_locks_without_fbr_or_stock_side_effects(): void
    {
        Http::fake();
        $created = $this->postJson($this->base('invoices'), $this->invoicePayload())->assertCreated()->json('data');
        $url = $this->base('invoices/'.$created['id'].'/approve');
        $this->postJson($url, ['version' => $created['version']])->assertUnprocessable()->assertJsonValidationErrors('confirmed');
        $this->postJson($url, ['version' => $created['version'], 'confirmed' => false])->assertUnprocessable();
        $approved = $this->postJson($url, ['version' => $created['version'], 'confirmed' => true])->assertOk()
            ->assertJsonPath('data.status', 'approved')->assertJsonPath('data.fbr_status', 'not_submitted')
            ->assertJsonPath('data.editable', false)->json('data');
        $invoice = Invoice::findOrFail($created['id']);
        $this->assertSame($this->owner->id, $invoice->approved_by);
        $this->assertNotNull($invoice->approved_at);
        $this->assertNull($invoice->issued_at);
        $this->assertNull($invoice->fbr_invoice_number);
        $this->assertSame(0, $this->workspace->stockMovements()->count());
        $this->assertSame(0, $invoice->fbrSubmissions()->count());
        $this->postJson($url, ['version' => $created['version'], 'confirmed' => true])->assertConflict();
        $this->postJson($url, ['version' => $approved['version'], 'confirmed' => true])->assertUnprocessable();
        $this->putJson($this->base('invoices/'.$created['id']), [...$this->invoicePayload(), 'version' => $approved['version']])->assertUnprocessable();
        $this->deleteJson($this->base('invoices/'.$created['id']), ['version' => $approved['version']])->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_preview_edit_preserves_snapshots_and_rejects_stale_approval(): void
    {
        $created = $this->postJson($this->base('invoices'), $this->invoicePayload())->assertCreated()->json('data');
        $this->buyer->forceFill(['name' => 'Changed buyer'])->save();
        $this->item->forceFill(['description' => 'Changed item'])->save();
        $data = [...$this->invoicePayload(), 'invoice_id' => $created['id'], 'version' => $created['version'],
            'lines' => [[...$this->invoicePayload()['lines'][0], 'id' => $created['lines'][0]['id']]]];
        $this->postJson($this->base('invoices/preview'), $data)->assertOk()
            ->assertJsonPath('data.buyer_business_name', 'Original Buyer')->assertJsonPath('data.lines.0.product_description', 'Original Description');
        $this->putJson($this->base('invoices/'.$created['id']), [...$this->invoicePayload(), 'version' => $created['version'], 'notes' => 'Changed'])->assertOk();
        $this->postJson($this->base('invoices/preview'), $data)->assertConflict();
        $this->postJson($this->base('invoices/'.$created['id'].'/approve'), ['version' => $created['version'], 'confirmed' => true])->assertConflict();
        $this->assertSame('draft', Invoice::findOrFail($created['id'])->status->value);
    }

    public function test_preview_and_approval_enforce_tenant_role_and_authentication(): void
    {
        $created = $this->postJson($this->base('invoices'), $this->invoicePayload())->assertCreated()->json('data');
        $member = User::factory()->create()->fresh();
        $this->workspace->users()->attach($member->id, ['role' => 'member', 'is_active' => true]);
        $this->actingAs($member)->postJson($this->base('invoices/preview'), $this->invoicePayload())->assertForbidden();
        $this->postJson($this->base('invoices/'.$created['id'].'/approve'), ['version' => $created['version'], 'confirmed' => true])->assertForbidden();
        $other = User::factory()->create()->fresh();
        $otherCompany = app(CompanyService::class)->createOwnedCompany($other, ['company_name' => 'Other verification company']);
        $otherBase = '/api/v1/companies/'.$otherCompany->id.'/invoices';
        $this->actingAs($other)->postJson($this->base('invoices/preview'), $this->invoicePayload())->assertNotFound();
        $this->postJson($otherBase.'/preview', [...$this->invoicePayload(), 'invoice_id' => $created['id'], 'version' => $created['version']])->assertNotFound();
        $this->postJson($otherBase.'/'.$created['id'].'/approve', ['version' => $created['version'], 'confirmed' => true])->assertNotFound();
        auth()->forgetGuards();
        $this->postJson($this->base('invoices/preview'), $this->invoicePayload())->assertUnauthorized();
        $this->postJson($this->base('invoices/'.$created['id'].'/approve'), ['version' => $created['version'], 'confirmed' => true])->assertUnauthorized();
    }
}
