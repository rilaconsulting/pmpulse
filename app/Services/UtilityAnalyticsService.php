<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Property;
use App\Models\PropertyFlag;
use App\Models\PropertyUtilityExclusion;
use App\Models\UtilityAccount;
use App\Models\UtilityExpense;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Utility Analytics Service
 *
 * Calculates utility metrics and comparisons for properties and portfolio.
 * Uses adjusted values for unit counts and square footage when adjustments exist.
 * Excludes properties with utility exclusion flags (HOA, tenant pays utilities).
 */
class UtilityAnalyticsService
{
    /**
     * Request-scoped cache for expensive calculations.
     * Cleared automatically at end of request since service is not a singleton.
     */
    private array $cache = [];

    public function __construct(
        private readonly AdjustmentService $adjustmentService
    ) {}

    /**
     * Get utility cost per unit for a property.
     *
     * @param  Property  $property  The property to calculate for
     * @param  string  $utilityType  The utility type (water, electric, gas, etc.)
     * @param  array  $period  Period config ['type' => 'month|quarter|year', 'date' => Carbon]
     * @return float|null Cost per unit, or null if no units
     */
    public function getCostPerUnit(Property $property, string $utilityType, array $period): ?float
    {
        $totalCost = $this->getTotalCost($property, $utilityType, $period);
        $effectiveUnitCount = $this->getEffectiveUnitCount($property, $period['date'] ?? now());

        if ($effectiveUnitCount <= 0) {
            return null;
        }

        return round($totalCost / $effectiveUnitCount, 2);
    }

    /**
     * Get utility cost per square foot for a property.
     *
     * @param  Property  $property  The property to calculate for
     * @param  string  $utilityType  The utility type (water, electric, gas, etc.)
     * @param  array  $period  Period config ['type' => 'month|quarter|year', 'date' => Carbon]
     * @return float|null Cost per sqft, or null if no sqft data
     */
    public function getCostPerSqft(Property $property, string $utilityType, array $period): ?float
    {
        $totalCost = $this->getTotalCost($property, $utilityType, $period);
        $effectiveSqft = $this->getEffectiveSqft($property, $period['date'] ?? now());

        if ($effectiveSqft <= 0) {
            return null;
        }

        return round($totalCost / $effectiveSqft, 4);
    }

    /**
     * Get period comparison for utility costs.
     *
     * Compares current period vs previous period, previous quarter, and previous year.
     *
     * @param  Property  $property  The property to calculate for
     * @param  string  $utilityType  The utility type (water, electric, gas, etc.)
     * @param  Carbon|null  $referenceDate  The reference date (defaults to today)
     * @return array Comparison data with current and previous period costs
     */
    public function getPeriodComparison(Property $property, string $utilityType, ?Carbon $referenceDate = null): array
    {
        $date = $referenceDate ?? now();

        // Current month (use copy() for consistency even though getPeriodDates copies internally)
        $currentMonth = $this->getTotalCost($property, $utilityType, [
            'type' => 'month',
            'date' => $date->copy(),
        ]);

        // Previous month
        $previousMonth = $this->getTotalCost($property, $utilityType, [
            'type' => 'month',
            'date' => $date->copy()->subMonth(),
        ]);

        // Current quarter
        $currentQuarter = $this->getTotalCost($property, $utilityType, [
            'type' => 'quarter',
            'date' => $date->copy(),
        ]);

        // Previous quarter
        $previousQuarter = $this->getTotalCost($property, $utilityType, [
            'type' => 'quarter',
            'date' => $date->copy()->subQuarter(),
        ]);

        // Current year to date
        $currentYtd = $this->getTotalCost($property, $utilityType, [
            'type' => 'ytd',
            'date' => $date->copy(),
        ]);

        // Previous year same period
        $previousYtd = $this->getTotalCost($property, $utilityType, [
            'type' => 'ytd',
            'date' => $date->copy()->subYear(),
        ]);

        return [
            'current_month' => $currentMonth,
            'previous_month' => $previousMonth,
            'month_change' => $this->calculatePercentChange($previousMonth, $currentMonth),
            'current_quarter' => $currentQuarter,
            'previous_quarter' => $previousQuarter,
            'quarter_change' => $this->calculatePercentChange($previousQuarter, $currentQuarter),
            'current_ytd' => $currentYtd,
            'previous_ytd' => $previousYtd,
            'ytd_change' => $this->calculatePercentChange($previousYtd, $currentYtd),
        ];
    }

    /**
     * Get portfolio-wide average utility cost.
     *
     * @param  string  $utilityType  The utility type (water, electric, gas, etc.)
     * @param  array  $period  Period config ['type' => 'month|quarter|year', 'date' => Carbon]
     * @param  string  $metric  The metric to average ('per_unit' or 'per_sqft')
     * @return array Portfolio average data
     */
    public function getPortfolioAverage(string $utilityType, array $period, string $metric = 'per_unit'): array
    {
        $data = $this->computePortfolioData($utilityType, $period, $metric);

        return [
            'average' => round($data['average'], 2),
            'median' => round($data['median'], 2),
            'std_dev' => round($data['std_dev'], 2),
            'total_cost' => $data['total_cost'],
            'property_count' => $data['property_count'],
            'data_points' => $data['data_points'],
            'min' => $data['data_points'] > 0 ? round($data['min'], 2) : 0,
            'max' => $data['data_points'] > 0 ? round($data['max'], 2) : 0,
        ];
    }

    /**
     * Compute portfolio data for utility metrics (shared by getPortfolioAverage and getAnomalies).
     *
     * Results are cached within the request lifecycle to avoid redundant computations.
     *
     * @param  string  $utilityType  The utility type
     * @param  array  $period  Period config
     * @param  string  $metric  The metric ('per_unit' or 'per_sqft')
     * @return array Portfolio data including per-property values
     */
    private function computePortfolioData(string $utilityType, array $period, string $metric): array
    {
        // Generate cache key based on parameters
        $dateStr = ($period['date'] ?? now())->format('Y-m-d');
        $cacheKey = "portfolio:{$utilityType}:{$period['type']}:{$dateStr}:{$metric}";

        // Return cached result if available
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        // Get properties excluded for this specific utility type
        $utilityExcludedIds = PropertyUtilityExclusion::getExcludedPropertyIds($utilityType);

        $properties = Property::active()
            ->forUtilityReports()
            ->when(! empty($utilityExcludedIds), function ($query) use ($utilityExcludedIds) {
                $query->whereNotIn('id', $utilityExcludedIds);
            })
            ->get();

        $values = [];
        $propertyValues = [];
        $totalCost = 0;
        $propertyCount = 0;

        foreach ($properties as $property) {
            $cost = $this->getTotalCost($property, $utilityType, $period);

            if ($cost > 0) {
                $totalCost += $cost;
                $propertyCount++;

                // Calculate per-unit or per-sqft value directly
                $value = null;
                if ($metric === 'per_unit') {
                    $effectiveUnitCount = $this->getEffectiveUnitCount($property, $period['date'] ?? now());
                    if ($effectiveUnitCount > 0) {
                        $value = $cost / $effectiveUnitCount;
                    }
                } else {
                    $effectiveSqft = $this->getEffectiveSqft($property, $period['date'] ?? now());
                    if ($effectiveSqft > 0) {
                        $value = $cost / $effectiveSqft;
                    }
                }

                if ($value !== null) {
                    $values[] = $value;
                    $propertyValues[] = [
                        'property_id' => $property->id,
                        'property_name' => $property->name,
                        'value' => $value,
                    ];
                }
            }
        }

        $count = count($values);
        $average = $count > 0 ? array_sum($values) / $count : 0;

        $result = [
            'average' => $average,
            'median' => $this->calculateMedian($values),
            'std_dev' => $this->calculateStdDev($values, $average),
            'total_cost' => $totalCost,
            'property_count' => $propertyCount,
            'data_points' => $count,
            'min' => $count > 0 ? min($values) : 0,
            'max' => $count > 0 ? max($values) : 0,
            'property_values' => $propertyValues,
        ];

        // Cache the result
        $this->cache[$cacheKey] = $result;

        return $result;
    }

    /**
     * Get properties with anomalous utility costs.
     *
     * Anomalies are defined as values outside a specified number of
     * standard deviations from the portfolio average.
     *
     * @param  string  $utilityType  The utility type (water, electric, gas, etc.)
     * @param  array  $period  Period config ['type' => 'month|quarter|year', 'date' => Carbon]
     * @param  float  $threshold  Number of standard deviations for anomaly detection (default: 2.0)
     * @param  string  $metric  The metric to analyze ('per_unit' or 'per_sqft')
     * @return array List of properties with anomalous values
     */
    public function getAnomalies(string $utilityType, array $period, float $threshold = 2.0, string $metric = 'per_unit'): array
    {
        // Compute portfolio data with per-property values in a single pass
        $portfolioData = $this->computePortfolioData($utilityType, $period, $metric);
        $average = $portfolioData['average'];
        $stdDev = $portfolioData['std_dev'];

        if ($stdDev <= 0) {
            return [];
        }

        $lowerBound = $average - ($threshold * $stdDev);
        $upperBound = $average + ($threshold * $stdDev);

        $anomalies = [];

        // Use the already-computed property values from portfolioData
        foreach ($portfolioData['property_values'] as $propertyData) {
            $value = $propertyData['value'];

            if ($value < $lowerBound || $value > $upperBound) {
                $deviation = ($value - $average) / $stdDev;
                $anomalies[] = [
                    'property_id' => $propertyData['property_id'],
                    'property_name' => $propertyData['property_name'],
                    'value' => round($value, 2),
                    'average' => round($average, 2),
                    'deviation' => round($deviation, 2),
                    'type' => $value > $upperBound ? 'high' : 'low',
                ];
            }
        }

        // Sort by absolute deviation (most anomalous first)
        usort($anomalies, fn ($a, $b) => abs($b['deviation']) <=> abs($a['deviation']));

        return $anomalies;
    }

    /**
     * Get utility cost breakdown by type for a property.
     *
     * @param  Property  $property  The property to analyze
     * @param  array  $period  Period config ['type' => 'month|quarter|year', 'date' => Carbon]
     * @return array Cost breakdown by utility type
     */
    public function getCostBreakdown(Property $property, array $period): array
    {
        $utilityTypes = array_keys(UtilityAccount::getUtilityTypeOptions());
        $breakdown = [];
        $total = 0;

        foreach ($utilityTypes as $type) {
            $cost = $this->getTotalCost($property, $type, $period);
            $breakdown[$type] = $cost;
            $total += $cost;
        }

        // Add percentages
        $result = [];
        foreach ($breakdown as $type => $cost) {
            $result[] = [
                'type' => $type,
                'cost' => $cost,
                'percentage' => $total > 0 ? round(($cost / $total) * 100, 1) : 0,
            ];
        }

        return [
            'breakdown' => $result,
            'total' => $total,
        ];
    }

    /**
     * Get utility trend data for a property over multiple periods.
     *
     * @param  Property  $property  The property to analyze
     * @param  string  $utilityType  The utility type
     * @param  int  $periods  Number of periods to include
     * @param  string  $periodType  Period type ('month', 'quarter', 'year')
     * @return array Trend data
     */
    public function getTrend(Property $property, string $utilityType, int $periods = 12, string $periodType = 'month'): array
    {
        $data = [];
        $date = now();

        for ($i = $periods - 1; $i >= 0; $i--) {
            $periodDate = match ($periodType) {
                'month' => $date->copy()->subMonths($i),
                'quarter' => $date->copy()->subQuarters($i),
                'year' => $date->copy()->subYears($i),
                default => throw new \InvalidArgumentException(
                    sprintf(
                        'Invalid period type "%s". Allowed values are: month, quarter, year.',
                        $periodType
                    )
                ),
            };

            $cost = $this->getTotalCost($property, $utilityType, [
                'type' => $periodType,
                'date' => $periodDate,
            ]);

            // Calculate cost_per_unit directly to avoid redundant getTotalCost call
            $costPerUnit = null;
            $effectiveUnitCount = $this->getEffectiveUnitCount($property, $periodDate);
            if ($effectiveUnitCount > 0) {
                $costPerUnit = round($cost / $effectiveUnitCount, 2);
            }

            $data[] = [
                'period' => $this->formatPeriodLabel($periodDate, $periodType),
                'date' => $periodDate->toDateString(),
                'cost' => $cost,
                'cost_per_unit' => $costPerUnit,
            ];
        }

        return $data;
    }

    /**
     * Get the total utility cost for a property in a period.
     */
    public function getTotalCost(Property $property, string $utilityType, array $period): float
    {
        [$startDate, $endDate] = $this->getPeriodDates($period);

        return (float) UtilityExpense::query()
            ->forProperty($property->id)
            ->ofType($utilityType)
            ->inDateRange($startDate, $endDate)
            ->sum('amount');
    }

    /**
     * Get bulk expense data for multiple properties and utility types in a single query.
     *
     * This method fetches all expense data grouped by property, utility type, and month,
     * replacing the N+1 pattern of calling getTotalCost() in a loop.
     *
     * @param  array  $propertyIds  Array of property UUIDs
     * @param  array  $utilityTypes  Array of utility types (e.g., ['water', 'electric'])
     * @param  Carbon  $startDate  Start date for the date range
     * @param  Carbon  $endDate  End date for the date range
     * @return Collection Grouped by property_id, then utility_type, containing monthly totals
     */
    private function getBulkExpenseData(array $propertyIds, array $utilityTypes, Carbon $startDate, Carbon $endDate): Collection
    {
        if (empty($propertyIds) || empty($utilityTypes)) {
            return collect();
        }

        // Use toBase() to get plain objects and avoid Eloquent accessor conflicts
        return UtilityExpense::query()
            ->select([
                'utility_expenses.property_id',
                'utility_accounts.utility_type',
                DB::raw("DATE_TRUNC('month', expense_date) as month"),
                DB::raw('SUM(amount) as total'),
            ])
            ->join('utility_accounts', 'utility_expenses.utility_account_id', '=', 'utility_accounts.id')
            ->whereIn('utility_expenses.property_id', $propertyIds)
            ->whereIn('utility_accounts.utility_type', $utilityTypes)
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->groupBy('utility_expenses.property_id', 'utility_accounts.utility_type', DB::raw("DATE_TRUNC('month', expense_date)"))
            ->toBase()
            ->get();
    }

    /**
     * Get portfolio trend data using a single aggregated query.
     *
     * Returns monthly totals for each utility type across all properties,
     * replacing the nested loop pattern in getPortfolioTrend().
     *
     * @param  array  $utilityTypes  Array of utility types
     * @param  int  $months  Number of months to include
     * @param  Carbon|null  $referenceDate  Reference date (defaults to now)
     * @return Collection Monthly totals grouped by month and utility type
     */
    public function getPortfolioTrendData(array $utilityTypes, int $months = 12, ?Carbon $referenceDate = null): Collection
    {
        $date = $referenceDate ?? now();
        $endDate = $date->copy()->endOfMonth();
        $startDate = $date->copy()->subMonths($months - 1)->startOfMonth();

        // Get properties that should be included in portfolio calculations
        $propertyIds = Property::active()
            ->forUtilityReports()
            ->pluck('id')
            ->toArray();

        if (empty($propertyIds)) {
            return collect();
        }

        // Get utility-specific exclusions for all utility types
        $exclusionsByType = [];
        foreach ($utilityTypes as $type) {
            $exclusionsByType[$type] = PropertyUtilityExclusion::getExcludedPropertyIds($type);
        }

        // Use toBase() to get plain objects and avoid Eloquent accessor conflicts
        $results = UtilityExpense::query()
            ->select([
                DB::raw("DATE_TRUNC('month', expense_date) as month"),
                'utility_accounts.utility_type',
                'utility_expenses.property_id',
                DB::raw('SUM(amount) as total'),
            ])
            ->join('utility_accounts', 'utility_expenses.utility_account_id', '=', 'utility_accounts.id')
            ->whereIn('utility_expenses.property_id', $propertyIds)
            ->whereIn('utility_accounts.utility_type', $utilityTypes)
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->groupBy(DB::raw("DATE_TRUNC('month', expense_date)"), 'utility_accounts.utility_type', 'utility_expenses.property_id')
            ->orderBy('month')
            ->toBase()
            ->get();

        // Filter out excluded properties per utility type and re-aggregate
        $filtered = $results->reject(function ($item) use ($exclusionsByType) {
            $excludedIds = $exclusionsByType[$item->utility_type] ?? [];

            return in_array($item->property_id, $excludedIds);
        });

        // Re-aggregate by month and utility_type after filtering
        return $filtered->groupBy(fn ($item) => $item->month.'|'.$item->utility_type)
            ->map(function ($group) {
                $first = $group->first();

                return (object) [
                    'month' => $first->month,
                    'utility_type' => $first->utility_type,
                    'total' => $group->sum('total'),
                ];
            })
            ->values();
    }

    /**
     * Get property comparison data using bulk queries.
     *
     * Fetches all expense data for properties across multiple time periods
     * in a minimal number of queries, replacing the per-property loop pattern.
     *
     * @param  string  $utilityType  The utility type to compare
     * @param  Carbon|null  $referenceDate  Reference date (defaults to now)
     * @return array Comparison data with properties, totals, and averages
     */
    public function getPropertyComparisonDataBulk(string $utilityType, ?Carbon $referenceDate = null): array
    {
        $now = $referenceDate ?? now();

        // Get properties excluded for this specific utility type
        $utilityExcludedIds = PropertyUtilityExclusion::getExcludedPropertyIds($utilityType);

        // Get active properties for utility reports, excluding utility-specific exclusions
        $properties = Property::active()
            ->forUtilityReports()
            ->when(! empty($utilityExcludedIds), function ($query) use ($utilityExcludedIds) {
                $query->whereNotIn('id', $utilityExcludedIds);
            })
            ->orderBy('name')
            ->get();

        if ($properties->isEmpty()) {
            return [
                'properties' => [],
                'totals' => ['current_month' => 0, 'prev_month' => 0, 'prev_3_months' => 0, 'prev_12_months' => 0],
                'averages' => ['current_month' => 0, 'prev_month' => 0, 'prev_3_months' => 0, 'prev_12_months' => 0],
                'property_count' => 0,
            ];
        }

        $propertyIds = $properties->pluck('id')->toArray();

        // Define date ranges
        $currentMonthStart = $now->copy()->startOfMonth();
        $currentMonthEnd = $now->copy()->endOfMonth();
        $prevMonthStart = $now->copy()->subMonth()->startOfMonth();
        $prevMonthEnd = $now->copy()->subMonth()->endOfMonth();
        $prev3MonthStart = $now->copy()->subMonths(3)->startOfMonth();
        $prev12MonthStart = $now->copy()->subMonths(12)->startOfMonth();

        // Single query to get all expense data grouped by property and month
        // Use toBase() to get plain objects and avoid Eloquent accessor conflicts
        $expenseData = UtilityExpense::query()
            ->select([
                'utility_expenses.property_id',
                DB::raw("DATE_TRUNC('month', expense_date) as month"),
                DB::raw('SUM(amount) as total'),
            ])
            ->join('utility_accounts', 'utility_expenses.utility_account_id', '=', 'utility_accounts.id')
            ->whereIn('utility_expenses.property_id', $propertyIds)
            ->where('utility_accounts.utility_type', $utilityType)
            ->whereBetween('expense_date', [$prev12MonthStart, $currentMonthEnd])
            ->groupBy('utility_expenses.property_id', DB::raw("DATE_TRUNC('month', expense_date)"))
            ->toBase()
            ->get()
            ->groupBy('property_id');

        $data = [];
        $totals = [
            'current_month' => 0,
            'prev_month' => 0,
            'prev_3_months' => 0,
            'prev_12_months' => 0,
        ];

        foreach ($properties as $property) {
            $propertyExpenses = $expenseData->get($property->id, collect());

            // Calculate costs for each period from the grouped data
            $currentMonth = $this->sumExpensesInPeriod($propertyExpenses, $currentMonthStart, $currentMonthEnd);
            $prevMonth = $this->sumExpensesInPeriod($propertyExpenses, $prevMonthStart, $prevMonthEnd);
            $prev3Total = $this->sumExpensesInPeriod($propertyExpenses, $prev3MonthStart, $prevMonthEnd);
            $prev12Total = $this->sumExpensesInPeriod($propertyExpenses, $prev12MonthStart, $prevMonthEnd);

            $prev3Monthly = $prev3Total > 0 ? round($prev3Total / 3, 2) : null;
            $prev12Monthly = $prev12Total > 0 ? round($prev12Total / 12, 2) : null;

            // Calculate $/unit and $/sqft using 12-month average
            $avgPerUnit = null;
            $avgPerSqft = null;

            if ($prev12Total > 0) {
                $monthlyAvg = $prev12Total / 12;

                if ($property->unit_count && $property->unit_count > 0) {
                    $avgPerUnit = round($monthlyAvg / $property->unit_count, 2);
                }

                if ($property->total_sqft && $property->total_sqft > 0) {
                    $avgPerSqft = round($monthlyAvg / $property->total_sqft, 4);
                }
            }

            $data[] = [
                'property_id' => $property->id,
                'property_name' => $property->name,
                'unit_count' => $property->unit_count,
                'total_sqft' => $property->total_sqft,
                'current_month' => $currentMonth > 0 ? $currentMonth : null,
                'prev_month' => $prevMonth > 0 ? $prevMonth : null,
                'prev_3_months' => $prev3Monthly,
                'prev_12_months' => $prev12Monthly,
                'avg_per_unit' => $avgPerUnit,
                'avg_per_sqft' => $avgPerSqft,
            ];

            // Accumulate totals
            $totals['current_month'] += $currentMonth;
            $totals['prev_month'] += $prevMonth;
            $totals['prev_3_months'] += $prev3Total;
            $totals['prev_12_months'] += $prev12Total;
        }

        // Calculate portfolio averages
        $propertyCount = $properties->count();
        $portfolioAvg = [
            'current_month' => $propertyCount > 0 ? round($totals['current_month'] / $propertyCount, 2) : 0,
            'prev_month' => $propertyCount > 0 ? round($totals['prev_month'] / $propertyCount, 2) : 0,
            'prev_3_months' => $propertyCount > 0 ? round($totals['prev_3_months'] / 3 / $propertyCount, 2) : 0,
            'prev_12_months' => $propertyCount > 0 ? round($totals['prev_12_months'] / 12 / $propertyCount, 2) : 0,
        ];

        return [
            'properties' => $data,
            'totals' => $totals,
            'averages' => $portfolioAvg,
            'property_count' => $propertyCount,
        ];
    }

    /**
     * Sum expenses from a collection within a date period.
     *
     * @param  Collection  $expenses  Collection of expense records with 'month' and 'total' keys
     * @param  Carbon  $startDate  Start of the period
     * @param  Carbon  $endDate  End of the period
     * @return float Total amount
     */
    private function sumExpensesInPeriod(Collection $expenses, Carbon $startDate, Carbon $endDate): float
    {
        // Pre-compute boundaries outside the filter to avoid redundant calculations
        $startMonth = $startDate->copy()->startOfMonth();
        $endMonth = $endDate->copy()->startOfMonth();

        return (float) $expenses
            ->filter(function ($expense) use ($startMonth, $endMonth) {
                $month = Carbon::parse($expense->month);

                return $month->gte($startMonth) && $month->lte($endMonth);
            })
            ->sum('total');
    }

    /**
     * Get optimized portfolio summary data using bulk queries.
     *
     * Computes summary data for all utility types in a single pass,
     * replacing multiple calls to getPortfolioAverage().
     *
     * @param  array  $utilityTypes  Array of utility types
     * @param  array  $period  Period config
     * @return array Summary data keyed by utility type
     */
    public function getPortfolioSummaryBulk(array $utilityTypes, array $period): array
    {
        [$startDate, $endDate] = $this->getPeriodDates($period);

        // Get properties that should be included
        $properties = Property::active()
            ->forUtilityReports()
            ->get();

        if ($properties->isEmpty()) {
            return array_fill_keys($utilityTypes, [
                'total_cost' => 0,
                'average_per_unit' => 0,
                'property_count' => 0,
            ]);
        }

        $propertyIds = $properties->pluck('id')->toArray();

        // Get utility-specific exclusions for all utility types upfront
        $exclusionsByType = [];
        foreach ($utilityTypes as $type) {
            $exclusionsByType[$type] = PropertyUtilityExclusion::getExcludedPropertyIds($type);
        }

        // Single query to get totals by property and utility type
        // Use toBase() to get plain objects and avoid Eloquent accessor conflicts
        $expenseData = UtilityExpense::query()
            ->select([
                'utility_expenses.property_id',
                'utility_accounts.utility_type',
                DB::raw('SUM(amount) as total'),
            ])
            ->join('utility_accounts', 'utility_expenses.utility_account_id', '=', 'utility_accounts.id')
            ->whereIn('utility_expenses.property_id', $propertyIds)
            ->whereIn('utility_accounts.utility_type', $utilityTypes)
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->groupBy('utility_expenses.property_id', 'utility_accounts.utility_type')
            ->toBase()
            ->get()
            ->groupBy('utility_type');

        // Build property lookup for unit counts
        $propertyLookup = $properties->keyBy('id');

        $summary = [];
        foreach ($utilityTypes as $type) {
            $excludedIds = $exclusionsByType[$type] ?? [];
            // Filter out excluded properties for this utility type
            $typeExpenses = $expenseData->get($type, collect())
                ->reject(fn ($expense) => in_array($expense->property_id, $excludedIds));
            $totalCost = (float) $typeExpenses->sum('total');
            $propertyCount = $typeExpenses->count();

            // Calculate average per unit
            $totalUnits = 0;
            foreach ($typeExpenses as $expense) {
                $property = $propertyLookup->get($expense->property_id);
                if ($property && $property->unit_count > 0) {
                    $totalUnits += $property->unit_count;
                }
            }

            $avgPerUnit = $totalUnits > 0 ? round($totalCost / $totalUnits, 2) : 0;

            $summary[$type] = [
                'total_cost' => $totalCost,
                'average_per_unit' => $avgPerUnit,
                'property_count' => $propertyCount,
            ];
        }

        return $summary;
    }

    /**
     * Get the effective unit count for a property (respecting adjustments).
     */
    private function getEffectiveUnitCount(Property $property, Carbon $date): int
    {
        if ($this->adjustmentService->hasAdjustment($property, 'unit_count', $date)) {
            return (int) $this->adjustmentService->getEffectiveValue($property, 'unit_count', $date);
        }

        // Fall back to property unit_count or actual unit count
        return $property->unit_count ?? $property->units()->active()->count();
    }

    /**
     * Get the effective square footage for a property (respecting adjustments).
     */
    private function getEffectiveSqft(Property $property, Carbon $date): int
    {
        if ($this->adjustmentService->hasAdjustment($property, 'total_sqft', $date)) {
            return (int) $this->adjustmentService->getEffectiveValue($property, 'total_sqft', $date);
        }

        return $property->total_sqft ?? 0;
    }

    /**
     * Get start and end dates for a period.
     *
     * @return array{Carbon, Carbon} [startDate, endDate]
     */
    private function getPeriodDates(array $period): array
    {
        $date = $period['date'] ?? now();
        $type = $period['type'] ?? 'month';

        return match ($type) {
            'month' => [
                $date->copy()->startOfMonth(),
                $date->copy()->endOfMonth(),
            ],
            'last_month' => [
                $date->copy()->subMonth()->startOfMonth(),
                $date->copy()->subMonth()->endOfMonth(),
            ],
            'last_3_months' => [
                $date->copy()->subMonths(2)->startOfMonth(),
                $date->copy()->endOfMonth(),
            ],
            'last_6_months' => [
                $date->copy()->subMonths(5)->startOfMonth(),
                $date->copy()->endOfMonth(),
            ],
            'last_12_months' => [
                $date->copy()->subMonths(11)->startOfMonth(),
                $date->copy()->endOfMonth(),
            ],
            'quarter' => [
                $date->copy()->startOfQuarter(),
                $date->copy()->endOfQuarter(),
            ],
            'year' => [
                $date->copy()->startOfYear(),
                $date->copy()->endOfYear(),
            ],
            'ytd' => [
                $date->copy()->startOfYear(),
                $date->copy(),
            ],
            default => [
                $date->copy()->startOfMonth(),
                $date->copy()->endOfMonth(),
            ],
        };
    }

    /**
     * Calculate percent change between two values.
     */
    private function calculatePercentChange(float $previous, float $current): ?float
    {
        if ($previous <= 0) {
            return null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * Calculate median of a set of values.
     */
    private function calculateMedian(array $values): float
    {
        if (empty($values)) {
            return 0;
        }

        sort($values);
        $count = count($values);
        $middle = (int) floor($count / 2);

        if ($count % 2 === 0) {
            return ($values[$middle - 1] + $values[$middle]) / 2;
        }

        return $values[$middle];
    }

    /**
     * Calculate sample standard deviation of a set of values.
     *
     * Uses the sample standard deviation formula (n-1 denominator) for unbiased
     * estimation of the population standard deviation from sample data.
     */
    private function calculateStdDev(array $values, float $mean): float
    {
        if (count($values) < 2) {
            return 0;
        }

        $sumSquaredDiffs = array_reduce($values, function ($carry, $value) use ($mean) {
            return $carry + pow($value - $mean, 2);
        }, 0);

        // Use sample standard deviation (n-1) for unbiased estimate
        return sqrt($sumSquaredDiffs / (count($values) - 1));
    }

    /**
     * Format a period label for display.
     */
    private function formatPeriodLabel(Carbon $date, string $periodType): string
    {
        return match ($periodType) {
            'month' => $date->format('M Y'),
            'quarter' => 'Q'.$date->quarter.' '.$date->year,
            'year' => (string) $date->year,
            default => $date->format('M Y'),
        };
    }

    /**
     * Get information about properties excluded from utility reports.
     *
     * Returns both flag-based exclusions (all utilities) and utility-specific exclusions.
     *
     * @return array{total_count: int, flag_excluded_count: int, utility_excluded_count: int, properties: array}
     */
    public function getExcludedPropertiesInfo(): array
    {
        // Get properties that have any utility exclusion flags (excluded from ALL utilities)
        $flagExcludedProperties = Property::active()
            ->whereHas('flags', function ($query) {
                $query->whereIn('flag_type', PropertyFlag::UTILITY_EXCLUSION_FLAGS);
            })
            ->with(['flags' => function ($query) {
                $query->whereIn('flag_type', PropertyFlag::UTILITY_EXCLUSION_FLAGS);
            }])
            ->orderBy('name')
            ->get()
            ->map(function ($property) {
                $flags = $property->flags->map(fn ($flag) => [
                    'type' => $flag->flag_type,
                    'label' => $flag->flag_label,
                    'reason' => $flag->reason,
                ])->values()->all();

                return [
                    'id' => $property->id,
                    'name' => $property->name,
                    'exclusion_type' => 'all_utilities',
                    'flags' => $flags,
                    'utility_exclusions' => [],
                ];
            });

        // Get utility-specific exclusions (excluded from specific utility types only)
        $utilityExclusions = PropertyUtilityExclusion::query()
            ->with(['property', 'creator'])
            ->whereHas('property', function ($query) {
                $query->where('is_active', true);
            })
            ->get()
            ->groupBy('property_id');

        // Get property IDs that are already fully excluded by flags
        $flagExcludedIds = $flagExcludedProperties->pluck('id')->toArray();

        // Map utility-specific exclusions to properties (excluding those fully excluded by flags)
        $utilityExcludedProperties = $utilityExclusions
            ->filter(fn ($exclusions, $propertyId) => ! in_array($propertyId, $flagExcludedIds))
            ->map(function ($exclusions) {
                $property = $exclusions->first()->property;
                if (! $property) {
                    return null;
                }

                $utilityExclusionsList = $exclusions->map(fn ($exclusion) => [
                    'utility_type' => $exclusion->utility_type,
                    'utility_label' => $exclusion->utility_type_label,
                    'reason' => $exclusion->reason,
                    'created_by' => $exclusion->creator?->name,
                    'created_at' => $exclusion->created_at->toDateString(),
                ])->values()->all();

                return [
                    'id' => $property->id,
                    'name' => $property->name,
                    'exclusion_type' => 'specific_utilities',
                    'flags' => [],
                    'utility_exclusions' => $utilityExclusionsList,
                ];
            })
            ->filter()
            ->values();

        // Combine both lists, sorted by property name
        $allExcluded = $flagExcludedProperties
            ->concat($utilityExcludedProperties)
            ->sortBy('name')
            ->values()
            ->all();

        return [
            'total_count' => count($allExcluded),
            'flag_excluded_count' => $flagExcludedProperties->count(),
            'utility_excluded_count' => $utilityExcludedProperties->count(),
            'properties' => $allExcluded,
        ];
    }
}
