<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /** @param array<string, mixed> $data */
    public function register(array $data): User
    {
        try {
            return DB::transaction(function () use ($data): User {
                if (config('erp.single_user_mode', true) && User::query()->exists()) {
                    abort(403, 'Single-user installation active. Please login with your master credentials.');
                }

                $user = new User([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'phone' => $data['phone'] ?? null,
                    'password' => $data['password'],
                ]);
                $user->forceFill(['is_active' => true])->save();
                if (! config('erp.single_user_mode', true)) {
                    app(CompanyService::class)->createOwnedCompany($user, $data);
                }

                return $user;
            });
        } catch (UniqueConstraintViolationException $exception) {
            if (User::where('email', $data['email'])->exists()) {
                throw ValidationException::withMessages(['email' => 'The email has already been taken.']);
            }

            throw $exception;
        }
    }

    /** @param array{email: string, password: string} $credentials */
    public function login(array $credentials, Session $session): User
    {
        if (! Auth::guard('web')->attempt([...$credentials, 'is_active' => true])) {
            throw ValidationException::withMessages(['email' => 'The email or password is incorrect.']);
        }

        $session->regenerate();
        $user = Auth::guard('web')->user();
        $user->setRelation('companies', app(CompanyService::class)->accessibleCompanies($user));
        if (! config('erp.single_user_mode', true) && $user->companies->isEmpty()) {
            $this->logout($session);
            throw ValidationException::withMessages(['email' => 'This account has no active company access.']);
        }

        return $user;
    }

    public function startSession(User $user, Session $session): void
    {
        Auth::guard('web')->login($user);
        $session->regenerate();
    }

    public function currentUser(User $user, Session $session): User
    {
        if (! $user->is_active) {
            $this->logout($session);
            abort(401, 'Your account is inactive.');
        }

        $user->setRelation('companies', app(CompanyService::class)->accessibleCompanies($user));
        if (! config('erp.single_user_mode', true) && $user->companies->isEmpty()) {
            $this->logout($session);
            abort(403, 'No active company access is available.');
        }

        return $user;
    }

    public function logout(Session $session): void
    {
        Auth::guard('web')->logout();
        $session->invalidate();
        $session->regenerateToken();
    }
}
