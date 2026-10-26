<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('customer_id');
            $table->string('number', 80);
            $table->string('invoice_type', 40)->default('Sale Invoice');
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->string('scenario_id', 30)->nullable();
            $table->string('reference_invoice_no', 100)->nullable();
            $table->text('notes')->nullable();
            $table->char('currency', 3)->default('PKR');
            $table->string('status', 30)->default('draft');
            $table->string('fbr_status', 30)->default('not_submitted');
            $table->string('fbr_environment', 20)->default('sandbox');
            $table->string('fbr_invoice_number', 100)->nullable();
            $table->string('seller_business_name')->nullable();
            $table->string('seller_ntn_cnic', 20)->nullable();
            $table->string('seller_province', 100)->nullable();
            $table->text('seller_address')->nullable();
            $table->string('buyer_business_name')->nullable();
            $table->string('buyer_ntn_cnic', 20)->nullable();
            $table->string('buyer_registration_type', 30)->nullable();
            $table->string('buyer_province', 100)->nullable();
            $table->text('buyer_address')->nullable();
            $table->decimal('value_sales_excluding_st', 20, 6)->default(0);
            $table->decimal('sales_tax_applicable', 20, 6)->default(0);
            $table->decimal('sales_tax_withheld_at_source', 20, 6)->default(0);
            $table->decimal('extra_tax', 20, 6)->default(0);
            $table->decimal('further_tax', 20, 6)->default(0);
            $table->decimal('fed_payable', 20, 6)->default(0);
            $table->decimal('discount', 20, 6)->default(0);
            $table->decimal('total_values', 20, 6)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('fbr_accepted_at')->nullable();
            $table->timestamps();
            $table->foreign(['company_id', 'customer_id'])->references(['company_id', 'id'])->on('customers')->restrictOnDelete();
            $table->unique(['company_id', 'number']);
            $table->unique(['company_id', 'id']);
            $table->unique(['company_id', 'fbr_environment', 'fbr_invoice_number'], 'invoices_fbr_number_unique');
            $table->index(['company_id', 'invoice_date']);
            $table->index(['company_id', 'status', 'invoice_date']);
            $table->index(['company_id', 'fbr_status', 'invoice_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
