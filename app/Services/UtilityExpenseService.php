<?php

declare(strict_types=1);

namespace App\Services;

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
     * Cache of GL account number => utility type mappings.
     *
     * @var array<string, string>|null
     */
    private ?array $accountMappings = null;

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

        // Check if this GL account maps to a utility type
        $utilityType = $this->accountMappings[$glAccountNumber] ?? null;

        if ($utilityType === null) {
            $this->unmatched++;
            Log::debug('Expense GL account not mapped to utility type', [
                'gl_account' => $glAccountNumber,
                'expense_id' => $expense['expense_id'] ?? 'unknown',
            ]);

            return;
        }

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
        $externalExpenseId = (string) ($expense['expense_id'] ?? $expense['id']);
        $amount = $this->parseAmount($expense['amount'] ?? $expense['total'] ?? 0);
        $expenseDate = $this->parseDate($expense['expense_date'] ?? $expense['bill_date'] ?? $expense['date']);
        $periodStart = $this->parseDate($expense['period_start'] ?? $expense['service_start'] ?? null);
        $periodEnd = $this->parseDate($expense['period_end'] ?? $expense['service_end'] ?? null);
        $vendorName = $expense['vendor_name'] ?? $expense['vendor'] ?? $expense['payee'] ?? null;
        $description = $expense['description'] ?? $expense['memo'] ?? null;

        // Create or update utility expense record
        DB::transaction(function () use (
            $propertyId,
            $utilityType,
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
                    'utility_type' => $utilityType,
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
     * Extract GL account number from expense data.
     *
     * AppFolio expense data may have the GL account in different fields.
     */
    private function extractGlAccountNumber(array $expense): ?string
    {
        // Try various field names that might contain the GL account
        $glAccount = $expense['gl_account_number']
            ?? $expense['gl_account']
            ?? $expense['expense_account']
            ?? $expense['account_number']
            ?? $expense['account']
            ?? null;

        // If it's a nested object, try to extract the account number
        if (is_array($glAccount)) {
            $glAccount = $glAccount['number'] ?? $glAccount['account_number'] ?? null;
        }

        return $glAccount !== null ? (string) $glAccount : null;
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
            $this->accountMappings = UtilityAccount::getActiveAccountMappings();

            Log::info('Loaded utility account mappings', [
                'count' => count($this->accountMappings),
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

            if ($glAccount && ! isset($this->accountMappings[$glAccount])) {
                $unmatchedCounts[$glAccount] = ($unmatchedCounts[$glAccount] ?? 0) + 1;
            }
        }

        // Sort by count descending
        arsort($unmatchedCounts);

        return $unmatchedCounts;
    }
}
