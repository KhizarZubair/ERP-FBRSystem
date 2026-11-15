<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_scenario_certifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->json('passed_scenarios')->nullable();
            $table->json('certification_details')->nullable();
            $table->boolean('all_passed')->default(false);
            $table->string('token_fingerprint', 64)->nullable();
            $table->string('profile_fingerprint', 64)->nullable();
            $table->timestamp('certified_at')->nullable();
            $table->timestamps();

            $table->unique('company_id');
            $table->index(['company_id', 'all_passed']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_scenario_certifications');
    }
};
