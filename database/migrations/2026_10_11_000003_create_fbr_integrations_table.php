<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fbr_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('environment', 20);
            $table->text('token')->nullable();
            $table->string('status', 30)->default('not_configured');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'environment']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fbr_integrations');
    }
};
