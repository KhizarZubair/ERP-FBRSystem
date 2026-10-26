<?php

namespace App\Services;

use App\Enums\CompanyRole;
use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerService
{
    public function company(User $user, int $companyId, bool $write = false): Company
    {
        abort_unless($user->is_active, 403);
        $company = $user->companies()->where('companies.id', $companyId)
            ->where('companies.is_active', true)->wherePivot('is_active', true)
            ->select('companies.*')->firstOrFail();
        if ($write) {
            abort_unless($company->pivot->role === CompanyRole::Owner, 403);
        }

        return $company;
    }

    /** @param array<string, mixed> $filters */
    public function listing(Company $company, array $filters): LengthAwarePaginator
    {
        $query = $company->customers()->select(['id', 'company_id', 'code', 'name', 'registration_type',
            'ntn_cnic', 'province', 'city', 'address', 'email', 'phone', 'is_active']);
        if (! empty($filters['search'])) {
            $search = '%'.addcslashes($filters['search'], '%_\\').'%';
            $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', $search)->orWhere('code', 'like', $search)->orWhere('ntn_cnic', 'like', $search);
            });
        }
        if (! empty($filters['registration_type'])) {
            $query->where('registration_type', $filters['registration_type']);
        }
        $sort = $filters['sort'] ?? 'name';
        $direction = $filters['direction'] ?? 'asc';

        return $query->orderBy($sort, $direction)->orderBy('id', $direction)
            ->paginate($filters['per_page'] ?? 15)->withQueryString();
    }

    public function find(Company $company, int $customerId): Customer
    {
        return $company->customers()->whereKey($customerId)->firstOrFail();
    }

    /** @param array<string, mixed> $data */
    public function save(Company $company, array $data, ?int $customerId = null): Customer
    {
        try {
            return DB::transaction(function () use ($company, $data, $customerId): Customer {
                $customer = $customerId === null ? $company->customers()->make()
                    : $company->customers()->whereKey($customerId)->lockForUpdate()->firstOrFail();
                if ($customerId === null && empty($data['code'])) {
                    Company::whereKey($company->id)->lockForUpdate()->firstOrFail();
                    $next = (int) ($company->customers()->max('id') ?? 0) + 1;
                    do {
                        $data['code'] = 'CUS-'.str_pad((string) $next++, 6, '0', STR_PAD_LEFT);
                    } while ($company->customers()->where('code', $data['code'])->exists());
                }
                $customer->fill($data)->save();

                return $customer;
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['code' => 'Customer code is already in use.']);
        }
    }

    public function status(Company $company, int $customerId, bool $active): Customer
    {
        return $this->save($company, ['is_active' => $active], $customerId);
    }

    public function delete(Company $company, int $customerId): void
    {
        try {
            DB::transaction(function () use ($company, $customerId): void {
                $customer = $company->customers()->whereKey($customerId)->lockForUpdate()->firstOrFail();
                if ($customer->invoices()->exists()) {
                    throw ValidationException::withMessages(['customer' => 'This customer has invoices. Deactivate it instead.']);
                }
                $customer->delete();
            });
        } catch (QueryException $exception) {
            if (($exception->errorInfo[1] ?? null) === 1451) {
                throw ValidationException::withMessages(['customer' => 'This customer has invoices. Deactivate it instead.']);
            }
            throw $exception;
        }
    }
}
