<?php

namespace App\Models;

use App\Enums\FbrEnvironment;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;
use RuntimeException;

#[Fillable(['name', 'ntn_cnic', 'province', 'address', 'contact_person', 'email', 'phone', 'technical_contact', 'logo_path', 'invoice_series', 'currency', 'fbr_environment', 'business_activity', 'sector', 'allowed_sandbox_scenarios', 'is_active'])]
class Company extends Model
{
    protected static function booted(): void
    {
        static::creating(function (): void {
            if (config('erp.single_user_mode', true) && static::query()->count() >= 1) {
                throw new RuntimeException('System restricted: only one company profile is allowed.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'fbr_environment' => FbrEnvironment::class,
            'allowed_sandbox_scenarios' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function scopeAccessibleTo(Builder $query, int $userId): Builder
    {
        if ($userId < 1) {
            throw new InvalidArgumentException('A valid user ID is required.');
        }

        return $query->where($this->qualifyColumn('is_active'), true)
            ->whereHas('users', function (Builder $users) use ($userId): void {
                $users->where('users.id', $userId)
                    ->where('users.is_active', true)
                    ->where('company_user.is_active', true);
            });
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->using(CompanyUser::class)->withPivot(['id', 'role', 'is_active'])->withTimestamps();
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(CompanyUser::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function itemPrices(): HasMany
    {
        return $this->hasMany(ItemPrice::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function fbrIntegrations(): HasMany
    {
        return $this->hasMany(FbrIntegration::class);
    }

    public function fbrSubmissions(): HasMany
    {
        return $this->hasMany(FbrSubmission::class);
    }
}
