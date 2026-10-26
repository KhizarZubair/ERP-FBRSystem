<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fbr_submission_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('invoice_id');
            $table->unsignedBigInteger('fbr_submission_id');
            $table->unsignedBigInteger('invoice_item_id')->nullable();
            $table->unsignedInteger('line_number');
            $table->string('status', 30)->nullable();
            $table->string('status_code', 100)->nullable();
            $table->string('error_code', 100)->nullable();
            $table->text('error_message')->nullable();
            $table->string('fbr_invoice_number', 100)->nullable();
            $table->longText('response_payload')->nullable();
            $table->timestamps();
            $table->foreign(['company_id', 'invoice_id', 'fbr_submission_id'], 'fbr_submission_items_submission_foreign')->references(['company_id', 'invoice_id', 'id'])->on('fbr_submissions')->restrictOnDelete();
            $table->foreign(['company_id', 'invoice_id', 'invoice_item_id'], 'fbr_submission_items_invoice_item_foreign')->references(['company_id', 'invoice_id', 'id'])->on('invoice_items')->restrictOnDelete();
            $table->unique(['fbr_submission_id', 'line_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fbr_submission_items');
    }
};
