<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->string('registration_type', 30);
            $table->string('ntn_cnic', 20)->nullable();
            $table->string('province', 100);
            $table->string('city', 100)->nullable();
            $table->text('address');
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
            $table->unique(['company_id', 'id']);
            $table->index(['company_id', 'is_active', 'name']);
            $table->index(['company_id', 'ntn_cnic']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
