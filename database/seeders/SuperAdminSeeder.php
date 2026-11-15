<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Seed the Super Admin (Tax Consultant / Lawyer) user.
     * Guaranteed to have ID = 1 to unlock full Super Admin multi-company privileges.
     */
    public function run(): void
    {
        $name = config('erp.admin_default_name') ?: 'Tax Consultant / Super Admin';
        $email = strtolower(trim((string) (config('erp.admin_default_email') ?: 'admin@erp.local')));
        $password = (string) (config('erp.admin_default_password') ?: 'Admin@12345');

        $user = User::query()->firstOrNew(['id' => 1]);
        $user->forceFill([
            'id' => 1,
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'phone' => '03001234567',
            'is_active' => true,
        ]);
        $user->save();

        if ($this->command) {
            $this->command->info('====================================================');
            $this->command->info(' Super Admin (Tax Consultant / Lawyer) Seeded!');
            $this->command->info(' ID:       1');
            $this->command->info(' Name:     '.$user->name);
            $this->command->info(' Email:    '.$user->email);
            $this->command->info(' Password: '.$password);
            $this->command->info(' Role:     Super Admin / Lawyer Portal Full Access');
            $this->command->info('====================================================');
        }
    }
}
