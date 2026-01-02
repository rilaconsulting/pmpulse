<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Role::updateOrCreate(
            ['name' => Role::ADMIN],
            [
                'description' => 'Full access to all features',
                'permissions' => [
                    'users.view',
                    'users.create',
                    'users.update',
                    'users.delete',
                    'settings.view',
                    'settings.update',
                    'reports.view',
                    'reports.export',
                    'sync.trigger',
                    'sync.configure',
                ],
            ]
        );

        Role::updateOrCreate(
            ['name' => Role::VIEWER],
            [
                'description' => 'Read-only access to dashboards',
                'permissions' => [
                    'reports.view',
                ],
            ]
        );
    }
}
