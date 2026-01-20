<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Property;
use App\Models\UtilityAccount;
use App\Models\UtilityType;
use App\Services\UtilityExpenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UtilityExpenseServiceTest extends TestCase
{
    use RefreshDatabase;

    private UtilityExpenseService $service;

    private UtilityType $waterType;

    private UtilityType $electricType;

    private UtilityType $gasType;

    private UtilityType $garbageType;

    private UtilityType $sewerType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new UtilityExpenseService;

        // Get utility types (seeded by migration)
        $this->waterType = UtilityType::where('key', 'water')->firstOrFail();
        $this->electricType = UtilityType::where('key', 'electric')->firstOrFail();
        $this->gasType = UtilityType::where('key', 'gas')->firstOrFail();
        $this->garbageType = UtilityType::where('key', 'garbage')->firstOrFail();
        $this->sewerType = UtilityType::where('key', 'sewer')->firstOrFail();
    }

    public function test_processes_expenses_with_matched_gl_accounts(): void
    {
        // Create a property and utility account mapping
        $property = Property::factory()->create(['external_id' => '12345']);
        $account = UtilityAccount::factory()->forUtilityType($this->waterType)->create([
            'gl_account_number' => '6210',
            'is_active' => true,
        ]);

        $expenses = [
            [
                'property_id' => '12345',
                'expense_account_number' => '6210',
                'amount' => '150.00',
                'bill_date' => '2025-01-15',
                'payee_name' => 'City Water',
            ],
        ];

        $stats = $this->service->processExpenses($expenses);

        $this->assertEquals(1, $stats['created']);
        $this->assertEquals(0, $stats['unmatched']);
        $this->assertDatabaseHas('utility_expenses', [
            'property_id' => $property->id,
            'utility_account_id' => $account->id,
            'gl_account_number' => '6210',
            'amount' => '150.00',
            'vendor_name' => 'City Water',
        ]);
    }

    public function test_skips_expenses_with_unmatched_gl_accounts(): void
    {
        $property = Property::factory()->create(['external_id' => '12345']);

        // No utility account mapping for GL 9999
        $expenses = [
            [
                'property_id' => '12345',
                'expense_account_number' => '9999',
                'amount' => '100.00',
                'bill_date' => '2025-01-15',
            ],
        ];

        $stats = $this->service->processExpenses($expenses);

        $this->assertEquals(0, $stats['created']);
        $this->assertEquals(1, $stats['unmatched']);
        $this->assertDatabaseCount('utility_expenses', 0);
    }

    public function test_updates_existing_utility_expense(): void
    {
        $property = Property::factory()->create(['external_id' => '12345']);
        UtilityAccount::factory()->forUtilityType($this->electricType)->create([
            'gl_account_number' => '6220',
            'is_active' => true,
        ]);

        // First sync - composite ID is based on property_id, bill_date, expense_account_number, amount, reference_number
        $expenses1 = [
            [
                'property_id' => '12345',
                'expense_account_number' => '6220',
                'amount' => '200.00',
                'bill_date' => '2025-01-15',
                'payee_name' => 'PG&E',
                'reference_number' => 'REF-001',
            ],
        ];
        $this->service->processExpenses($expenses1);

        // Second sync with same composite key (same amount and reference)
        $expenses2 = [
            [
                'property_id' => '12345',
                'expense_account_number' => '6220',
                'amount' => '200.00',  // Same amount
                'bill_date' => '2025-01-15',
                'payee_name' => 'PG&E Updated',  // Different vendor
                'reference_number' => 'REF-001',  // Same reference
            ],
        ];
        $stats = $this->service->processExpenses($expenses2);

        $this->assertEquals(0, $stats['created']);
        $this->assertEquals(1, $stats['updated']);
        $this->assertDatabaseCount('utility_expenses', 1);
        $this->assertDatabaseHas('utility_expenses', [
            'vendor_name' => 'PG&E Updated',
        ]);
    }

    public function test_skips_expenses_without_property(): void
    {
        UtilityAccount::factory()->forUtilityType($this->waterType)->create([
            'gl_account_number' => '6210',
            'is_active' => true,
        ]);

        // Expense for property that doesn't exist
        $expenses = [
            [
                'property_id' => '99999',
                'expense_account_number' => '6210',
                'amount' => '100.00',
                'bill_date' => '2025-01-15',
            ],
        ];

        $stats = $this->service->processExpenses($expenses);

        $this->assertEquals(0, $stats['created']);
        $this->assertEquals(1, $stats['skipped']);
    }

    public function test_ignores_inactive_utility_accounts(): void
    {
        $property = Property::factory()->create(['external_id' => '12345']);
        UtilityAccount::factory()->forUtilityType($this->gasType)->create([
            'gl_account_number' => '6230',
            'is_active' => false, // Inactive
        ]);

        $expenses = [
            [
                'property_id' => '12345',
                'expense_account_number' => '6230',
                'amount' => '75.00',
                'bill_date' => '2025-01-15',
            ],
        ];

        $stats = $this->service->processExpenses($expenses);

        $this->assertEquals(0, $stats['created']);
        $this->assertEquals(1, $stats['unmatched']);
    }

    public function test_parses_various_amount_formats(): void
    {
        $property = Property::factory()->create(['external_id' => '12345']);
        $account = UtilityAccount::factory()->forUtilityType($this->garbageType)->create([
            'gl_account_number' => '6240',
            'is_active' => true,
        ]);

        $expenses = [
            [
                'property_id' => '12345',
                'expense_account_number' => '6240',
                'amount' => '$1,234.56',
                'bill_date' => '2025-01-15',
            ],
        ];

        $stats = $this->service->processExpenses($expenses);

        $this->assertEquals(1, $stats['created']);
        $this->assertDatabaseHas('utility_expenses', [
            'utility_account_id' => $account->id,
            'amount' => '1234.56',
        ]);
    }

    public function test_handles_multiple_gl_account_field_names(): void
    {
        $property = Property::factory()->create(['external_id' => '12345']);
        $account = UtilityAccount::factory()->forUtilityType($this->sewerType)->create([
            'gl_account_number' => '6250',
            'is_active' => true,
        ]);

        // Using 'expense_account' format "6250 - Sewer" instead of just the number
        $expenses = [
            [
                'property_id' => '12345',
                'expense_account' => '6250 - Sewer Service',
                'amount' => '50.00',
                'bill_date' => '2025-01-15',
            ],
        ];

        $stats = $this->service->processExpenses($expenses);

        $this->assertEquals(1, $stats['created']);
        $this->assertDatabaseHas('utility_expenses', [
            'utility_account_id' => $account->id,
            'amount' => '50.00',
        ]);
    }

    public function test_processes_billing_period_dates(): void
    {
        $property = Property::factory()->create(['external_id' => '12345']);
        $account = UtilityAccount::factory()->forUtilityType($this->waterType)->create([
            'gl_account_number' => '6210',
            'is_active' => true,
        ]);

        $expenses = [
            [
                'property_id' => '12345',
                'expense_account_number' => '6210',
                'amount' => '100.00',
                'bill_date' => '2025-01-20',
                'period_start' => '2024-12-15',
                'period_end' => '2025-01-15',
            ],
        ];

        $stats = $this->service->processExpenses($expenses);

        $this->assertEquals(1, $stats['created']);

        // Verify the record was created with correct dates
        $expense = \App\Models\UtilityExpense::where('utility_account_id', $account->id)->first();
        $this->assertNotNull($expense);
        $this->assertEquals('2025-01-20', $expense->expense_date->format('Y-m-d'));
        $this->assertEquals('2024-12-15', $expense->period_start->format('Y-m-d'));
        $this->assertEquals('2025-01-15', $expense->period_end->format('Y-m-d'));
    }
}
