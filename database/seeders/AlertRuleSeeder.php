<?php

namespace Database\Seeders;

use App\Models\AlertRule;
use Illuminate\Database\Seeder;

class AlertRuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rules = [
            [
                'name' => 'High Vacancy Alert',
                'metric' => 'vacancy_count',
                'operator' => 'gt',
                'threshold' => 5,
                'enabled' => true,
                'recipients' => ['admin@pmpulse.local'],
            ],
            [
                'name' => 'High Delinquency Alert',
                'metric' => 'delinquency_amount',
                'operator' => 'gt',
                'threshold' => 10000,
                'enabled' => true,
                'recipients' => ['admin@pmpulse.local'],
            ],
            [
                'name' => 'Work Order Aging Alert',
                'metric' => 'work_order_days_open',
                'operator' => 'gt',
                'threshold' => 7,
                'enabled' => true,
                'recipients' => ['admin@pmpulse.local'],
            ],
            [
                'name' => 'Low Occupancy Alert',
                'metric' => 'occupancy_rate',
                'operator' => 'lt',
                'threshold' => 90,
                'enabled' => true,
                'recipients' => ['admin@pmpulse.local'],
            ],
        ];

        foreach ($rules as $rule) {
            AlertRule::firstOrCreate(
                ['name' => $rule['name']],
                $rule
            );
        }
    }
}
