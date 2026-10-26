<?php

namespace Tests\Unit;

use App\Enums\FbrEnvironment;
use App\Enums\FbrSubmissionStatus;
use App\Enums\InvoiceFbrStatus;
use App\Enums\RegistrationType;
use App\Enums\StockMovementType;
use App\Models\Company;
use App\Models\Customer;
use App\Models\FbrIntegration;
use App\Models\FbrSubmission;
use App\Models\FbrSubmissionItem;
use App\Models\Invoice;
use App\Models\StockMovement;
use Illuminate\Database\ConnectionResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\MySqlConnection;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;
use ValueError;

class SchemaHardeningTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $connection = new MySqlConnection(
            fn () => throw new RuntimeException('Database access is prohibited in these tests.'),
            'erp-fbr-system',
            '',
            ['driver' => 'mysql'],
        );
        $resolver = new ConnectionResolver(['offline' => $connection]);
        $resolver->setDefaultConnection('offline');
        Model::setConnectionResolver($resolver);
    }

    public function test_fiscal_environment_and_normalized_statuses_round_trip_as_enums(): void
    {
        $invoice = new Invoice([
            'fbr_environment' => 'sandbox',
            'fbr_status' => 'unknown',
            'buyer_registration_type' => 'Unregistered',
        ]);
        $stock = new StockMovement(['movement_type' => 'issue', 'quantity' => '-2.500000']);

        $this->assertSame(FbrEnvironment::Sandbox, $invoice->fbr_environment);
        $this->assertSame(InvoiceFbrStatus::Unknown, $invoice->fbr_status);
        $this->assertSame(RegistrationType::Unregistered, $invoice->buyer_registration_type);
        $this->assertSame('unknown', $invoice->toArray()['fbr_status']);
        $this->assertSame(StockMovementType::Issue, $stock->movement_type);
        $this->assertSame('-2.500000', $stock->quantity);
    }

    public function test_misspelled_normalized_status_is_rejected(): void
    {
        $this->expectException(ValueError::class);
        new Invoice(['fbr_status' => 'acepted']);
    }

    public function test_tokens_and_audit_evidence_are_encrypted_and_hidden(): void
    {
        $payload = ['buyerBusinessName' => 'Private Buyer', 'items' => [['quantity' => 1]]];
        $integration = new FbrIntegration(['environment' => 'sandbox', 'token' => 'sample-secret']);
        $submission = new FbrSubmission([
            'environment' => 'sandbox',
            'status' => 'unknown',
            'request_payload' => $payload,
            'response_payload' => ['error' => 'Private response'],
            'response_body' => '<gateway>Private response</gateway>',
        ]);
        $line = new FbrSubmissionItem(['response_payload' => ['error' => 'Private line response']]);

        $this->assertSame(FbrSubmissionStatus::Unknown, $submission->status);
        $this->assertSame($payload, $submission->request_payload);
        $this->assertSame(['error' => 'Private response'], $submission->response_payload);
        $this->assertSame('<gateway>Private response</gateway>', $submission->response_body);
        $this->assertSame(['error' => 'Private line response'], $line->response_payload);
        $this->assertSame('sample-secret', $integration->token);
        $this->assertStringNotContainsString('Private Buyer', $submission->getAttributes()['request_payload']);
        $this->assertStringNotContainsString('Private response', $submission->getAttributes()['response_body']);
        $this->assertNotSame('sample-secret', $integration->getAttributes()['token']);
        foreach (['request_payload', 'response_payload', 'response_body'] as $field) {
            $this->assertArrayNotHasKey($field, $submission->toArray());
        }
        $this->assertArrayNotHasKey('response_payload', $line->toArray());
        $this->assertArrayNotHasKey('token', $integration->toArray());
    }

    public function test_company_scope_uses_a_qualified_company_predicate(): void
    {
        $query = Customer::forCompany(42);

        $this->assertStringContainsString('customers.company_id = ?', str_replace(chr(96), '', $query->toSql()));
        $this->assertSame([42], $query->getBindings());
        $this->assertNotContains('company_id', (new Customer)->getFillable());
    }

    public function test_missing_company_id_cannot_silently_use_an_unscoped_query(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Customer::forCompany(0);
    }

    public function test_accessible_company_query_requires_active_company_user_and_membership(): void
    {
        $query = Company::accessibleTo(7);
        $sql = str_replace(chr(96), '', $query->toSql());

        $this->assertStringContainsString('companies.is_active = ?', $sql);
        $this->assertStringContainsString('users.id = ?', $sql);
        $this->assertStringContainsString('users.is_active = ?', $sql);
        $this->assertStringContainsString('company_user.is_active = ?', $sql);
        $this->assertSame([true, 7, true, true], $query->getBindings());
    }

    public function test_missing_user_id_is_rejected_before_company_lookup(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Company::accessibleTo(0);
    }
}
