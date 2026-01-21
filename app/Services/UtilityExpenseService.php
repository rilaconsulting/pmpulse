<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BillDetail;
use App\Models\Property;
use App\Models\UtilityAccount;
use App\Models\UtilityExpense;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Utility Expense Service
 *
 * This service processes synced expense data and creates utility expense records
 * based on configured GL account mappings in the utility_accounts table.
 */
class UtilityExpenseService
{
    /**
     * Cache of GL account number => UtilityAccount mappings.
     */
    private ?\Illuminate\Support\Collection $accountMappings = null;

    /**
     * Cache of property external_id => property UUID mappings.
     *
     * @var array<string, string>
     */
    private array $propertyCache = [];

    /**
     * Statistics for the current processing run.
     */
    private int $created = 0;

    private int $updated = 0;

    private int $skipped = 0;

    private int $unmatched = 0;

    private array $errors = [];

    /**
     * Process expense data and create utility expense records.
     *
     * @param  array  $expenses  Array of expense records from AppFolio
     * @return array Processing statistics
     */
    public function processExpenses(array $expenses): array
    {
        $this->resetStats();
        $this->loadAccountMappings();
        $this->prefetchProperties($expenses);

        foreach ($expenses as $expense) {
            try {
                $this->processExpense($expense);
            } catch (\Exception $e) {
                $this->errors[] = [
                    'expense_id' => $expense['expense_id'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ];
                Log::error('Failed to process utility expense', [
                    'expense_id' => $expense['expense_id'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $stats = $this->getStats();
        $this->logSummary($stats);

        return $stats;
    }

    /**
     * Process a single expense record.
     */
    private function processExpense(array $expense): void
    {
        // Extract GL account number from expense
        $glAccountNumber = $this->extractGlAccountNumber($expense);

        if (empty($glAccountNumber)) {
            $this->skipped++;

            return;
        }

        // Check if this GL account maps to a utility account
        $utilityAccount = $this->accountMappings->get($glAccountNumber);

        if ($utilityAccount === null) {
            $this->unmatched++;
            Log::debug('Expense GL account not mapped to utility type', [
                'gl_account' => $glAccountNumber,
                'expense_id' => $expense['expense_id'] ?? 'unknown',
            ]);

            return;
        }

        $utilityAccountId = $utilityAccount->id;

        // Get property UUID from external property ID
        $propertyId = $this->lookupPropertyId($expense);

        if ($propertyId === null) {
            $this->skipped++;
            Log::warning('Property not found for utility expense', [
                'property_external_id' => $expense['property_id'] ?? 'unknown',
                'expense_id' => $expense['expense_id'] ?? 'unknown',
            ]);

            return;
        }

        // Extract expense data
        // AppFolio expense_register doesn't have a unique ID, so we create a composite key
        $externalExpenseId = $this->generateExpenseId($expense);
        $amount = $this->parseAmount($expense['amount'] ?? $expense['total'] ?? 0);
        $expenseDate = $this->parseDate($expense['expense_date'] ?? $expense['bill_date'] ?? $expense['date']);
        $periodStart = $this->parseDate($expense['period_start'] ?? $expense['service_start'] ?? null);
        $periodEnd = $this->parseDate($expense['period_end'] ?? $expense['service_end'] ?? null);
        $vendorName = $expense['payee_name'] ?? $expense['vendor_name'] ?? $expense['vendor'] ?? $expense['payee'] ?? null;
        $description = $expense['description'] ?? $expense['memo'] ?? null;

        // Create or update utility expense record
        DB::transaction(function () use (
            $propertyId,
            $utilityAccountId,
            $glAccountNumber,
            $externalExpenseId,
            $amount,
            $expenseDate,
            $periodStart,
            $periodEnd,
            $vendorName,
            $description
        ) {
            $utilityExpense = UtilityExpense::updateOrCreate(
                ['external_expense_id' => $externalExpenseId],
                [
                    'property_id' => $propertyId,
                    'utility_account_id' => $utilityAccountId,
                    'gl_account_number' => $glAccountNumber,
                    'expense_date' => $expenseDate,
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'amount' => $amount,
                    'vendor_name' => $vendorName,
                    'description' => $description,
                ]
            );

            if ($utilityExpense->wasRecentlyCreated) {
                $this->created++;
            } else {
                $this->updated++;
            }
        });
    }

    /**
     * Generate a unique ID for an expense from composite fields.
     *
     * AppFolio expense_register doesn't have a unique expense ID,
     * so we create one from property, date, account, and amount.
     */
    private function generateExpenseId(array $expense): string
    {
        $propertyId = $expense['property_id'] ?? 'unknown';
        $billDate = $expense['bill_date'] ?? $expense['expense_date'] ?? 'unknown';
        $account = $expense['expense_account_number'] ?? $expense['expense_account'] ?? 'unknown';
        $amount = $expense['amount'] ?? '0';
        $reference = $expense['reference_number'] ?? '';

        // Create a deterministic hash from the composite fields
        return md5("{$propertyId}|{$billDate}|{$account}|{$amount}|{$reference}");
    }

    /**
     * Extract GL account number from expense data.
     *
     * AppFolio expense data may have the GL account in different fields.
     */
    private function extractGlAccountNumber(array $expense): ?string
    {
        // Try various field names that might contain the GL account
        // expense_account_number is the direct numeric code from AppFolio
        $glAccount = $expense['expense_account_number']
            ?? $expense['gl_account_number']
            ?? $expense['gl_account']
            ?? $expense['expense_account']
            ?? $expense['account_number']
            ?? $expense['account']
            ?? null;

        // If it's a nested object, try to extract the account number
        if (is_array($glAccount)) {
            $glAccount = $glAccount['number'] ?? $glAccount['account_number'] ?? null;
        }

        if ($glAccount === null) {
            return null;
        }

        $glAccount = (string) $glAccount;

        // AppFolio returns GL accounts in format "6210 - Water" or just "6210"
        // Extract just the numeric prefix for matching
        if (preg_match('/^(\d+)/', $glAccount, $matches)) {
            return $matches[1];
        }

        return $glAccount;
    }

    /**
     * Look up property UUID from expense data.
     */
    private function lookupPropertyId(array $expense): ?string
    {
        $externalId = (string) ($expense['property_id'] ?? $expense['property']['id'] ?? null);

        if (empty($externalId)) {
            return null;
        }

        if (isset($this->propertyCache[$externalId])) {
            return $this->propertyCache[$externalId];
        }

        $property = Property::where('external_id', $externalId)->first();
        if ($property) {
            $this->propertyCache[$externalId] = $property->id;

            return $property->id;
        }

        return null;
    }

    /**
     * Parse amount from various formats.
     */
    private function parseAmount(mixed $amount): float
    {
        if (is_numeric($amount)) {
            return abs((float) $amount);
        }

        if (is_string($amount)) {
            // Remove currency symbols and commas
            $cleaned = preg_replace('/[^0-9.-]/', '', $amount);

            return abs((float) $cleaned);
        }

        return 0.0;
    }

    /**
     * Parse date from various formats.
     */
    private function parseDate(mixed $date): ?Carbon
    {
        if ($date === null || $date === '') {
            return null;
        }

        if ($date instanceof Carbon) {
            return $date;
        }

        try {
            return Carbon::parse($date);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Load active GL account mappings.
     */
    private function loadAccountMappings(): void
    {
        if ($this->accountMappings === null) {
            $this->accountMappings = UtilityAccount::getActiveAccountsByGlNumber();

            Log::info('Loaded utility account mappings', [
                'count' => $this->accountMappings->count(),
            ]);
        }
    }

    /**
     * Prefetch properties to avoid N+1 queries.
     */
    private function prefetchProperties(array $expenses): void
    {
        $externalIds = collect($expenses)
            ->map(fn ($expense) => (string) ($expense['property_id'] ?? $expense['property']['id'] ?? null))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($externalIds)) {
            return;
        }

        Property::whereIn('external_id', $externalIds)
            ->pluck('id', 'external_id')
            ->each(fn ($id, $externalId) => $this->propertyCache[$externalId] = $id);
    }

    /**
     * Reset processing statistics.
     */
    private function resetStats(): void
    {
        $this->created = 0;
        $this->updated = 0;
        $this->skipped = 0;
        $this->unmatched = 0;
        $this->errors = [];
    }

    /**
     * Get processing statistics.
     */
    public function getStats(): array
    {
        return [
            'created' => $this->created,
            'updated' => $this->updated,
            'skipped' => $this->skipped,
            'unmatched' => $this->unmatched,
            'errors' => count($this->errors),
            'error_details' => $this->errors,
        ];
    }

    /**
     * Log processing summary.
     */
    private function logSummary(array $stats): void
    {
        Log::info('Utility expense processing complete', [
            'created' => $stats['created'],
            'updated' => $stats['updated'],
            'skipped' => $stats['skipped'],
            'unmatched' => $stats['unmatched'],
            'errors' => $stats['errors'],
        ]);
    }

    /**
     * Process expenses from raw AppFolio events.
     *
     * This method can be called to reprocess expenses from stored raw events.
     *
     * @param  string|null  $syncRunId  Optional sync run ID to filter by
     * @return array Processing statistics
     */
    public function processFromRawEvents(?string $syncRunId = null): array
    {
        $query = \App\Models\RawAppfolioEvent::where('resource_type', 'expenses');

        if ($syncRunId !== null) {
            $query->where('sync_run_id', $syncRunId);
        }

        $expenses = $query->get()
            ->map(fn ($event) => $event->payload_json)
            ->all();

        return $this->processExpenses($expenses);
    }

    /**
     * Process bill details to create utility expense records.
     *
     * This method reads from the bill_details table and creates utility expense
     * records for bills that match configured GL accounts in utility_accounts.
     *
     * @param  string|null  $syncRunId  Optional sync run ID to filter by
     * @return array Processing statistics
     */
    public function processFromBillDetails(?string $syncRunId = null): array
    {
        $this->resetStats();
        $this->loadAccountMappings();

        // Get the GL account numbers that are mapped to utility accounts
        $utilityGlAccounts = $this->accountMappings->keys()->all();

        if (empty($utilityGlAccounts)) {
            Log::info('No utility accounts configured, skipping utility expense processing');

            return $this->getStats();
        }

        // Query bill details that match utility GL accounts
        $query = BillDetail::whereIn('gl_account_number', $utilityGlAccounts);

        if ($syncRunId !== null) {
            $query->where('sync_run_id', $syncRunId);
        }

        $totalCount = $query->count();

        Log::info('Processing bill details for utility expenses', [
            'total_bill_details' => $totalCount,
            'utility_gl_accounts' => $utilityGlAccounts,
            'sync_run_id' => $syncRunId,
        ]);

        // Process in chunks to manage memory for large datasets
        $query->chunk(500, function ($billDetails) {
            foreach ($billDetails as $billDetail) {
                try {
                    $this->processBillDetailToUtilityExpense($billDetail);
                } catch (\Exception $e) {
                    $this->errors[] = [
                        'txn_id' => $billDetail->txn_id,
                        'error' => $e->getMessage(),
                    ];
                    Log::error('Failed to process bill detail to utility expense', [
                        'txn_id' => $billDetail->txn_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });

        $stats = $this->getStats();
        $this->logSummary($stats);

        return $stats;
    }

    /**
     * Process a single bill detail to create/update a utility expense.
     */
    private function processBillDetailToUtilityExpense(BillDetail $billDetail): void
    {
        $glAccountNumber = $billDetail->gl_account_number;

        if (empty($glAccountNumber)) {
            $this->skipped++;

            return;
        }

        // Get the utility account for this GL account number
        $utilityAccount = $this->accountMappings->get($glAccountNumber);

        if ($utilityAccount === null) {
            $this->unmatched++;

            return;
        }

        // Skip if no property linked
        if ($billDetail->property_id === null) {
            $this->skipped++;
            Log::debug('Bill detail has no property, skipping', [
                'txn_id' => $billDetail->txn_id,
            ]);

            return;
        }

        // Calculate total amount (paid + unpaid)
        $amount = (float) (($billDetail->paid ?? 0) + ($billDetail->unpaid ?? 0));

        if ($amount === 0.0) {
            $this->skipped++;

            return;
        }

        // Use txn_id as the unique identifier for the expense
        $externalExpenseId = 'txn_'.$billDetail->txn_id;

        // Create or update utility expense record
        DB::transaction(function () use (
            $billDetail,
            $utilityAccount,
            $glAccountNumber,
            $externalExpenseId,
            $amount
        ) {
            $utilityExpense = UtilityExpense::updateOrCreate(
                ['external_expense_id' => $externalExpenseId],
                [
                    'property_id' => $billDetail->property_id,
                    'utility_account_id' => $utilityAccount->id,
                    'gl_account_number' => $glAccountNumber,
                    'expense_date' => $billDetail->bill_date,
                    'period_start' => $billDetail->service_from,
                    'period_end' => $billDetail->service_to,
                    'amount' => abs($amount),
                    'vendor_name' => $billDetail->payee_name,
                    'description' => $billDetail->description,
                    'bill_detail_id' => $billDetail->id,
                ]
            );

            if ($utilityExpense->wasRecentlyCreated) {
                $this->created++;
            } else {
                $this->updated++;
            }
        });
    }

    /**
     * Reprocess all utility expenses with current account mappings.
     *
     * This method should be called when utility account mappings change
     * to retroactively apply the new mappings to all existing data.
     *
     * Steps:
     * 1. Delete all existing utility_expenses
     * 2. Reprocess all bill_details with current mappings
     *
     * @param  string|null  $fromDate  Optional start date filter (Y-m-d)
     * @param  string|null  $toDate  Optional end date filter (Y-m-d)
     * @return array Processing statistics
     */
    public function reprocessAllWithCurrentMappings(?string $fromDate = null, ?string $toDate = null): array
    {
        $this->resetStats();
        $this->loadAccountMappings();

        // Build the date filter for deletion
        $deleteQuery = UtilityExpense::query();
        if ($fromDate !== null) {
            $deleteQuery->where('expense_date', '>=', $fromDate);
        }
        if ($toDate !== null) {
            $deleteQuery->where('expense_date', '<=', $toDate);
        }

        // Count and delete existing utility expenses
        $deletedCount = $deleteQuery->count();
        $deleteQuery->delete();

        Log::info('Deleted existing utility expenses for reprocessing', [
            'deleted_count' => $deletedCount,
            'from_date' => $fromDate,
            'to_date' => $toDate,
        ]);

        // Get the GL account numbers that are mapped to utility accounts
        $utilityGlAccounts = $this->accountMappings->keys()->all();

        if (empty($utilityGlAccounts)) {
            Log::info('No utility accounts configured, nothing to reprocess');

            return array_merge($this->getStats(), ['deleted' => $deletedCount]);
        }

        // Query bill details that match utility GL accounts
        $query = BillDetail::whereIn('gl_account_number', $utilityGlAccounts);

        if ($fromDate !== null) {
            $query->where('bill_date', '>=', $fromDate);
        }
        if ($toDate !== null) {
            $query->where('bill_date', '<=', $toDate);
        }

        $totalCount = $query->count();

        Log::info('Reprocessing bill details for utility expenses', [
            'total_bill_details' => $totalCount,
            'utility_gl_accounts' => $utilityGlAccounts,
            'from_date' => $fromDate,
            'to_date' => $toDate,
        ]);

        // Process in chunks to manage memory
        $query->chunk(500, function ($billDetails) {
            foreach ($billDetails as $billDetail) {
                try {
                    $this->processBillDetailToUtilityExpense($billDetail);
                } catch (\Exception $e) {
                    $this->errors[] = [
                        'txn_id' => $billDetail->txn_id,
                        'error' => $e->getMessage(),
                    ];
                    Log::error('Failed to reprocess bill detail', [
                        'txn_id' => $billDetail->txn_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });

        $stats = $this->getStats();
        $stats['deleted'] = $deletedCount;

        Log::info('Utility expense reprocessing complete', $stats);

        return $stats;
    }

    /**
     * Get unmatched GL accounts from recent expenses.
     *
     * Returns a list of GL accounts that appear in expenses but are not
     * configured in the utility_accounts table.
     *
     * @param  int  $days  Number of days to look back
     * @return array<string, int> GL account => count of expenses
     */
    public function getUnmatchedAccounts(int $days = 30): array
    {
        $this->loadAccountMappings();

        $expenses = \App\Models\RawAppfolioEvent::where('resource_type', 'expenses')
            ->where('pulled_at', '>=', now()->subDays($days))
            ->get();

        $unmatchedCounts = [];

        foreach ($expenses as $event) {
            $expense = $event->payload_json;
            $glAccount = $this->extractGlAccountNumber($expense);

            if ($glAccount && ! $this->accountMappings->has($glAccount)) {
                $unmatchedCounts[$glAccount] = ($unmatchedCounts[$glAccount] ?? 0) + 1;
            }
        }

        // Sort by count descending
        arsort($unmatchedCounts);

        return $unmatchedCounts;
    }
}
