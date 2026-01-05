<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Property;
use App\Models\UtilityAccount;
use App\Services\UtilityExpenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UtilityExpenseServiceTest extends TestCase
{
    use RefreshDatabase;

    private UtilityExpenseService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new UtilityExpenseService();
    }

    public function test_processes_expenses_with_matched_gl_accounts(): void
    {
        // Create a property and utility account mapping
        $property = Property::factory()->create(['external_id' => '12345']);
        UtilityAccount::factory()->create([
            'gl_account_number' => '6210',
            'utility_type' => 'water',
            'is_active' => true,
        ]);

        $expenses = [
            [
                'expense_id' => 'exp-001',
                'property_id' => '12345',
                'gl_account_number' => '6210',
                'amount' => '150.00',
                'expense_date' => '2025-01-15',
                'vendor_name' => 'City Water',
            ],
        ];

        $stats = $this->service->processExpenses($expenses);

        $this->assertEquals(1, $stats['created']);
        $this->assertEquals(0, $stats['unmatched']);
        $this->assertDatabaseHas('utility_expenses', [
            'property_id' => $property->id,
            'utility_type' => 'water',
            'external_expense_id' => 'exp-001',
        ]);
    }

    public function test_skips_expenses_with_unmatched_gl_accounts(): void
    {
        $property = Property::factory()->create(['external_id' => '12345']);

        // No utility account mapping for GL 9999
        $expenses = [
            [
                'expense_id' => 'exp-002',
                'property_id' => '12345',
                'gl_account_number' => '9999',
                'amount' => '100.00',
                'expense_date' => '2025-01-15',
            ],
        ];

        $stats = $this->service->processExpenses($expenses);

        $this->assertEquals(0, $stats['created']);
        $this->assertEquals(1, $stats['unmatched']);
        $this->assertDatabaseMissing('utility_expenses', [
            'external_expense_id' => 'exp-002',
        ]);
    }

    public function test_updates_existing_utility_expense(): void
    {
        $property = Property::factory()->create(['external_id' => '12345']);
        UtilityAccount::factory()->create([
            'gl_account_number' => '6220',
            'utility_type' => 'electric',
            'is_active' => true,
        ]);

        // First sync
        $expenses1 = [
            [
                'expense_id' => 'exp-003',
                'property_id' => '12345',
                'gl_account_number' => '6220',
                'amount' => '200.00',
                'expense_date' => '2025-01-15',
                'vendor_name' => 'PG&E',
            ],
        ];
        $this->service->processExpenses($expenses1);

        // Second sync with updated amount
        $expenses2 = [
            [
                'expense_id' => 'exp-003',
                'property_id' => '12345',
                'gl_account_number' => '6220',
                'amount' => '225.50',
                'expense_date' => '2025-01-15',
                'vendor_name' => 'PG&E',
            ],
        ];
        $stats = $this->service->processExpenses($expenses2);

        $this->assertEquals(0, $stats['created']);
        $this->assertEquals(1, $stats['updated']);
        $this->assertDatabaseCount('utility_expenses', 1);
        $this->assertDatabaseHas('utility_expenses', [
            'external_expense_id' => 'exp-003',
            'amount' => '225.50',
        ]);
    }

    public function test_skips_expenses_without_property(): void
    {
        UtilityAccount::factory()->create([
            'gl_account_number' => '6210',
            'utility_type' => 'water',
            'is_active' => true,
        ]);

        // Expense for property that doesn't exist
        $expenses = [
            [
                'expense_id' => 'exp-004',
                'property_id' => '99999',
                'gl_account_number' => '6210',
                'amount' => '100.00',
                'expense_date' => '2025-01-15',
            ],
        ];

        $stats = $this->service->processExpenses($expenses);

        $this->assertEquals(0, $stats['created']);
        $this->assertEquals(1, $stats['skipped']);
    }

    public function test_ignores_inactive_utility_accounts(): void
    {
        $property = Property::factory()->create(['external_id' => '12345']);
        UtilityAccount::factory()->create([
            'gl_account_number' => '6230',
            'utility_type' => 'gas',
            'is_active' => false, // Inactive
        ]);

        $expenses = [
            [
                'expense_id' => 'exp-005',
                'property_id' => '12345',
                'gl_account_number' => '6230',
                'amount' => '75.00',
                'expense_date' => '2025-01-15',
            ],
        ];

        $stats = $this->service->processExpenses($expenses);

        $this->assertEquals(0, $stats['created']);
        $this->assertEquals(1, $stats['unmatched']);
    }

    public function test_parses_various_amount_formats(): void
    {
        $property = Property::factory()->create(['external_id' => '12345']);
        UtilityAccount::factory()->create([
            'gl_account_number' => '6240',
            'utility_type' => 'garbage',
            'is_active' => true,
        ]);

        $expenses = [
            [
                'expense_id' => 'exp-006',
                'property_id' => '12345',
                'gl_account_number' => '6240',
                'amount' => '$1,234.56',
                'expense_date' => '2025-01-15',
            ],
        ];

        $stats = $this->service->processExpenses($expenses);

        $this->assertEquals(1, $stats['created']);
        $this->assertDatabaseHas('utility_expenses', [
            'external_expense_id' => 'exp-006',
            'amount' => '1234.56',
        ]);
    }

    public function test_handles_multiple_gl_account_field_names(): void
    {
        $property = Property::factory()->create(['external_id' => '12345']);
        UtilityAccount::factory()->create([
            'gl_account_number' => '6250',
            'utility_type' => 'sewer',
            'is_active' => true,
        ]);

        // Using 'expense_account' instead of 'gl_account_number'
        $expenses = [
            [
                'expense_id' => 'exp-007',
                'property_id' => '12345',
                'expense_account' => '6250',
                'amount' => '50.00',
                'expense_date' => '2025-01-15',
            ],
        ];

        $stats = $this->service->processExpenses($expenses);

        $this->assertEquals(1, $stats['created']);
        $this->assertDatabaseHas('utility_expenses', [
            'utility_type' => 'sewer',
            'external_expense_id' => 'exp-007',
        ]);
    }

    public function test_processes_billing_period_dates(): void
    {
        $property = Property::factory()->create(['external_id' => '12345']);
        UtilityAccount::factory()->create([
            'gl_account_number' => '6210',
            'utility_type' => 'water',
            'is_active' => true,
        ]);

        $expenses = [
            [
                'expense_id' => 'exp-008',
                'property_id' => '12345',
                'gl_account_number' => '6210',
                'amount' => '100.00',
                'expense_date' => '2025-01-20',
                'period_start' => '2024-12-15',
                'period_end' => '2025-01-15',
            ],
        ];

        $stats = $this->service->processExpenses($expenses);

        $this->assertEquals(1, $stats['created']);

        // Verify the record was created
        $expense = \App\Models\UtilityExpense::where('external_expense_id', 'exp-008')->first();
        $this->assertNotNull($expense);
        $this->assertEquals('2025-01-20', $expense->expense_date->format('Y-m-d'));
        $this->assertEquals('2024-12-15', $expense->period_start->format('Y-m-d'));
        $this->assertEquals('2025-01-15', $expense->period_end->format('Y-m-d'));
    }
}
