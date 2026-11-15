<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id',
    'user_id',
    'passed_scenarios',
    'certification_details',
    'all_passed',
    'token_fingerprint',
    'profile_fingerprint',
    'certified_at',
])]
class CompanyScenarioCertification extends Model
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'passed_scenarios' => 'array',
            'certification_details' => 'array',
            'all_passed' => 'boolean',
            'certified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
