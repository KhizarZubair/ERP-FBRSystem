<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class SingleUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = strtolower(trim((string) config('erp.admin_default_email', '')));
        $password = (string) config('erp.admin_default_password', '');
        $name = trim((string) config('erp.admin_default_name', 'System Administrator'));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('ADMIN_DEFAULT_EMAIL must be a valid email address.');
        }

        if (strlen($password) < 8) {
            throw new RuntimeException('ADMIN_DEFAULT_PASSWORD must contain at least 8 characters.');
        }

        if (User::query()->count() > 1) {
            throw new RuntimeException('More than one user already exists. Review the existing data before seeding.');
        }

        $user = User::query()->first();
        if ($user !== null) {
            $user->forceFill([
                'name' => $name !== '' ? $name : 'System Administrator',
                'email' => $email,
                'password' => Hash::make($password),
                'is_active' => true,
            ])->save();

            return;
        }

        $user = User::query()->create([
            'name' => $name !== '' ? $name : 'System Administrator',
            'email' => $email,
            'password' => Hash::make($password),
        ]);
        $user->forceFill(['is_active' => true])->save();
    }
}
