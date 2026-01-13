<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CreateAdminUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:create-admin
                            {name : The name of the admin user}
                            {email : The email address of the admin user}
                            {--password= : The password (will prompt if not provided)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create an admin user with password authentication';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = $this->argument('name');
        $email = $this->argument('email');
        $password = $this->option('password');

        // Validate email
        $validator = Validator::make(
            ['email' => $email],
            ['email' => 'required|email']
        );

        if ($validator->fails()) {
            $this->error('Invalid email address.');

            return Command::FAILURE;
        }

        // Check if user already exists
        if (User::where('email', $email)->exists()) {
            $this->error("A user with email '{$email}' already exists.");

            return Command::FAILURE;
        }

        // Get or create admin role
        $adminRole = Role::firstOrCreate(
            ['name' => Role::ADMIN],
            [
                'description' => 'Full system access',
                'permissions' => [],
            ]
        );

        // Prompt for password if not provided
        if (! $password) {
            $password = $this->secret('Enter password (min 8 characters)');

            if (! $password) {
                $this->error('Password is required.');

                return Command::FAILURE;
            }

            $confirmPassword = $this->secret('Confirm password');

            if ($password !== $confirmPassword) {
                $this->error('Passwords do not match.');

                return Command::FAILURE;
            }
        }

        // Validate password length
        if (strlen($password) < 8) {
            $this->error('Password must be at least 8 characters.');

            return Command::FAILURE;
        }

        // Create the user
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'auth_provider' => User::AUTH_PROVIDER_PASSWORD,
            'is_active' => true,
            'role_id' => $adminRole->id,
            'api_token' => Str::random(60),
            'email_verified_at' => now(),
        ]);

        $this->info('Admin user created successfully!');
        $this->table(
            ['Field', 'Value'],
            [
                ['Name', $user->name],
                ['Email', $user->email],
                ['Role', 'Admin'],
                ['Auth Method', 'Password'],
            ]
        );

        return Command::SUCCESS;
    }
}
