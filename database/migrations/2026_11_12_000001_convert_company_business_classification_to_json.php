<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['business_activity', 'sector'] as $column) {
            DB::table('companies')->select(['id', $column])->orderBy('id')->chunkById(200, function ($companies) use ($column): void {
                foreach ($companies as $company) {
                    $value = $company->{$column};
                    if ($value === null || trim((string) $value) === '') {
                        $array = null;
                    } else {
                        $decoded = json_decode((string) $value, true);
                        $array = is_array($decoded) && array_is_list($decoded) ? $decoded : [(string) $value];
                    }

                    DB::table('companies')->where('id', $company->id)->update([
                        $column => $array === null ? null : json_encode(array_values(array_unique($array))),
                    ]);
                }
            });
        }

        Schema::table('companies', function (Blueprint $table): void {
            $table->json('business_activity')->nullable()->change();
            $table->json('sector')->nullable()->change();
        });
    }

    public function down(): void
    {
        foreach (['business_activity', 'sector'] as $column) {
            DB::table('companies')->select(['id', $column])->orderBy('id')->chunkById(200, function ($companies) use ($column): void {
                foreach ($companies as $company) {
                    $decoded = json_decode((string) $company->{$column}, true);
                    $value = is_array($decoded) ? ($decoded[0] ?? null) : $company->{$column};
                    DB::table('companies')->where('id', $company->id)->update([$column => $value]);
                }
            });
        }

        Schema::table('companies', function (Blueprint $table): void {
            $table->string('business_activity', 50)->nullable()->change();
            $table->string('sector', 100)->nullable()->change();
        });
    }
};
