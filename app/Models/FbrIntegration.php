<?php

namespace App\Models;

use App\Enums\FbrEnvironment;
use App\Enums\FbrIntegrationStatus;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['environment', 'token', 'status', 'verified_at'])]
#[Hidden(['token'])]
class FbrIntegration extends Model
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'environment' => FbrEnvironment::class,
            'status' => FbrIntegrationStatus::class,
            'verified_at' => 'datetime',
            'token' => 'encrypted',
        ];
    }
}
