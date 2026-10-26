<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('item_id');
            $table->string('price_list', 100)->default('Standard');
            $table->decimal('unit_price', 20, 6);
            $table->char('currency', 3)->default('PKR');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->foreign(['company_id', 'item_id'])->references(['company_id', 'id'])->on('items')->restrictOnDelete();
            $table->unique(['company_id', 'item_id', 'price_list', 'currency', 'effective_from'], 'item_prices_effective_unique');
            $table->index(['company_id', 'item_id', 'is_active', 'effective_from'], 'item_prices_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_prices');
    }
};
