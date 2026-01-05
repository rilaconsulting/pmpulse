<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\UtilityAccount;
use Illuminate\Database\Seeder;

class UtilityAccountSeeder extends Seeder
{
    /**
     * Common GL account patterns for utility expenses.
     * These are example mappings - actual GL accounts will vary by property management company.
     */
    private const SAMPLE_UTILITY_ACCOUNTS = [
        [
            'gl_account_number' => '6210',
            'gl_account_name' => 'Water Expense',
            'utility_type' => 'water',
        ],
        [
            'gl_account_number' => '6211',
            'gl_account_name' => 'Water & Sewer',
            'utility_type' => 'water',
        ],
        [
            'gl_account_number' => '6220',
            'gl_account_name' => 'Electric Expense',
            'utility_type' => 'electric',
        ],
        [
            'gl_account_number' => '6221',
            'gl_account_name' => 'PG&E',
            'utility_type' => 'electric',
        ],
        [
            'gl_account_number' => '6230',
            'gl_account_name' => 'Gas Expense',
            'utility_type' => 'gas',
        ],
        [
            'gl_account_number' => '6240',
            'gl_account_name' => 'Garbage Expense',
            'utility_type' => 'garbage',
        ],
        [
            'gl_account_number' => '6241',
            'gl_account_name' => 'Trash Removal',
            'utility_type' => 'garbage',
        ],
        [
            'gl_account_number' => '6250',
            'gl_account_name' => 'Sewer Expense',
            'utility_type' => 'sewer',
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::SAMPLE_UTILITY_ACCOUNTS as $account) {
            UtilityAccount::firstOrCreate(
                ['gl_account_number' => $account['gl_account_number']],
                [
                    'gl_account_name' => $account['gl_account_name'],
                    'utility_type' => $account['utility_type'],
                    'is_active' => true,
                ]
            );
        }
    }
}
