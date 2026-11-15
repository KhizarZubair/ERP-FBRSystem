<?php

namespace App\Services;

use App\Enums\CompanyRole;
use App\Enums\FbrIntegrationStatus;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class CompanyService
{
    /** @param array<string, mixed> $data */
    public function createOwnedCompany(User $owner, array $data): Company
    {
        abort_if(config('erp.single_user_mode', false) && Company::query()->exists(), 409,
            'Single-company installation active. Update the existing Company Profile instead.');

        return DB::transaction(function () use ($owner, $data): Company {
            $company = Company::create([
                'name' => $data['company_name'],
                'contact_person' => $owner->name,
                'email' => $owner->email,
                'phone' => $owner->phone,
                'province' => $data['province'] ?? null,
                'ntn_cnic' => $data['ntn_cnic'] ?? null,
                'business_activity' => $data['business_activity'] ?? [],
                'sector' => $data['sector'] ?? [],
                'is_sales_tax_profile_retailer' => (bool) ($data['is_sales_tax_profile_retailer'] ?? false),
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
        abort_if(config('erp.single_user_mode', false) && Company::query()->exists(), 409,
            'Single-company installation active. Update the existing Company Profile instead.');

        return DB::transaction(function () use ($owner, $data): Company {
            $sellerNtn = filled($data['seller_ntn'] ?? null) ? trim((string) $data['seller_ntn']) : null;
            $sellerCnic = filled($data['seller_cnic'] ?? null) ? trim((string) $data['seller_cnic']) : null;

            $company = Company::create([
                ...$data,
                'ntn_cnic' => $sellerNtn ?? $sellerCnic,
                'technical_contact' => $sellerCnic,
                'contact_person' => $owner->name,
                'invoice_series' => $data['invoice_series'] ?? 'INV',
                'currency' => $data['currency'] ?? 'PKR',
                'fbr_environment' => $data['fbr_environment'] ?? 'sandbox',
            ]);
            $company->users()->attach($owner->id, [
                'role' => CompanyRole::Owner->value,
                'is_active' => true,
            ]);

            return $this->details($owner, $company->id);
        });
    }

    /**
     * Atomically enroll a new client company with optional client user credentials (REQ-01).
     *
     * @param  array<string, mixed>  $companyData
     * @param  array<string, mixed>|null  $clientUserData
     * @return array{company: Company, client_user: User|null}
     */
    public function enrollCompanyWithUser(User $superAdmin, array $companyData, ?array $clientUserData = null): array
    {
        abort_unless($superAdmin->isSuperAdmin(), 403, 'Only Super Admin / Tax Practitioner can enroll client companies.');

        return DB::transaction(function () use ($superAdmin, $companyData, $clientUserData): array {
            $sellerNtn = filled($companyData['seller_ntn'] ?? null) ? trim((string) $companyData['seller_ntn']) : null;
            $sellerCnic = filled($companyData['seller_cnic'] ?? null) ? trim((string) $companyData['seller_cnic']) : null;

            $company = Company::create([
                'name' => trim((string) $companyData['name']),
                'ntn_cnic' => $sellerNtn ?? $sellerCnic ?? ($companyData['ntn_cnic'] ?? null),
                'province' => $companyData['province'] ?? null,
                'address' => $companyData['address'] ?? null,
                'contact_person' => $companyData['contact_person'] ?? null,
                'email' => $companyData['email'] ?? null,
                'phone' => $companyData['phone'] ?? null,
                'technical_contact' => $sellerCnic ?? ($companyData['technical_contact'] ?? null),
                'invoice_series' => $companyData['invoice_series'] ?? 'INV',
                'currency' => $companyData['currency'] ?? 'PKR',
                'fbr_environment' => $companyData['fbr_environment'] ?? 'sandbox',
                'business_activity' => $companyData['business_activity'] ?? [],
                'sector' => $companyData['sector'] ?? [],
                'is_sales_tax_profile_retailer' => (bool) ($companyData['is_sales_tax_profile_retailer'] ?? false),
                'is_active' => true,
            ]);

            // Setup FBR tokens if provided
            foreach (['sandbox', 'production'] as $env) {
                $tokenKey = $env.'_token';
                if (filled($companyData[$tokenKey] ?? null)) {
                    $integration = $company->fbrIntegrations()->firstOrNew(['environment' => $env]);
                    $integration->token = trim((string) $companyData[$tokenKey]);
                    $integration->status = FbrIntegrationStatus::Configured;
                    $integration->save();
                }
            }

            // Attach Super Admin with owner role
            $company->users()->attach($superAdmin->id, [
                'role' => CompanyRole::Owner->value,
                'is_active' => true,
            ]);

            // Create or attach Client Representative user if provided
            $clientUser = null;
            if ($clientUserData !== null && filled($clientUserData['email'] ?? null)) {
                $clientEmail = mb_strtolower(trim((string) $clientUserData['email']));
                $clientUser = User::whereRaw('LOWER(email) = ?', [$clientEmail])->first();

                if ($clientUser === null) {
                    $clientUser = User::create([
                        'name' => trim((string) ($clientUserData['name'] ?? $company->name.' Representative')),
                        'email' => $clientEmail,
                        'phone' => $clientUserData['phone'] ?? $company->phone,
                        'password' => $clientUserData['password'] ?? 'Welcome@123',
                    ]);
                    $clientUser->forceFill(['is_active' => true])->save();
                }

                $company->users()->syncWithoutDetaching([
                    $clientUser->id => [
                        'role' => CompanyRole::Owner->value,
                        'is_active' => true,
                    ],
                ]);
            }

            return [
                'company' => $this->details($superAdmin, $company->id),
                'client_user' => $clientUser,
            ];
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
        abort_if(config('erp.single_user_mode', false), 409, 'Company membership management is disabled in the single-user edition.');

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
        abort_if(config('erp.single_user_mode', false), 409, 'Company membership management is disabled in the single-user edition.');

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
        abort_if(config('erp.single_user_mode', false), 409, 'Company membership management is disabled in the single-user edition.');

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
        if ($user->isSuperAdmin()) {
            return Company::query()
                ->where('is_active', true)
                ->select([
                    'companies.id', 'companies.name', 'companies.ntn_cnic', 'companies.province',
                    'companies.address', 'companies.contact_person', 'companies.email', 'companies.phone',
                    'companies.technical_contact', 'companies.invoice_series', 'companies.currency',
                    'companies.fbr_environment', 'companies.business_activity', 'companies.sector',
                    'companies.is_sales_tax_profile_retailer',
                    'companies.allowed_sandbox_scenarios',
                ])
                ->orderBy('companies.id')
                ->get();
        }

        return $user->companies()
            ->where('companies.is_active', true)
            ->wherePivot('is_active', true)
            ->select([
                'companies.id', 'companies.name', 'companies.ntn_cnic', 'companies.province',
                'companies.address', 'companies.contact_person', 'companies.email', 'companies.phone',
                'companies.technical_contact', 'companies.invoice_series', 'companies.currency',
                'companies.fbr_environment', 'companies.business_activity', 'companies.sector',
                'companies.is_sales_tax_profile_retailer',
                'companies.allowed_sandbox_scenarios',
            ])
            ->orderBy('companies.id')
            ->when(config('erp.single_user_mode', false), fn ($query) => $query->limit(1))
            ->get();
    }

    public function workspace(User $user, int $companyId, bool $write = false): Company
    {
        abort_unless($user->is_active, 403);

        if ($user->isSuperAdmin()) {
            $company = Company::where('id', $companyId)->where('is_active', true)->firstOrFail();
            $pivot = new CompanyUser(['role' => CompanyRole::Owner->value, 'is_active' => true]);
            $pivot->setRelation('company', $company);
            $company->setRelation('pivot', $pivot);

            return $company;
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

        if ($user->isSuperAdmin()) {
            $company = Company::where('companies.id', $companyId)
                ->where('companies.is_active', true)
                ->with(['fbrIntegrations', 'scenarioCertification'])
                ->firstOrFail();
            $pivot = new CompanyUser(['role' => CompanyRole::Owner->value, 'is_active' => true]);
            $pivot->setRelation('company', $company);
            $company->setRelation('pivot', $pivot);

            return $company;
        }

        return $user->companies()->where('companies.is_active', true)->wherePivot('is_active', true)
            ->where('companies.id', $companyId)->with(['fbrIntegrations', 'scenarioCertification'])->firstOrFail();
    }

    /** @param array<string, mixed> $data */
    public function updateDetails(User $user, int $companyId, array $data): Company
    {
        return DB::transaction(function () use ($user, $companyId, $data): Company {
            $company = $this->details($user, $companyId);
            abort_unless($user->isSuperAdmin() || $company->pivot->role === CompanyRole::Owner, 403);
            Company::whereKey($companyId)->lockForUpdate()->firstOrFail();
            $sellerNtn = filled($data['seller_ntn'] ?? null) ? trim((string) $data['seller_ntn']) : null;
            $sellerCnic = filled($data['seller_cnic'] ?? null) ? trim((string) $data['seller_cnic']) : null;

            $data['ntn_cnic'] = $sellerNtn ?? $sellerCnic;
            $data['technical_contact'] = $sellerCnic;
            $fields = ['name', 'ntn_cnic', 'province', 'address', 'contact_person', 'email', 'phone',
                'technical_contact', 'invoice_series', 'currency', 'fbr_environment', 'business_activity',
                'sector', 'is_sales_tax_profile_retailer', 'allowed_sandbox_scenarios'];
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
