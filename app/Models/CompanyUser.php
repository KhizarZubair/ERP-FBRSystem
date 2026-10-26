<?php

namespace App\Models;

use App\Enums\CompanyRole;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[Fillable(['user_id', 'role', 'is_active'])]
class CompanyUser extends Pivot
{
    use BelongsToCompany;

    protected $table = 'company_user';

    public $incrementing = true;

    protected function casts(): array
    {
        return [
            'role' => CompanyRole::class,
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
