<?php

namespace App\Models\Concerns;

use App\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

trait BelongsToCompany
{
    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        if ($companyId < 1) {
            throw new InvalidArgumentException('A valid company ID is required.');
        }

        return $query->where($this->qualifyColumn('company_id'), $companyId);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
