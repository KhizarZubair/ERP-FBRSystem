<?php

namespace App\Models;

use App\Enums\RegistrationType;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'registration_type', 'ntn_cnic', 'province', 'city', 'address', 'email', 'phone', 'is_active'])]
class Customer extends Model
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'registration_type' => RegistrationType::class,
            'is_active' => 'boolean',
        ];
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
