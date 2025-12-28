<?php

namespace Database\Seeders;

use App\Models\FeatureFlag;
use Illuminate\Database\Seeder;

class FeatureFlagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $flags = [
            [
                'name' => 'incremental_sync',
                'enabled' => true,
                'description' => 'Enable incremental sync jobs that run every N minutes',
            ],
            [
                'name' => 'notifications',
                'enabled' => true,
                'description' => 'Enable email notifications for alerts',
            ],
            [
                'name' => 'analytics_refresh',
                'enabled' => true,
                'description' => 'Enable automatic analytics table refresh',
            ],
        ];

        foreach ($flags as $flag) {
            FeatureFlag::firstOrCreate(
                ['name' => $flag['name']],
                $flag
            );
        }
    }
}
