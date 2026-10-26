<?php

namespace App\Services;

use App\Enums\CompanyRole;
use App\Enums\FbrIntegrationStatus;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompanyService
{
    /** @param array<string, mixed> $data */
    public function createOwnedCompany(User $owner, array $data): Company
    {
        abort_if(config('erp.single_user_mode', true) && Company::query()->exists(), 409,
            'Single-company installation active. Update the existing Company Profile instead.');

        return DB::transaction(function () use ($owner, $data): Company {
            $company = Company::create([
                'name' => $data['company_name'],
                'contact_person' => $owner->name,
                'email' => $owner->email,
                'phone' => $owner->phone,
                'province' => $data['province'] ?? null,
                'ntn_cnic' => $data['ntn_cnic'] ?? null,
            ]);

            $company->users()->attach($owner->id, [
                'role' => CompanyRole::Owner->value,
                'is_active' => true,
            ]);

            return $company;
        });
    }

    /** @param array<string, mixed> $data */
    public function createAdditionalCompany(User $owner, array $data): Company
    {
        abort_if(config('erp.single_user_mode', true) && Company::query()->exists(), 409,
            'Single-company installation active. Update the existing Company Profile instead.');

        return DB::transaction(function () use ($owner, $data): Company {
            $company = Company::create([
                ...$data,
                'ntn_cnic' => $data['seller_ntn'],
                'technical_contact' => $data['seller_cnic'],
                'contact_person' => $owner->name,
                'invoice_series' => 'INV',
                'currency' => 'PKR',
                'fbr_environment' => 'sandbox',
            ]);
            $company->users()->attach($owner->id, [
                'role' => CompanyRole::Owner->value,
                'is_active' => true,
            ]);

            return $this->details($owner, $company->id);
        });
    }

    public function members(User $owner, int $companyId): Collection
    {
        $company = $this->details($owner, $companyId);
        abort_unless($company->pivot->role === CompanyRole::Owner, 403);

        return $company->users()->orderBy('users.name')->get();
    }

    public function addMember(User $owner, int $companyId, string $email, CompanyRole $role): User
    {
        abort_if(config('erp.single_user_mode', true), 409, 'Company membership management is disabled in the single-user edition.');

        return DB::transaction(function () use ($owner, $companyId, $email, $role): User {
            $company = $this->details($owner, $companyId);
            abort_unless($company->pivot->role === CompanyRole::Owner, 403);
            $member = User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($email))])->lockForUpdate()->first();
            if ($member === null || ! $member->is_active) {
                throw ValidationException::withMessages(['email' => 'Ask this person to register an active account before adding them.']);
            }
            $existing = $company->users()->whereKey($member->id)->first();
            if ($existing !== null) {
                $company->users()->updateExistingPivot($member->id, ['role' => $role->value, 'is_active' => true]);
            } else {
                $company->users()->attach($member->id, ['role' => $role->value, 'is_active' => true]);
            }

            return $company->users()->whereKey($member->id)->firstOrFail();
        });
    }

    public function updateMembership(User $owner, int $companyId, int $userId, CompanyRole $role, bool $active): User
    {
        abort_if(config('erp.single_user_mode', true), 409, 'Company membership management is disabled in the single-user edition.');

        return DB::transaction(function () use ($owner, $companyId, $userId, $role, $active): User {
            $company = $this->details($owner, $companyId);
            abort_unless($company->pivot->role === CompanyRole::Owner, 403);
            abort_if($owner->id === $userId && (! $active || $role !== CompanyRole::Owner), 422, 'You cannot deactivate or demote your own owner membership.');
            $member = $company->users()->whereKey($userId)->lockForUpdate()->firstOrFail();
            $company->users()->updateExistingPivot($userId, ['role' => $role->value, 'is_active' => $active]);

            return $company->users()->whereKey($member->id)->firstOrFail();
        });
    }

    public function removeMember(User $owner, int $companyId, int $userId): void
    {
        abort_if(config('erp.single_user_mode', true), 409, 'Company membership management is disabled in the single-user edition.');

        DB::transaction(function () use ($owner, $companyId, $userId): void {
            $company = $this->details($owner, $companyId);
            abort_unless($company->pivot->role === CompanyRole::Owner, 403);
            abort_if($owner->id === $userId, 422, 'You cannot remove your own owner membership.');
            abort_unless($company->users()->whereKey($userId)->exists(), 404);
            $company->users()->detach($userId);
        });
    }

    /** @return Collection<int, Company> */
    public function accessibleCompanies(User $user): Collection
    {
        return $user->companies()
            ->where('companies.is_active', true)
            ->wherePivot('is_active', true)
            ->select([
                'companies.id', 'companies.name', 'companies.ntn_cnic', 'companies.province',
                'companies.address', 'companies.contact_person', 'companies.email', 'companies.phone',
                'companies.technical_contact', 'companies.invoice_series', 'companies.currency',
                'companies.fbr_environment', 'companies.business_activity', 'companies.sector',
                'companies.allowed_sandbox_scenarios',
            ])
            ->orderBy('companies.id')
            ->when(config('erp.single_user_mode', true), fn ($query) => $query->limit(1))
            ->get();
    }

    public function workspace(User $user, int $companyId, bool $write = false): Company
    {
        abort_unless($user->is_active, 403);
        if (config('erp.single_user_mode', true)) {
            abort_unless((int) Company::query()->orderBy('id')->value('id') === $companyId, 404);
        }
        $company = $user->companies()->where('companies.id', $companyId)
            ->where('companies.is_active', true)->wherePivot('is_active', true)
            ->select('companies.*')->firstOrFail();
        if ($write) {
            abort_unless($company->pivot->role === CompanyRole::Owner, 403);
        }

        return $company;
    }

    public function details(User $user, int $companyId): Company
    {
        abort_unless($user->is_active, 403);
        if (config('erp.single_user_mode', true)) {
            abort_unless((int) Company::query()->orderBy('id')->value('id') === $companyId, 404);
        }

        return $user->companies()->where('companies.is_active', true)->wherePivot('is_active', true)
            ->where('companies.id', $companyId)->with('fbrIntegrations')->firstOrFail();
    }

    /** @param array<string, mixed> $data */
    public function updateDetails(User $user, int $companyId, array $data): Company
    {
        return DB::transaction(function () use ($user, $companyId, $data): Company {
            $company = $this->details($user, $companyId);
            abort_unless($company->pivot->role === CompanyRole::Owner, 403);
            Company::whereKey($companyId)->lockForUpdate()->firstOrFail();
            $data['ntn_cnic'] = $data['seller_ntn'];
            $data['technical_contact'] = $data['seller_cnic'];
            $fields = ['name', 'ntn_cnic', 'province', 'address', 'contact_person', 'email', 'phone',
                'technical_contact', 'invoice_series', 'currency', 'fbr_environment', 'business_activity',
                'sector', 'allowed_sandbox_scenarios'];
            $company->fill(array_intersect_key($data, array_flip($fields)))->save();
            foreach ([$company->fbr_environment->value] as $environment) {
                $token = $data[$environment.'_token'] ?? null;
                if ($token !== null && $token !== '') {
                    $integration = $company->fbrIntegrations()->firstOrNew(['environment' => $environment]);
                    $integration->token = $token;
                    $integration->status = FbrIntegrationStatus::Configured;
                    $integration->verified_at = null;
                    $integration->save();
                }
            }

            return $this->details($user, $companyId);
        });
    }
}
