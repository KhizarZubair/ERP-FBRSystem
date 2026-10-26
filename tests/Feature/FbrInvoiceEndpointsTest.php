<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use App\Services\CompanyService;
use App\Services\CustomerService;
use App\Services\FbrInvoiceService;
use App\Services\FbrReferenceService;
use App\Services\InvoiceService;
use App\Services\ItemService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

class FbrInvoiceEndpointsTest extends TestCase
{
    private User $owner;

    private Company $company;

    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'erp-fbr-system']);
        DB::beginTransaction();
        Http::preventStrayRequests();
        $this->mock(FbrReferenceService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('saleTypes')->andReturn(['records' => [18 => 'Goods at standard rate (default)']])->byDefault();
            $mock->shouldReceive('provinces')->andReturn(['records' => [8 => 'Sindh']])->byDefault();
            $mock->shouldReceive('rates')->andReturn(['records' => [734 => '18%']])->byDefault();
            $mock->shouldReceive('validateHsUnit')->andReturnNull()->byDefault();
            $mock->shouldReceive('buyerVerification')->andReturn([
                'active' => true, 'status' => 'Active', 'registration_type' => 'Registered', 'source' => 'fbr',
            ])->byDefault();
        });
        $this->owner = User::factory()->create()->fresh();
        $this->company = app(CompanyService::class)->createOwnedCompany($this->owner, ['company_name' => 'FBR Fixture']);
        $this->company->forceFill(['ntn_cnic' => '0123456', 'province' => 'Sindh', 'address' => 'Seller address',
            'business_activity' => 'Manufacturer', 'sector' => 'All Other Sectors',
            'allowed_sandbox_scenarios' => ['SN001']])->save();
        $buyer = app(CustomerService::class)->save($this->company, ['name' => 'Buyer', 'code' => 'BUY', 'registration_type' => 'Registered', 'ntn_cnic' => '0765432', 'province' => 'Sindh', 'address' => 'Buyer address', 'is_active' => true]);
        $item = app(ItemService::class)->save($this->company, ['sku' => 'FBR', 'name' => 'Item', 'description' => 'Saved description', 'hs_code' => '1006.3000', 'unit_of_measurement' => 'KG', 'sale_type' => 'Goods at standard rate (default)', 'tax_rate_percent' => '18', 'track_stock' => false, 'reorder_level' => '0', 'is_active' => true]);
        $this->invoice = app(InvoiceService::class)->save($this->company, $this->owner, ['customer_id' => $buyer->id,
            'invoice_date' => '2026-10-14', 'scenario_id' => 'SN001',
            'lines' => [['item_id' => $item->id, 'quantity' => '2', 'unit_price' => '100', 'tax_rate_percent' => '18', 'discount' => '0']]]);
        $this->invoice = app(InvoiceService::class)->approve($this->company, $this->owner, $this->invoice->id, app(InvoiceService::class)->version($this->invoice));
        foreach (['sandbox', 'production'] as $env) {
            $this->company->fbrIntegrations()->create(['environment' => $env, 'token' => 'dummy-'.$env, 'status' => 'configured']);
        }
        $this->actingAs($this->owner);
    }

    protected function tearDown(): void
    {
        try {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        } finally {
            parent::tearDown();
        }
    }

    private function url(string $op = ''): string
    {
        return '/api/v1/companies/'.$this->company->id.'/invoices/'.$this->invoice->id.'/fbr'.($op ? '/'.$op : '');
    }

    private function data(string $env = 'sandbox'): array
    {
        return ['environment' => $env, 'version' => app(InvoiceService::class)->version($this->invoice->fresh('items')), 'scenario_id' => 'SN001', 'request_id' => (string) Str::uuid(), 'confirmed' => true];
    }

    private function valid(bool $submit = false): array
    {
        return [...($submit ? ['invoiceNumber' => 'TEST-FBR-123'] : []), 'validationResponse' => ['statusCode' => '00', 'status' => 'Valid', 'error' => '', 'invoiceStatuses' => [['itemSNo' => '1', 'statusCode' => '00', 'status' => 'Valid', 'invoiceNo' => $submit ? 'TEST-FBR-123-1' : null, 'error' => '']]]];
    }

    public function test_seller_readiness_accepts_only_supported_ntn_and_cnic_lengths(): void
    {
        $service = app(FbrInvoiceService::class);

        foreach (['1234567', '12345678', '1234567890123'] as $identifier) {
            $this->company->forceFill(['ntn_cnic' => $identifier])->save();
            $readiness = $service->readiness($this->company->fresh());
            $this->assertNotContains('Seller NTN must contain 7 or 8 digits, or a CNIC must contain 13 digits.', $readiness['blockers'], $identifier);
        }

        foreach (['123456', '123456789', '123456789012', '12345678901234', '12A4567', ''] as $identifier) {
            $this->company->forceFill(['ntn_cnic' => $identifier])->save();
            $readiness = $service->readiness($this->company->fresh());
            $this->assertContains('Seller NTN must contain 7 or 8 digits, or a CNIC must contain 13 digits.', $readiness['blockers'], $identifier);
        }
    }

    public function test_environment_routing_payload_validation_acceptance_and_duplicate_block(): void
    {
        Http::fake(['*' => Http::sequence()->push($this->valid())->push($this->valid(true))]);
        $this->getJson($this->url())->assertOk()->assertJsonCount(1, 'environments')->assertJsonPath('environments.0', 'sandbox');
        $this->postJson($this->url('validate'), $this->data())->assertOk()->assertJsonPath('data.status', 'accepted');
        Http::assertSent(fn ($r) => str_ends_with($r->url(), 'validateinvoicedata_sb')
            && $r->hasHeader('Authorization', 'Bearer dummy-sandbox')
            && $r['invoiceType'] === 'Sale Invoice'
            && $r['invoiceDate'] === '2026-10-14'
            && $r['sellerNTNCNIC'] === '0123456'
            && $r['sellerBusinessName'] === 'FBR Fixture'
            && $r['sellerProvince'] === 'Sindh'
            && $r['sellerAddress'] === 'Seller address'
            && $r['buyerNTNCNIC'] === '0765432'
            && $r['buyerBusinessName'] === 'Buyer'
            && $r['buyerProvince'] === 'Sindh'
            && $r['buyerAddress'] === 'Buyer address'
            && $r['buyerRegistrationType'] === 'Registered'
            && $r['invoiceRefNo'] === ''
            && $r['scenarioId'] === 'SN001'
            && array_keys($r['items'][0]) === [
                'hsCode', 'productDescription', 'rate', 'uoM', 'quantity', 'totalValues',
                'valueSalesExcludingST', 'fixedNotifiedValueOrRetailPrice', 'salesTaxApplicable',
                'salesTaxWithheldAtSource', 'extraTax', 'furtherTax', 'sroScheduleNo', 'fedPayable',
                'discount', 'saleType', 'sroItemSerialNo',
            ]
            && $r['items'][0]['hsCode'] === '1006.3000'
            && $r['items'][0]['productDescription'] === 'Saved description'
            && $r['items'][0]['rate'] === '18%'
            && $r['items'][0]['uoM'] === 'KG'
            && $r['items'][0]['quantity'] === 2
            && $r['items'][0]['valueSalesExcludingST'] === 200
            && $r['items'][0]['salesTaxApplicable'] === 36
            && $r['items'][0]['totalValues'] === 236
            && $r['items'][0]['fixedNotifiedValueOrRetailPrice'] === 0
            && $r['items'][0]['salesTaxWithheldAtSource'] === 0
            && $r['items'][0]['extraTax'] === 0
            && $r['items'][0]['furtherTax'] === 0
            && $r['items'][0]['fedPayable'] === 0
            && $r['items'][0]['discount'] === 0
            && $r['items'][0]['saleType'] === 'Goods at standard rate (default)'
            && $r['items'][0]['sroScheduleNo'] === ''
            && $r['items'][0]['sroItemSerialNo'] === '');
        $this->postJson($this->url('submit'), $this->data())->assertOk()->assertJsonPath('data.status', 'accepted')->assertJsonPath('data.fbr_invoice_number', 'TEST-FBR-123');
        $this->assertSame('TEST-FBR-123', $this->invoice->fresh()->fbr_invoice_number);
        $this->assertSame('approved', $this->invoice->fresh()->status->value);
        $this->postJson($this->url('submit'), $this->data())->assertConflict();
        $this->assertSame(0, $this->company->stockMovements()->count());
    }

    public function test_production_omits_scenario_and_uses_production_token(): void
    {
        $this->company->forceFill(['fbr_environment' => 'production'])->save();
        Http::fake(['*' => Http::sequence()->push($this->valid())->push($this->valid(true))]);
        $this->postJson($this->url('validate'), $this->data('production'))->assertOk()->assertJsonPath('data.status', 'accepted');
        Http::assertSent(fn ($r) => str_ends_with($r->url(), 'validateinvoicedata') && $r->hasHeader('Authorization', 'Bearer dummy-production') && ! array_key_exists('scenarioId', $r->data()));
        $this->postJson($this->url('submit'), $this->data('production'))->assertOk();
        $this->assertSame('issued', $this->invoice->fresh()->status->value);
    }

    public function test_http_200_with_invalid_line_is_rejected_and_cannot_submit(): void
    {
        $response = $this->valid();
        $response['validationResponse']['invoiceStatuses'][0] = ['itemSNo' => '1', 'statusCode' => '01', 'status' => 'Invalid', 'errorCode' => '0046', 'error' => 'Provide rate'];
        Http::fake(['*' => Http::response($response)]);
        $this->postJson($this->url('validate'), $this->data())->assertOk()->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.lines.0.error_code', '0046')->assertJsonPath('data.lines.0.error', 'Provide rate')
            ->assertJsonPath('data.lines.0.guidance', 'Reload and select an FBR-approved rate for the selected sale type.');
        $this->postJson($this->url('submit'), $this->data())->assertUnprocessable();
    }

    public function test_header_only_error_is_exposed_with_guidance(): void
    {
        Http::fake(['*' => Http::response(['validationResponse' => [
            'statusCode' => '01', 'status' => 'Invalid', 'errorCode' => '0083',
            'error' => 'Seller registration number mismatch', 'invoiceStatuses' => null,
        ]])]);

        $this->postJson($this->url('validate'), $this->data())->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.error_code', '0083')
            ->assertJsonPath('data.error', 'Seller registration number mismatch')
            ->assertJsonPath('data.guidance', 'Ensure the seller NTN matches the owner of the configured token.')
            ->assertJsonCount(0, 'data.lines');
    }

    public function test_rejected_validation_can_be_reopened_corrected_and_reapproved_with_history_preserved(): void
    {
        $invalid = $this->valid();
        $invalid['validationResponse']['statusCode'] = '01';
        $invalid['validationResponse']['status'] = 'Invalid';
        $invalid['validationResponse']['errorCode'] = '0046';
        $invalid['validationResponse']['error'] = 'Provide rate';
        Http::fake(['*' => Http::response($invalid)]);

        $staleVersion = $this->data()['version'];
        $this->postJson($this->url('validate'), $this->data())->assertOk()->assertJsonPath('data.status', 'rejected');
        $this->assertSame('validation_failed', $this->invoice->fresh()->fbr_status->value);
        $currentVersion = app(InvoiceService::class)->version($this->invoice->fresh('items'));

        $this->postJson('/api/v1/companies/'.$this->company->id.'/invoices/'.$this->invoice->id.'/reopen', [
            'version' => $staleVersion, 'confirmed' => true,
        ])->assertConflict();
        $reopened = $this->postJson('/api/v1/companies/'.$this->company->id.'/invoices/'.$this->invoice->id.'/reopen', [
            'version' => $currentVersion, 'confirmed' => true,
        ])->assertOk()->assertJsonPath('data.status', 'draft')->assertJsonPath('data.fbr_status', 'validation_failed')->json('data');

        $this->assertNull($this->invoice->fresh()->approved_at);
        $this->assertSame(1, $this->invoice->fbrSubmissions()->count());
        $this->deleteJson('/api/v1/companies/'.$this->company->id.'/invoices/'.$this->invoice->id, [
            'version' => $reopened['version'],
        ])->assertUnprocessable();
        $this->postJson('/api/v1/companies/'.$this->company->id.'/invoices/'.$this->invoice->id.'/approve', [
            'version' => $reopened['version'], 'confirmed' => true,
        ])->assertOk()->assertJsonPath('data.status', 'approved');
    }

    public function test_unauthorized_token_response_is_stored_without_acceptance(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Unauthorized'], 401)]);
        $this->postJson($this->url('validate'), $this->data())->assertOk()->assertJsonPath('data.http_status', 401)->assertJsonPath('data.status', 'rejected');
        $this->assertNull($this->invoice->fresh()->fbr_invoice_number);
    }

    public function test_unknown_submission_blocks_retry(): void
    {
        Http::fake(['*' => Http::sequence()->push($this->valid())->push('Gateway failure', 500)]);
        $this->postJson($this->url('validate'), $this->data())->assertOk();
        $this->postJson($this->url('submit'), $this->data())->assertOk()->assertJsonPath('data.status', 'unknown');
        $this->postJson($this->url('submit'), $this->data())->assertConflict();
    }

    public function test_missing_credentials_scenario_and_other_company_are_blocked(): void
    {
        $this->postJson($this->url('validate'), [...$this->data(), 'scenario_id' => 'SN000'])->assertUnprocessable();
        foreach (['SN005', 'SN026'] as $scenario) {
            $this->postJson($this->url('validate'), [...$this->data(), 'scenario_id' => $scenario])
                ->assertUnprocessable()->assertJsonValidationErrors('scenario_id');
        }
        $this->company->fbrIntegrations()->delete();
        $this->postJson($this->url('validate'), $this->data())->assertUnprocessable();
        $this->actingAs(User::factory()->create()->fresh())->postJson($this->url('validate'), $this->data())->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_accepted_unknown_pending_and_issued_invoices_cannot_be_reopened(): void
    {
        $endpoint = '/api/v1/companies/'.$this->company->id.'/invoices/'.$this->invoice->id.'/reopen';
        foreach ([
            ['status' => 'approved', 'fbr_status' => 'accepted'],
            ['status' => 'approved', 'fbr_status' => 'unknown'],
            ['status' => 'approved', 'fbr_status' => 'pending'],
            ['status' => 'issued', 'fbr_status' => 'accepted'],
        ] as $state) {
            $this->invoice->forceFill($state)->save();
            $this->postJson($endpoint, [
                'version' => app(InvoiceService::class)->version($this->invoice->fresh('items')),
                'confirmed' => true,
            ])->assertUnprocessable();
        }
    }

    public function test_partial_acceptance_is_unknown_and_reconciliation_requires_confirmed_evidence(): void
    {
        $partial = $this->valid(true);
        $partial['validationResponse']['status'] = 'Invalid';
        $partial['validationResponse']['invoiceStatuses'][0]['statusCode'] = '01';
        $partial['validationResponse']['invoiceStatuses'][0]['status'] = 'Invalid';
        Http::fake(['*' => Http::sequence()->push($this->valid())->push($partial)]);
        $this->postJson($this->url('validate'), $this->data())->assertOk();
        $attempt = $this->postJson($this->url('submit'), $this->data())->assertOk()->assertJsonPath('data.status', 'unknown')->json('data.id');
        $this->postJson($this->url('submit'), $this->data())->assertConflict();
        $payload = ['submission_id' => $attempt, 'outcome' => 'accepted', 'notes' => 'Verified by checking the FBR sandbox portal.', 'confirmed' => true];
        $this->postJson($this->url('reconcile'), $payload)->assertUnprocessable()->assertJsonValidationErrors('fbr_invoice_number');
        $this->postJson($this->url('reconcile'), [...$payload, 'confirmed' => false, 'fbr_invoice_number' => 'CONFIRMED-123'])->assertUnprocessable();
        $this->postJson($this->url('reconcile'), [...$payload, 'fbr_invoice_number' => 'CONFIRMED-123'])->assertOk()->assertJsonPath('data.status', 'accepted');
        $this->assertSame('CONFIRMED-123', $this->invoice->fresh()->fbr_invoice_number);
        $this->postJson($this->url('reconcile'), [...$payload, 'fbr_invoice_number' => 'CONFIRMED-123'])->assertConflict();
    }

    public function test_confirmed_non_acceptance_allows_fresh_validation_and_only_owner_can_resolve(): void
    {
        Http::fake(['*' => Http::sequence()->push($this->valid())->push('No reliable result', 500)]);
        $this->postJson($this->url('validate'), $this->data())->assertOk();
        $attempt = $this->postJson($this->url('submit'), $this->data())->assertOk()->json('data.id');
        $payload = ['submission_id' => $attempt, 'outcome' => 'not_accepted', 'notes' => 'PRAL support case confirmed no invoice was accepted.', 'confirmed' => true];
        $member = User::factory()->create()->fresh();
        $this->company->users()->attach($member->id, ['role' => 'member', 'is_active' => true]);
        $this->actingAs($member)->postJson($this->url('reconcile'), $payload)->assertForbidden();
        $this->actingAs($this->owner)->postJson($this->url('reconcile'), $payload)->assertOk()->assertJsonPath('data.status', 'rejected');
        $this->assertSame('rejected', $this->invoice->fresh()->fbr_status->value);
        $this->postJson($this->url('submit'), $this->data())->assertUnprocessable();
        $this->assertNotNull($this->invoice->fbrSubmissions()->findOrFail($attempt)->reconciled_at);
    }

    public function test_stale_validation_and_duplicate_request_id_cannot_send(): void
    {
        Http::fake(['*' => Http::response($this->valid())]);
        $data = $this->data();
        $this->postJson($this->url('validate'), $data)->assertOk();
        $this->postJson($this->url('validate'), $data)->assertConflict();
        $this->invoice->fbrSubmissions()->where('operation', 'validate')->update(['completed_at' => now()->subMinutes(11)]);
        $this->postJson($this->url('submit'), $this->data())->assertUnprocessable();
        Http::assertSentCount(1);
    }

    public function test_company_environment_cannot_be_overridden_and_token_changes_require_fresh_validation(): void
    {
        Http::fake(['*' => Http::response($this->valid())]);
        $this->postJson($this->url('validate'), $this->data('production'))->assertUnprocessable();
        Http::assertNothingSent();
        $this->postJson($this->url('validate'), $this->data())->assertOk();
        $integration = $this->company->fbrIntegrations()->where('environment', 'sandbox')->firstOrFail();
        $integration->token = 'rotated-dummy-token';
        $integration->save();
        $this->postJson($this->url('submit'), $this->data())->assertUnprocessable();
        Http::assertSentCount(1);
    }

    public function test_invalid_official_rate_and_buyer_status_are_blocked_before_invoice_api_call(): void
    {
        $references = $this->mock(FbrReferenceService::class);
        $references->shouldReceive('saleTypes')->andReturn(['records' => [18 => 'Goods at standard rate (default)']]);
        $references->shouldReceive('provinces')->andReturn(['records' => [8 => 'Sindh']]);
        $references->shouldReceive('validateHsUnit')->andReturnNull();
        $references->shouldReceive('rates')->once()->andReturn(['records' => [735 => '5%']]);
        $this->postJson($this->url('validate'), $this->data())
            ->assertUnprocessable()->assertJsonValidationErrors('lines.0.tax_rate_label');
        Http::assertNothingSent();

        $references->shouldReceive('rates')->andReturn(['records' => [734 => '18%']]);
        $references->shouldReceive('buyerVerification')->once()->andReturn([
            'active' => false, 'status' => 'In-Active', 'registration_type' => 'Unregistered', 'source' => 'fbr',
        ]);
        $this->postJson($this->url('validate'), $this->data())
            ->assertUnprocessable()->assertJsonValidationErrors('buyer_ntn_cnic');
        Http::assertNothingSent();
    }
}
