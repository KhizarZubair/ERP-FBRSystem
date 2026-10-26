<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->is_active;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'max:128', 'current_password:web'],
            'password' => ['required', 'string', Password::min(8), 'max:128', 'confirmed', 'different:current_password'],
            'password_confirmation' => ['required', 'string', 'max:128'],
        ];
    }
}
