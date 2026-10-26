<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('sku', 80);
            $table->string('name');
            $table->text('description');
            $table->string('hs_code', 20)->nullable();
            $table->string('unit_of_measurement', 150);
            $table->string('sale_type');
            $table->string('tax_rate_label', 100)->nullable();
            $table->decimal('tax_rate_percent', 9, 6)->nullable();
            $table->decimal('fixed_notified_value_or_retail_price', 20, 6)->default(0);
            $table->decimal('extra_tax', 20, 6)->default(0);
            $table->decimal('fed_payable', 20, 6)->default(0);
            $table->string('sro_schedule_no', 100)->nullable();
            $table->string('sro_item_serial_no', 100)->nullable();
            $table->boolean('track_stock')->default(true);
            $table->decimal('reorder_level', 20, 6)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'sku']);
            $table->unique(['company_id', 'id']);
            $table->index(['company_id', 'is_active', 'name']);
            $table->index(['company_id', 'hs_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
