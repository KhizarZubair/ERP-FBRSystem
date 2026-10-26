<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('item_id');
            $table->date('movement_date');
            $table->string('document_number', 100);
            $table->string('movement_type', 30);
            $table->decimal('quantity', 20, 6);
            $table->unsignedBigInteger('invoice_item_id')->nullable();
            $table->unsignedBigInteger('reversal_of_id')->nullable();
            $table->uuid('idempotency_key');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'id']);
            $table->unique(['company_id', 'item_id', 'id'], 'stock_movements_reversal_reference_unique');
            $table->unique(['company_id', 'idempotency_key']);
            $table->unique('reversal_of_id');
            $table->foreign(['company_id', 'item_id'])->references(['company_id', 'id'])->on('items')->restrictOnDelete();
            $table->foreign(['company_id', 'item_id', 'invoice_item_id'], 'stock_movements_invoice_item_foreign')->references(['company_id', 'item_id', 'id'])->on('invoice_items')->restrictOnDelete();
            $table->foreign(['company_id', 'item_id', 'reversal_of_id'], 'stock_movements_reversal_foreign')->references(['company_id', 'item_id', 'id'])->on('stock_movements')->restrictOnDelete();
            $table->index(['company_id', 'item_id', 'movement_date', 'id'], 'stock_movements_ledger_index');
            $table->index(['company_id', 'movement_type', 'movement_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
