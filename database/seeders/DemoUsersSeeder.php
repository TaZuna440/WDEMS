<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DemoUsersSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the admin and demo accounts.
     *
     * Never runs in production. Idempotent — existing accounts are left
     * untouched, including their passwords and verification state.
     *
     * Two accounts:
     *   - admin@wdems.test        — admin, verified, 2FA off (admin bypasses 2FA)
     *   - pomasinejboy@gmail.com  — staff, unverified, 2FA on (drives the Resend email demo)
     */
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('DemoUsersSeeder skipped — production environment.');
            return;
        }

        $this->createIfMissing(
            name: 'Admin',
            email: 'admin@wdems.test',
            password: 'Wdems@Admin#2026!',
            role: 'admin',
            verified: true,
            twoFactor: false,
        );

        $this->createIfMissing(
            name: 'Demo Staff',
            email: 'pomasinejboy@gmail.com',
            password: 'TaZuna@440',
            role: 'staff',
            verified: false,
            twoFactor: true,
        );

        $this->command?->info('Demo users seeded (or already present).');
    }

    private function createIfMissing(
        string $name,
        string $email,
        string $password,
        string $role,
        bool $verified,
        bool $twoFactor,
    ): void {
        if (User::where('email', $email)->exists()) {
            $this->command?->line("  · {$email} — already exists, skipped.");
            return;
        }

        User::forceCreate([
            'name' => $name,
            'email' => $email,
            'password' => bcrypt($password),
            'role' => $role,
            'email_verified_at' => $verified ? now() : null,
            'email_two_factor_enabled' => $twoFactor,
        ]);

        $this->command?->line("  · {$email} — created.");
    }
}
