<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminRole = Role::where('name', Role::ADMIN)->first();

        User::updateOrCreate(
            ['email' => 'admin@pmpulse.local'],
            [
                'name' => 'Admin User',
                'password' => 'password',
                'auth_provider' => User::AUTH_PROVIDER_PASSWORD,
                'is_active' => true,
                'role_id' => $adminRole?->id,
                'api_token' => Str::random(60),
                'email_verified_at' => now(),
            ]
        );
    }
}
