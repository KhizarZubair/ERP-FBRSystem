<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('invoice_id');
            $table->unsignedBigInteger('item_id');
            $table->unsignedInteger('line_number');
            $table->string('sku', 80);
            $table->text('product_description');
            $table->string('hs_code', 20)->nullable();
            $table->string('unit_of_measurement', 150);
            $table->string('sale_type');
            $table->string('tax_rate_label', 100)->nullable();
            $table->decimal('tax_rate_percent', 9, 6)->nullable();
            $table->decimal('quantity', 20, 6);
            $table->decimal('unit_price', 20, 6);
            $table->decimal('value_sales_excluding_st', 20, 6)->default(0);
            $table->decimal('fixed_notified_value_or_retail_price', 20, 6)->default(0);
            $table->decimal('sales_tax_applicable', 20, 6)->default(0);
            $table->decimal('sales_tax_withheld_at_source', 20, 6)->default(0);
            $table->decimal('extra_tax', 20, 6)->default(0);
            $table->decimal('further_tax', 20, 6)->default(0);
            $table->decimal('fed_payable', 20, 6)->default(0);
            $table->decimal('discount', 20, 6)->default(0);
            $table->decimal('total_values', 20, 6)->default(0);
            $table->string('sro_schedule_no', 100)->nullable();
            $table->string('sro_item_serial_no', 100)->nullable();
            $table->timestamps();
            $table->foreign(['company_id', 'invoice_id'])->references(['company_id', 'id'])->on('invoices')->restrictOnDelete();
            $table->foreign(['company_id', 'item_id'])->references(['company_id', 'id'])->on('items')->restrictOnDelete();
            $table->unique(['invoice_id', 'line_number']);
            $table->unique(['company_id', 'id']);
            $table->unique(['company_id', 'item_id', 'id'], 'invoice_items_stock_reference_unique');
            $table->unique(['company_id', 'invoice_id', 'id'], 'invoice_items_fbr_reference_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
