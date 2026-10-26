<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fbr_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('invoice_id');
            $table->unsignedInteger('attempt_number');
            $table->uuid('idempotency_key')->unique();
            $table->string('environment', 20);
            $table->string('operation', 30)->default('submit');
            $table->string('status', 30)->default('pending');
            $table->string('endpoint', 500)->nullable();
            $table->longText('request_payload')->nullable();
            $table->char('payload_hash', 64)->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->longText('response_payload')->nullable();
            $table->longText('response_body')->nullable();
            $table->string('business_status_code', 100)->nullable();
            $table->string('business_error_code', 100)->nullable();
            $table->string('fbr_invoice_number', 100)->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('reconciled_at')->nullable();
            $table->text('reconciliation_notes')->nullable();
            $table->timestamps();
            $table->foreign(['company_id', 'invoice_id'])->references(['company_id', 'id'])->on('invoices')->restrictOnDelete();
            $table->unique(['invoice_id', 'environment', 'operation', 'attempt_number'], 'fbr_submissions_attempt_unique');
            $table->unique(['company_id', 'invoice_id', 'id'], 'fbr_submissions_line_reference_unique');
            $table->index(['company_id', 'status', 'created_at']);
            $table->index(['company_id', 'environment', 'fbr_invoice_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fbr_submissions');
    }
};
