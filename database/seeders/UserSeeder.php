<?php

namespace Database\Seeders;

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
        User::firstOrCreate(
            ['email' => 'admin@pmpulse.local'],
            [
                'name' => 'Admin User',
                'password' => 'password',
                'api_token' => Str::random(60),
                'email_verified_at' => now(),
            ]
        );
    }
}
