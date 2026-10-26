<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('ntn_cnic', 20)->nullable();
            $table->string('province', 100)->nullable();
            $table->text('address')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('technical_contact')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('invoice_series', 30)->default('INV');
            $table->unsignedBigInteger('next_invoice_number')->default(1);
            $table->char('currency', 3)->default('PKR');
            $table->string('fbr_environment', 20)->default('sandbox');
            $table->string('business_activity', 50)->nullable();
            $table->string('sector', 100)->nullable();
            $table->json('allowed_sandbox_scenarios')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
