<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends EmailAuthRequest
{
    public function authorize(): bool
    {
        abort_if(config('erp.single_user_mode', true) && User::query()->exists(), 403,
            'Single-user installation active. Please login with your master credentials.');

        return true;
    }

    public function rules(): array
    {
        return [
            'company_name' => [Rule::requiredIf(! config('erp.single_user_mode', true)), 'nullable', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+() .-]{7,30}$/'],
            'password' => ['required', 'string', Password::min(8), 'max:128', 'confirmed'],
            'password_confirmation' => ['required', 'string', 'max:128'],
            'province' => [Rule::requiredIf(! config('erp.single_user_mode', true)), 'nullable', 'string', Rule::in(config('erp.provinces'))],
            'ntn_cnic' => ['nullable', 'string', 'max:20', 'regex:/^[0-9-]+$/'],
            'details_confirmed' => [Rule::requiredIf(! config('erp.single_user_mode', true)), 'accepted'],
        ];
    }
}
