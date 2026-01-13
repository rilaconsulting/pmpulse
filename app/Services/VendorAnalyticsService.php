<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Vendor;
use App\Models\WorkOrder;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

/**
 * Vendor Analytics Service
 *
 * Calculates vendor performance metrics including work order counts,
 * spend analysis, completion times, and trade-based comparisons.
 * Supports canonical vendor grouping for accurate reporting across
 * duplicate vendor records.
 */
class VendorAnalyticsService
{
    /**
     * Get the work order count for a vendor in a period.
     *
     * @param  Vendor  $vendor  The vendor to analyze
     * @param  array  $period  Period config ['type' => 'month|quarter|year|...', 'date' => Carbon]
     * @param  bool  $includeGroup  Include work orders from canonical vendor group
     * @return int Count of work orders
     */
    public function getWorkOrderCount(Vendor $vendor, array $period, bool $includeGroup = true): int
    {
        [$startDate, $endDate] = $this->getPeriodDates($period);

        $query = WorkOrder::query()
            ->whereBetween('opened_at', [$startDate, $endDate]);

        if ($includeGroup && ! $vendor->isCanonical()) {
            // Include work orders from canonical vendor group
            $query->whereIn('vendor_id', $vendor->getAllGroupVendorIds());
        } else {
            $query->where('vendor_id', $vendor->id);
        }

        return $query->count();
    }

    /**
     * Get the total spend for a vendor in a period.
     *
     * @param  Vendor  $vendor  The vendor to analyze
     * @param  array  $period  Period config
     * @param  bool  $includeGroup  Include spend from canonical vendor group
     * @return float Total spend amount
     */
    public function getTotalSpend(Vendor $vendor, array $period, bool $includeGroup = true): float
    {
        [$startDate, $endDate] = $this->getPeriodDates($period);

        $query = WorkOrder::query()
            ->whereBetween('opened_at', [$startDate, $endDate]);

        if ($includeGroup && ! $vendor->isCanonical()) {
            $query->whereIn('vendor_id', $vendor->getAllGroupVendorIds());
        } else {
            $query->where('vendor_id', $vendor->id);
        }

        return (float) $query->sum('amount');
    }

    /**
     * Get the average cost per work order for a vendor in a period.
     *
     * @param  Vendor  $vendor  The vendor to analyze
     * @param  array  $period  Period config
     * @param  bool  $includeGroup  Include data from canonical vendor group
     * @return float|null Average cost per work order, or null if no work orders
     */
    public function getAverageCostPerWO(Vendor $vendor, array $period, bool $includeGroup = true): ?float
    {
        [$startDate, $endDate] = $this->getPeriodDates($period);

        $query = WorkOrder::query()
            ->whereBetween('opened_at', [$startDate, $endDate])
            ->whereNotNull('amount')
            ->where('amount', '>', 0);

        if ($includeGroup && ! $vendor->isCanonical()) {
            $query->whereIn('vendor_id', $vendor->getAllGroupVendorIds());
        } else {
            $query->where('vendor_id', $vendor->id);
        }

        $result = $query->selectRaw('AVG(amount) as avg_cost, COUNT(*) as count')->first();

        if (! $result || $result->count === 0) {
            return null;
        }

        return round((float) $result->avg_cost, 2);
    }

    /**
     * Get the average completion time for a vendor in a period.
     *
     * Calculates the average number of days from work order opened to closed.
     *
     * @param  Vendor  $vendor  The vendor to analyze
     * @param  array  $period  Period config
     * @param  bool  $includeGroup  Include data from canonical vendor group
     * @return float|null Average days to complete, or null if no completed work orders
     */
    public function getAverageCompletionTime(Vendor $vendor, array $period, bool $includeGroup = true): ?float
    {
        [$startDate, $endDate] = $this->getPeriodDates($period);

        $query = WorkOrder::query()
            ->whereBetween('opened_at', [$startDate, $endDate])
            ->whereNotNull('closed_at')
            ->whereIn('status', ['completed', 'cancelled']);

        if ($includeGroup && ! $vendor->isCanonical()) {
            $query->whereIn('vendor_id', $vendor->getAllGroupVendorIds());
        } else {
            $query->where('vendor_id', $vendor->id);
        }

        // Calculate days difference using database functions
        $result = $query->selectRaw('
            AVG(EXTRACT(EPOCH FROM (closed_at - opened_at)) / 86400) as avg_days,
            COUNT(*) as count
        ')->first();

        if (! $result || $result->count === 0) {
            return null;
        }

        return round((float) $result->avg_days, 1);
    }

    /**
     * Get all vendors in a specific trade category.
     *
     * @param  string  $trade  The trade to filter by (e.g., 'Plumbing', 'HVAC')
     * @param  bool  $activeOnly  Only include active vendors
     * @param  bool  $canonicalOnly  Only include canonical vendors (not duplicates)
     * @return Collection<Vendor> Vendors in the specified trade
     */
    public function getVendorsByTrade(string $trade, bool $activeOnly = true, bool $canonicalOnly = true): Collection
    {
        $query = Vendor::query()
            ->where('vendor_trades', 'ILIKE', '%'.$trade.'%');

        if ($activeOnly) {
            $query->active()->usable();
        }

        if ($canonicalOnly) {
            $query->canonical();
        }

        return $query->orderBy('company_name')->get();
    }

    /**
     * Get top vendors by a specific metric.
     *
     * @param  string  $metric  The metric to rank by ('work_order_count', 'total_spend', 'avg_cost', 'avg_completion_time')
     * @param  int  $limit  Maximum number of vendors to return
     * @param  array  $period  Period config
     * @param  bool  $ascending  Sort ascending (for metrics where lower is better)
     * @return array Top vendors with their metric values
     */
    public function getTopVendors(string $metric, int $limit, array $period, bool $ascending = false): array
    {
        [$startDate, $endDate] = $this->getPeriodDates($period);

        // Get all canonical vendors with work orders in the period
        $vendorIds = WorkOrder::query()
            ->whereBetween('opened_at', [$startDate, $endDate])
            ->whereNotNull('vendor_id')
            ->distinct()
            ->pluck('vendor_id');

        // Get canonical vendors (or all if no canonical relationship)
        $vendors = Vendor::query()
            ->whereIn('id', $vendorIds)
            ->canonical()
            ->active()
            ->usable()
            ->get();

        $results = [];

        foreach ($vendors as $vendor) {
            $value = match ($metric) {
                'work_order_count' => $this->getWorkOrderCount($vendor, $period),
                'total_spend' => $this->getTotalSpend($vendor, $period),
                'avg_cost' => $this->getAverageCostPerWO($vendor, $period),
                'avg_completion_time' => $this->getAverageCompletionTime($vendor, $period),
                default => throw new \InvalidArgumentException("Invalid metric: {$metric}"),
            };

            if ($value !== null) {
                $results[] = [
                    'vendor_id' => $vendor->id,
                    'company_name' => $vendor->company_name,
                    'vendor_trades' => $vendor->vendor_trades,
                    'value' => $value,
                ];
            }
        }

        // Sort by value
        usort($results, function ($a, $b) use ($ascending) {
            return $ascending
                ? $a['value'] <=> $b['value']
                : $b['value'] <=> $a['value'];
        });

        return array_slice($results, 0, $limit);
    }

    /**
     * Get comprehensive vendor metrics summary.
     *
     * @param  Vendor  $vendor  The vendor to analyze
     * @param  array  $period  Period config
     * @return array Summary of all vendor metrics
     */
    public function getVendorSummary(Vendor $vendor, array $period): array
    {
        return [
            'vendor_id' => $vendor->id,
            'company_name' => $vendor->company_name,
            'is_canonical' => $vendor->isCanonical(),
            'duplicate_count' => $vendor->isCanonical() ? $vendor->duplicateVendors()->count() : 0,
            'work_order_count' => $this->getWorkOrderCount($vendor, $period),
            'total_spend' => $this->getTotalSpend($vendor, $period),
            'avg_cost_per_wo' => $this->getAverageCostPerWO($vendor, $period),
            'avg_completion_time' => $this->getAverageCompletionTime($vendor, $period),
            'period' => [
                'type' => $period['type'] ?? 'month',
                'start' => $this->getPeriodDates($period)[0]->toDateString(),
                'end' => $this->getPeriodDates($period)[1]->toDateString(),
            ],
        ];
    }

    /**
     * Get portfolio-wide vendor statistics.
     *
     * @param  array  $period  Period config
     * @return array Portfolio statistics
     */
    public function getPortfolioStats(array $period): array
    {
        [$startDate, $endDate] = $this->getPeriodDates($period);

        // Get aggregated stats
        $stats = WorkOrder::query()
            ->whereBetween('opened_at', [$startDate, $endDate])
            ->whereNotNull('vendor_id')
            ->selectRaw('
                COUNT(*) as total_work_orders,
                COUNT(DISTINCT vendor_id) as unique_vendors,
                SUM(amount) as total_spend,
                AVG(amount) as avg_cost
            ')
            ->first();

        // Get completion time stats
        $completionStats = WorkOrder::query()
            ->whereBetween('opened_at', [$startDate, $endDate])
            ->whereNotNull('vendor_id')
            ->whereNotNull('closed_at')
            ->whereIn('status', ['completed', 'cancelled'])
            ->selectRaw('
                AVG(EXTRACT(EPOCH FROM (closed_at - opened_at)) / 86400) as avg_days,
                COUNT(*) as completed_count
            ')
            ->first();

        return [
            'total_work_orders' => (int) ($stats->total_work_orders ?? 0),
            'unique_vendors' => (int) ($stats->unique_vendors ?? 0),
            'total_spend' => round((float) ($stats->total_spend ?? 0), 2),
            'avg_cost_per_wo' => round((float) ($stats->avg_cost ?? 0), 2),
            'avg_completion_days' => $completionStats->completed_count > 0
                ? round((float) ($completionStats->avg_days ?? 0), 1)
                : null,
            'completed_work_orders' => (int) ($completionStats->completed_count ?? 0),
            'period' => [
                'type' => $period['type'] ?? 'month',
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
        ];
    }

    /**
     * Get period-over-period comparison for a vendor.
     *
     * Compares current period vs previous period of the same type.
     *
     * @param  Vendor  $vendor  The vendor to analyze
     * @param  Carbon|null  $referenceDate  The reference date (defaults to today)
     * @return array Comparison data with current and previous period metrics
     */
    public function getPeriodComparison(Vendor $vendor, ?Carbon $referenceDate = null): array
    {
        $date = $referenceDate ?? now();

        // Last 30 days vs previous 30 days
        $current30 = $this->getMetricsForPeriod($vendor, ['type' => 'last_30_days', 'date' => $date]);
        $previous30 = $this->getMetricsForPeriod($vendor, ['type' => 'last_30_days', 'date' => $date->copy()->subDays(30)]);

        // Last 90 days vs previous 90 days
        $current90 = $this->getMetricsForPeriod($vendor, ['type' => 'last_90_days', 'date' => $date]);
        $previous90 = $this->getMetricsForPeriod($vendor, ['type' => 'last_90_days', 'date' => $date->copy()->subDays(90)]);

        // Last 12 months vs previous 12 months
        $current12m = $this->getMetricsForPeriod($vendor, ['type' => 'last_12_months', 'date' => $date]);
        $previous12m = $this->getMetricsForPeriod($vendor, ['type' => 'last_12_months', 'date' => $date->copy()->subYear()]);

        // Year to date vs previous year same period
        $currentYtd = $this->getMetricsForPeriod($vendor, ['type' => 'ytd', 'date' => $date]);
        $previousYtd = $this->getMetricsForPeriod($vendor, ['type' => 'ytd', 'date' => $date->copy()->subYear()]);

        return [
            'last_30_days' => [
                'current' => $current30,
                'previous' => $previous30,
                'changes' => $this->calculateMetricChanges($previous30, $current30),
            ],
            'last_90_days' => [
                'current' => $current90,
                'previous' => $previous90,
                'changes' => $this->calculateMetricChanges($previous90, $current90),
            ],
            'last_12_months' => [
                'current' => $current12m,
                'previous' => $previous12m,
                'changes' => $this->calculateMetricChanges($previous12m, $current12m),
            ],
            'year_to_date' => [
                'current' => $currentYtd,
                'previous' => $previousYtd,
                'changes' => $this->calculateMetricChanges($previousYtd, $currentYtd),
            ],
        ];
    }

    /**
     * Get metrics for a specific period.
     *
     * @param  Vendor  $vendor  The vendor to analyze
     * @param  array  $period  Period config
     * @return array Metrics for the period
     */
    private function getMetricsForPeriod(Vendor $vendor, array $period): array
    {
        return [
            'work_order_count' => $this->getWorkOrderCount($vendor, $period),
            'total_spend' => $this->getTotalSpend($vendor, $period),
            'avg_cost_per_wo' => $this->getAverageCostPerWO($vendor, $period),
            'avg_completion_time' => $this->getAverageCompletionTime($vendor, $period),
        ];
    }

    /**
     * Calculate percent changes between two metric sets.
     *
     * @param  array  $previous  Previous period metrics
     * @param  array  $current  Current period metrics
     * @return array Percent changes for each metric
     */
    private function calculateMetricChanges(array $previous, array $current): array
    {
        return [
            'work_order_count' => $this->calculatePercentChange(
                $previous['work_order_count'],
                $current['work_order_count']
            ),
            'total_spend' => $this->calculatePercentChange(
                $previous['total_spend'],
                $current['total_spend']
            ),
            'avg_cost_per_wo' => $this->calculatePercentChange(
                $previous['avg_cost_per_wo'] ?? 0,
                $current['avg_cost_per_wo'] ?? 0
            ),
            'avg_completion_time' => $this->calculatePercentChange(
                $previous['avg_completion_time'] ?? 0,
                $current['avg_completion_time'] ?? 0
            ),
        ];
    }

    /**
     * Get vendor performance trend over multiple periods.
     *
     * @param  Vendor  $vendor  The vendor to analyze
     * @param  int  $periods  Number of periods to include
     * @param  string  $periodType  Period type ('month', 'quarter', 'year')
     * @return array Trend data including direction indicators
     */
    public function getVendorTrend(Vendor $vendor, int $periods = 12, string $periodType = 'month'): array
    {
        $data = [];
        $date = now();

        for ($i = $periods - 1; $i >= 0; $i--) {
            $periodDate = match ($periodType) {
                'month' => $date->copy()->subMonths($i),
                'quarter' => $date->copy()->subQuarters($i),
                'year' => $date->copy()->subYears($i),
                default => throw new \InvalidArgumentException("Invalid period type: {$periodType}"),
            };

            $period = ['type' => $periodType, 'date' => $periodDate];

            $data[] = [
                'period' => $this->formatPeriodLabel($periodDate, $periodType),
                'date' => $periodDate->toDateString(),
                'work_order_count' => $this->getWorkOrderCount($vendor, $period),
                'total_spend' => $this->getTotalSpend($vendor, $period),
                'avg_cost_per_wo' => $this->getAverageCostPerWO($vendor, $period),
                'avg_completion_time' => $this->getAverageCompletionTime($vendor, $period),
            ];
        }

        // Calculate trend direction for each metric
        $trends = $this->detectTrends($data);

        return [
            'data' => $data,
            'trends' => $trends,
            'period_type' => $periodType,
            'periods' => $periods,
        ];
    }

    /**
     * Detect trend direction for metrics over time.
     *
     * @param  array  $data  Time series data
     * @return array Trend indicators for each metric
     */
    private function detectTrends(array $data): array
    {
        if (count($data) < 3) {
            return [
                'work_order_count' => 'insufficient_data',
                'total_spend' => 'insufficient_data',
                'avg_cost_per_wo' => 'insufficient_data',
                'avg_completion_time' => 'insufficient_data',
            ];
        }

        $metrics = ['work_order_count', 'total_spend', 'avg_cost_per_wo', 'avg_completion_time'];
        $trends = [];

        foreach ($metrics as $metric) {
            $values = array_filter(array_column($data, $metric), fn ($v) => $v !== null);

            if (count($values) < 3) {
                $trends[$metric] = 'insufficient_data';

                continue;
            }

            // Compare first half average to second half average
            $midpoint = (int) floor(count($values) / 2);
            $firstHalf = array_slice($values, 0, $midpoint);
            $secondHalf = array_slice($values, $midpoint);

            $firstAvg = array_sum($firstHalf) / count($firstHalf);
            $secondAvg = array_sum($secondHalf) / count($secondHalf);

            if ($firstAvg == 0) {
                $trends[$metric] = $secondAvg > 0 ? 'increasing' : 'stable';
            } else {
                $changePercent = (($secondAvg - $firstAvg) / $firstAvg) * 100;

                $trends[$metric] = match (true) {
                    $changePercent > 10 => 'increasing',
                    $changePercent < -10 => 'decreasing',
                    default => 'stable',
                };
            }
        }

        return $trends;
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

    // ============================================================
    // Trade-Based Analysis Methods (PMP-85)
    // ============================================================

    /**
     * Get all unique trades from the vendor database.
     *
     * @param  bool  $activeOnly  Only include trades from active vendors
     * @param  bool  $canonicalOnly  Only include trades from canonical vendors
     * @return array List of unique trade names
     */
    public function getAllTrades(bool $activeOnly = true, bool $canonicalOnly = true): array
    {
        $query = Vendor::query()
            ->whereNotNull('vendor_trades')
            ->where('vendor_trades', '!=', '');

        if ($activeOnly) {
            $query->active()->usable();
        }

        if ($canonicalOnly) {
            $query->canonical();
        }

        $allTrades = $query->pluck('vendor_trades')
            ->flatMap(fn ($trades) => $this->parseTrades($trades))
            ->unique()
            ->sort()
            ->values()
            ->toArray();

        return $allTrades;
    }

    /**
     * Parse a vendor_trades string into individual trade names.
     *
     * @param  string|null  $trades  Comma-separated trade string
     * @return array Array of individual trade names
     */
    public function parseTrades(?string $trades): array
    {
        if (empty($trades)) {
            return [];
        }

        return array_map('trim', explode(',', $trades));
    }

    /**
     * Get the primary trade for a vendor.
     *
     * Returns the first trade in the vendor's trade list.
     *
     * @param  Vendor  $vendor  The vendor
     * @return string|null Primary trade name or null if no trades
     */
    public function getPrimaryTrade(Vendor $vendor): ?string
    {
        $trades = $this->parseTrades($vendor->vendor_trades);

        return $trades[0] ?? null;
    }

    /**
     * Group vendors by their primary trade.
     *
     * @param  bool  $activeOnly  Only include active vendors
     * @param  bool  $canonicalOnly  Only include canonical vendors
     * @return array Trade name => array of vendors
     */
    public function getVendorsGroupedByTrade(bool $activeOnly = true, bool $canonicalOnly = true): array
    {
        $query = Vendor::query()
            ->whereNotNull('vendor_trades')
            ->where('vendor_trades', '!=', '');

        if ($activeOnly) {
            $query->active()->usable();
        }

        if ($canonicalOnly) {
            $query->canonical();
        }

        $vendors = $query->orderBy('company_name')->get();

        $grouped = [];
        foreach ($vendors as $vendor) {
            $primaryTrade = $this->getPrimaryTrade($vendor);
            if ($primaryTrade) {
                $grouped[$primaryTrade][] = $vendor;
            }
        }

        ksort($grouped);

        return $grouped;
    }

    /**
     * Calculate trade-level average metrics.
     *
     * @param  string  $trade  The trade to analyze
     * @param  array  $period  Period config
     * @return array Trade averages for all metrics
     */
    public function getTradeAverages(string $trade, array $period): array
    {
        $vendors = $this->getVendorsByTrade($trade);

        if ($vendors->isEmpty()) {
            return [
                'trade' => $trade,
                'vendor_count' => 0,
                'avg_work_order_count' => null,
                'avg_total_spend' => null,
                'avg_cost_per_wo' => null,
                'avg_completion_time' => null,
                'total_work_orders' => 0,
                'total_spend' => 0.0,
            ];
        }

        $workOrderCounts = [];
        $totalSpends = [];
        $avgCosts = [];
        $completionTimes = [];
        $totalWorkOrders = 0;
        $totalSpend = 0.0;

        foreach ($vendors as $vendor) {
            $woCount = $this->getWorkOrderCount($vendor, $period);
            $spend = $this->getTotalSpend($vendor, $period);
            $avgCost = $this->getAverageCostPerWO($vendor, $period);
            $completionTime = $this->getAverageCompletionTime($vendor, $period);

            $workOrderCounts[] = $woCount;
            $totalSpends[] = $spend;
            $totalWorkOrders += $woCount;
            $totalSpend += $spend;

            if ($avgCost !== null) {
                $avgCosts[] = $avgCost;
            }
            if ($completionTime !== null) {
                $completionTimes[] = $completionTime;
            }
        }

        return [
            'trade' => $trade,
            'vendor_count' => $vendors->count(),
            'avg_work_order_count' => round(array_sum($workOrderCounts) / count($workOrderCounts), 1),
            'avg_total_spend' => round(array_sum($totalSpends) / count($totalSpends), 2),
            'avg_cost_per_wo' => ! empty($avgCosts) ? round(array_sum($avgCosts) / count($avgCosts), 2) : null,
            'avg_completion_time' => ! empty($completionTimes) ? round(array_sum($completionTimes) / count($completionTimes), 1) : null,
            'total_work_orders' => $totalWorkOrders,
            'total_spend' => round($totalSpend, 2),
        ];
    }

    /**
     * Get all trade averages for comparison.
     *
     * @param  array  $period  Period config
     * @return array Trade name => trade averages
     */
    public function getAllTradeAverages(array $period): array
    {
        $trades = $this->getAllTrades();
        $averages = [];

        foreach ($trades as $trade) {
            $averages[$trade] = $this->getTradeAverages($trade, $period);
        }

        return $averages;
    }

    /**
     * Compare a vendor's performance to their trade average.
     *
     * @param  Vendor  $vendor  The vendor to compare
     * @param  array  $period  Period config
     * @return array Comparison data with vendor metrics and trade averages
     */
    public function compareVendorToTradeAverage(Vendor $vendor, array $period): array
    {
        $primaryTrade = $this->getPrimaryTrade($vendor);

        if (! $primaryTrade) {
            return [
                'vendor_id' => $vendor->id,
                'company_name' => $vendor->company_name,
                'trade' => null,
                'has_trade' => false,
                'vendor_metrics' => null,
                'trade_averages' => null,
                'comparison' => null,
            ];
        }

        $vendorMetrics = [
            'work_order_count' => $this->getWorkOrderCount($vendor, $period),
            'total_spend' => $this->getTotalSpend($vendor, $period),
            'avg_cost_per_wo' => $this->getAverageCostPerWO($vendor, $period),
            'avg_completion_time' => $this->getAverageCompletionTime($vendor, $period),
        ];

        $tradeAverages = $this->getTradeAverages($primaryTrade, $period);

        // Calculate percent difference from trade average
        $comparison = [
            'work_order_count' => $this->calculatePercentDifference(
                $tradeAverages['avg_work_order_count'],
                $vendorMetrics['work_order_count']
            ),
            'total_spend' => $this->calculatePercentDifference(
                $tradeAverages['avg_total_spend'],
                $vendorMetrics['total_spend']
            ),
            'avg_cost_per_wo' => $this->calculatePercentDifference(
                $tradeAverages['avg_cost_per_wo'],
                $vendorMetrics['avg_cost_per_wo']
            ),
            'avg_completion_time' => $this->calculatePercentDifference(
                $tradeAverages['avg_completion_time'],
                $vendorMetrics['avg_completion_time']
            ),
        ];

        return [
            'vendor_id' => $vendor->id,
            'company_name' => $vendor->company_name,
            'trade' => $primaryTrade,
            'has_trade' => true,
            'vendor_metrics' => $vendorMetrics,
            'trade_averages' => $tradeAverages,
            'comparison' => $comparison,
        ];
    }

    /**
     * Rank vendors within a trade by a specific metric.
     *
     * @param  string  $trade  The trade to rank within
     * @param  string  $metric  Metric to rank by
     * @param  array  $period  Period config
     * @param  bool  $ascending  Sort ascending (for metrics where lower is better)
     * @return array Ranked vendors with their metrics and rank position
     */
    public function rankVendorsInTrade(string $trade, string $metric, array $period, bool $ascending = false): array
    {
        $vendors = $this->getVendorsByTrade($trade);

        if ($vendors->isEmpty()) {
            return [];
        }

        $ranked = [];
        foreach ($vendors as $vendor) {
            $value = match ($metric) {
                'work_order_count' => $this->getWorkOrderCount($vendor, $period),
                'total_spend' => $this->getTotalSpend($vendor, $period),
                'avg_cost' => $this->getAverageCostPerWO($vendor, $period),
                'avg_completion_time' => $this->getAverageCompletionTime($vendor, $period),
                default => throw new \InvalidArgumentException("Invalid metric: {$metric}"),
            };

            $ranked[] = [
                'vendor_id' => $vendor->id,
                'company_name' => $vendor->company_name,
                'value' => $value,
            ];
        }

        // Sort by value (filter out nulls for ranking)
        usort($ranked, function ($a, $b) use ($ascending) {
            if ($a['value'] === null) {
                return 1;
            }
            if ($b['value'] === null) {
                return -1;
            }

            return $ascending
                ? $a['value'] <=> $b['value']
                : $b['value'] <=> $a['value'];
        });

        // Add rank position
        $totalWithValues = count(array_filter($ranked, fn ($r) => $r['value'] !== null));
        foreach ($ranked as $index => &$item) {
            $item['rank'] = $item['value'] !== null ? $index + 1 : null;
            $item['total_in_trade'] = $totalWithValues;
            $item['percentile'] = $item['value'] !== null && $totalWithValues > 0
                ? round((($totalWithValues - $item['rank'] + 1) / $totalWithValues) * 100, 1)
                : null;
        }

        return $ranked;
    }

    /**
     * Get comprehensive trade analysis for a vendor.
     *
     * Includes comparison to trade average and rank within trade.
     *
     * @param  Vendor  $vendor  The vendor to analyze
     * @param  array  $period  Period config
     * @return array Complete trade analysis
     */
    public function getVendorTradeAnalysis(Vendor $vendor, array $period): array
    {
        $primaryTrade = $this->getPrimaryTrade($vendor);
        $allTrades = $this->parseTrades($vendor->vendor_trades);

        $comparison = $this->compareVendorToTradeAverage($vendor, $period);

        if (! $primaryTrade) {
            return [
                'vendor_id' => $vendor->id,
                'company_name' => $vendor->company_name,
                'primary_trade' => null,
                'all_trades' => $allTrades,
                'comparison' => $comparison,
                'rankings' => null,
            ];
        }

        // Get rankings for each metric
        $rankings = [
            'work_order_count' => $this->findVendorRank(
                $this->rankVendorsInTrade($primaryTrade, 'work_order_count', $period),
                $vendor->id
            ),
            'total_spend' => $this->findVendorRank(
                $this->rankVendorsInTrade($primaryTrade, 'total_spend', $period),
                $vendor->id
            ),
            'avg_cost' => $this->findVendorRank(
                $this->rankVendorsInTrade($primaryTrade, 'avg_cost', $period, true),
                $vendor->id
            ),
            'avg_completion_time' => $this->findVendorRank(
                $this->rankVendorsInTrade($primaryTrade, 'avg_completion_time', $period, true),
                $vendor->id
            ),
        ];

        return [
            'vendor_id' => $vendor->id,
            'company_name' => $vendor->company_name,
            'primary_trade' => $primaryTrade,
            'all_trades' => $allTrades,
            'comparison' => $comparison,
            'rankings' => $rankings,
        ];
    }

    /**
     * Get trade summary statistics.
     *
     * @param  array  $period  Period config
     * @return array Summary of all trades with vendor counts and metrics
     */
    public function getTradeSummary(array $period): array
    {
        $trades = $this->getAllTrades();
        $summary = [];

        foreach ($trades as $trade) {
            $averages = $this->getTradeAverages($trade, $period);

            $summary[] = [
                'trade' => $trade,
                'vendor_count' => $averages['vendor_count'],
                'total_work_orders' => $averages['total_work_orders'],
                'total_spend' => $averages['total_spend'],
                'avg_work_order_count' => $averages['avg_work_order_count'],
                'avg_cost_per_wo' => $averages['avg_cost_per_wo'],
                'avg_completion_time' => $averages['avg_completion_time'],
            ];
        }

        // Sort by total work orders descending
        usort($summary, fn ($a, $b) => $b['total_work_orders'] <=> $a['total_work_orders']);

        return $summary;
    }

    /**
     * Find a vendor's rank info in a ranked list.
     *
     * @param  array  $rankedList  Result from rankVendorsInTrade
     * @param  string  $vendorId  The vendor ID to find
     * @return array|null Rank info or null if not found
     */
    private function findVendorRank(array $rankedList, string $vendorId): ?array
    {
        foreach ($rankedList as $item) {
            if ($item['vendor_id'] === $vendorId) {
                return [
                    'rank' => $item['rank'],
                    'total' => $item['total_in_trade'],
                    'percentile' => $item['percentile'],
                    'value' => $item['value'],
                ];
            }
        }

        return null;
    }

    /**
     * Calculate percent difference from a baseline.
     *
     * @param  float|null  $baseline  The baseline value
     * @param  float|null  $actual  The actual value
     * @return array|null Difference info or null if can't calculate
     */
    private function calculatePercentDifference(?float $baseline, ?float $actual): ?array
    {
        if ($baseline === null || $actual === null || $baseline <= 0) {
            return null;
        }

        $difference = $actual - $baseline;
        $percent = round(($difference / $baseline) * 100, 1);

        return [
            'difference' => round($difference, 2),
            'percent' => $percent,
            'direction' => $percent > 5 ? 'above' : ($percent < -5 ? 'below' : 'average'),
        ];
    }

    // ============================================================
    // Response Time Metrics (PMP-86)
    // ============================================================

    /**
     * Get detailed response time metrics for a vendor.
     *
     * @param  Vendor  $vendor  The vendor to analyze
     * @param  array  $period  Period config
     * @param  bool  $includeGroup  Include data from canonical vendor group
     * @return array Response time metrics
     */
    public function getResponseTimeMetrics(Vendor $vendor, array $period, bool $includeGroup = true): array
    {
        [$startDate, $endDate] = $this->getPeriodDates($period);

        $vendorIds = $includeGroup ? $vendor->getAllGroupVendorIds() : [$vendor->id];

        // Get completed work orders for the period
        $completedWOs = WorkOrder::query()
            ->whereIn('vendor_id', $vendorIds)
            ->whereBetween('opened_at', [$startDate, $endDate])
            ->whereNotNull('closed_at')
            ->whereIn('status', ['completed', 'cancelled'])
            ->selectRaw('
                EXTRACT(EPOCH FROM (closed_at - opened_at)) / 86400 as days_to_complete,
                priority,
                status
            ')
            ->get();

        if ($completedWOs->isEmpty()) {
            return [
                'total_completed' => 0,
                'avg_days_to_complete' => null,
                'median_days_to_complete' => null,
                'min_days_to_complete' => null,
                'max_days_to_complete' => null,
                'by_priority' => [],
                'completion_buckets' => $this->getEmptyCompletionBuckets(),
            ];
        }

        $completionDays = $completedWOs
            ->pluck('days_to_complete')
            ->map(fn ($d) => round((float) $d, 1))
            ->sort()
            ->values()
            ->toArray();

        // Calculate overall metrics
        $avg = round(array_sum($completionDays) / count($completionDays), 1);
        $median = $this->calculateMedian($completionDays);
        $min = min($completionDays);
        $max = max($completionDays);

        // Calculate by priority
        $byPriority = $this->calculateMetricsByPriority($completedWOs);

        // Calculate completion time buckets
        $buckets = $this->calculateCompletionBuckets($completionDays);

        return [
            'total_completed' => count($completionDays),
            'avg_days_to_complete' => $avg,
            'median_days_to_complete' => $median,
            'min_days_to_complete' => $min,
            'max_days_to_complete' => $max,
            'by_priority' => $byPriority,
            'completion_buckets' => $buckets,
        ];
    }

    /**
     * Calculate metrics grouped by priority level.
     *
     * @param  \Illuminate\Support\Collection  $workOrders  Collection of work orders
     * @return array Priority-based metrics
     */
    private function calculateMetricsByPriority($workOrders): array
    {
        $grouped = $workOrders->groupBy('priority');
        $result = [];

        foreach ($grouped as $priority => $wos) {
            if (empty($priority)) {
                $priority = 'unspecified';
            }

            $days = $wos->pluck('days_to_complete')
                ->map(fn ($d) => round((float) $d, 1))
                ->sort()
                ->values()
                ->toArray();

            $result[$priority] = [
                'count' => count($days),
                'avg_days' => count($days) > 0 ? round(array_sum($days) / count($days), 1) : null,
                'median_days' => count($days) > 0 ? $this->calculateMedian($days) : null,
                'min_days' => count($days) > 0 ? min($days) : null,
                'max_days' => count($days) > 0 ? max($days) : null,
            ];
        }

        // Sort by priority (emergency first, then low last)
        $priorityOrder = ['emergency' => 0, 'high' => 1, 'normal' => 2, 'low' => 3, 'unspecified' => 4];
        uksort($result, fn ($a, $b) => ($priorityOrder[$a] ?? 5) <=> ($priorityOrder[$b] ?? 5));

        return $result;
    }

    /**
     * Calculate completion time distribution buckets.
     *
     * @param  array  $completionDays  Array of completion days
     * @return array Bucket counts and percentages
     */
    private function calculateCompletionBuckets(array $completionDays): array
    {
        $buckets = [
            'same_day' => ['label' => 'Same Day', 'min' => 0, 'max' => 1, 'count' => 0],
            '1_3_days' => ['label' => '1-3 Days', 'min' => 1, 'max' => 3, 'count' => 0],
            '4_7_days' => ['label' => '4-7 Days', 'min' => 3, 'max' => 7, 'count' => 0],
            '1_2_weeks' => ['label' => '1-2 Weeks', 'min' => 7, 'max' => 14, 'count' => 0],
            '2_4_weeks' => ['label' => '2-4 Weeks', 'min' => 14, 'max' => 28, 'count' => 0],
            'over_4_weeks' => ['label' => '4+ Weeks', 'min' => 28, 'max' => PHP_INT_MAX, 'count' => 0],
        ];

        $total = count($completionDays);

        foreach ($completionDays as $days) {
            if ($days < 1) {
                $buckets['same_day']['count']++;
            } elseif ($days <= 3) {
                $buckets['1_3_days']['count']++;
            } elseif ($days <= 7) {
                $buckets['4_7_days']['count']++;
            } elseif ($days <= 14) {
                $buckets['1_2_weeks']['count']++;
            } elseif ($days <= 28) {
                $buckets['2_4_weeks']['count']++;
            } else {
                $buckets['over_4_weeks']['count']++;
            }
        }

        // Add percentages
        foreach ($buckets as $key => &$bucket) {
            $bucket['percentage'] = $total > 0 ? round(($bucket['count'] / $total) * 100, 1) : 0;
        }

        return $buckets;
    }

    /**
     * Get empty completion buckets structure.
     */
    private function getEmptyCompletionBuckets(): array
    {
        return [
            'same_day' => ['label' => 'Same Day', 'count' => 0, 'percentage' => 0],
            '1_3_days' => ['label' => '1-3 Days', 'count' => 0, 'percentage' => 0],
            '4_7_days' => ['label' => '4-7 Days', 'count' => 0, 'percentage' => 0],
            '1_2_weeks' => ['label' => '1-2 Weeks', 'count' => 0, 'percentage' => 0],
            '2_4_weeks' => ['label' => '2-4 Weeks', 'count' => 0, 'percentage' => 0],
            'over_4_weeks' => ['label' => '4+ Weeks', 'count' => 0, 'percentage' => 0],
        ];
    }

    /**
     * Compare vendor response times to portfolio average.
     *
     * @param  Vendor  $vendor  The vendor to compare
     * @param  array  $period  Period config
     * @return array Comparison data
     */
    public function compareResponseTimeToPortfolio(Vendor $vendor, array $period): array
    {
        $vendorMetrics = $this->getResponseTimeMetrics($vendor, $period);
        $portfolioMetrics = $this->getPortfolioResponseTimeMetrics($period);

        $comparison = [
            'avg_days' => $this->calculatePercentDifference(
                $portfolioMetrics['avg_days_to_complete'],
                $vendorMetrics['avg_days_to_complete']
            ),
            'median_days' => $this->calculatePercentDifference(
                $portfolioMetrics['median_days_to_complete'],
                $vendorMetrics['median_days_to_complete']
            ),
        ];

        // For response time, lower is better
        $isFaster = null;
        if ($vendorMetrics['avg_days_to_complete'] !== null && $portfolioMetrics['avg_days_to_complete'] !== null) {
            $isFaster = $vendorMetrics['avg_days_to_complete'] < $portfolioMetrics['avg_days_to_complete'];
        }

        return [
            'vendor_id' => $vendor->id,
            'company_name' => $vendor->company_name,
            'vendor_metrics' => $vendorMetrics,
            'portfolio_metrics' => $portfolioMetrics,
            'comparison' => $comparison,
            'is_faster_than_average' => $isFaster,
        ];
    }

    /**
     * Get portfolio-wide response time metrics.
     *
     * @param  array  $period  Period config
     * @return array Portfolio response time metrics
     */
    public function getPortfolioResponseTimeMetrics(array $period): array
    {
        [$startDate, $endDate] = $this->getPeriodDates($period);

        $completedWOs = WorkOrder::query()
            ->whereNotNull('vendor_id')
            ->whereBetween('opened_at', [$startDate, $endDate])
            ->whereNotNull('closed_at')
            ->whereIn('status', ['completed', 'cancelled'])
            ->selectRaw('
                EXTRACT(EPOCH FROM (closed_at - opened_at)) / 86400 as days_to_complete,
                priority
            ')
            ->get();

        if ($completedWOs->isEmpty()) {
            return [
                'total_completed' => 0,
                'avg_days_to_complete' => null,
                'median_days_to_complete' => null,
                'by_priority' => [],
            ];
        }

        $completionDays = $completedWOs
            ->pluck('days_to_complete')
            ->map(fn ($d) => round((float) $d, 1))
            ->sort()
            ->values()
            ->toArray();

        return [
            'total_completed' => count($completionDays),
            'avg_days_to_complete' => round(array_sum($completionDays) / count($completionDays), 1),
            'median_days_to_complete' => $this->calculateMedian($completionDays),
            'by_priority' => $this->calculateMetricsByPriority($completedWOs),
        ];
    }

    /**
     * Rank vendors by response time (fastest first).
     *
     * @param  array  $period  Period config
     * @param  int  $limit  Maximum vendors to return
     * @param  int  $minWorkOrders  Minimum completed work orders to qualify
     * @return array Ranked vendors with response time metrics
     */
    public function rankVendorsByResponseTime(array $period, int $limit = 10, int $minWorkOrders = 3): array
    {
        [$startDate, $endDate] = $this->getPeriodDates($period);

        // Get all canonical vendors with completed work orders in the period
        $vendorStats = WorkOrder::query()
            ->whereNotNull('vendor_id')
            ->whereBetween('opened_at', [$startDate, $endDate])
            ->whereNotNull('closed_at')
            ->whereIn('status', ['completed', 'cancelled'])
            ->selectRaw('
                vendor_id,
                COUNT(*) as completed_count,
                AVG(EXTRACT(EPOCH FROM (closed_at - opened_at)) / 86400) as avg_days
            ')
            ->groupBy('vendor_id')
            ->having('completed_count', '>=', $minWorkOrders)
            ->orderBy('avg_days')
            ->limit($limit * 2) // Get more than needed to filter out duplicates
            ->get();

        $ranked = [];
        $seen = [];

        foreach ($vendorStats as $stat) {
            $vendor = Vendor::find($stat->vendor_id);
            if (! $vendor) {
                continue;
            }

            // Skip duplicates, use canonical vendor
            $effectiveId = $vendor->getEffectiveVendorId();
            if (isset($seen[$effectiveId])) {
                continue;
            }
            $seen[$effectiveId] = true;

            // Skip non-canonical vendors
            if (! $vendor->isCanonical()) {
                $vendor = $vendor->getCanonicalVendor();
            }

            if (! $vendor->is_active || $vendor->do_not_use) {
                continue;
            }

            $ranked[] = [
                'vendor_id' => $vendor->id,
                'company_name' => $vendor->company_name,
                'vendor_trades' => $vendor->vendor_trades,
                'completed_count' => (int) $stat->completed_count,
                'avg_days_to_complete' => round((float) $stat->avg_days, 1),
            ];

            if (count($ranked) >= $limit) {
                break;
            }
        }

        // Add rank position
        foreach ($ranked as $index => &$item) {
            $item['rank'] = $index + 1;
        }

        return $ranked;
    }

    /**
     * Get response time trend over time for a vendor.
     *
     * @param  Vendor  $vendor  The vendor to analyze
     * @param  int  $periods  Number of periods
     * @param  string  $periodType  Period type (month, quarter)
     * @return array Trend data
     */
    public function getResponseTimeTrend(Vendor $vendor, int $periods = 12, string $periodType = 'month'): array
    {
        $data = [];
        $date = now();

        for ($i = $periods - 1; $i >= 0; $i--) {
            $periodDate = match ($periodType) {
                'month' => $date->copy()->subMonths($i),
                'quarter' => $date->copy()->subQuarters($i),
                default => $date->copy()->subMonths($i),
            };

            $period = ['type' => $periodType, 'date' => $periodDate];
            $metrics = $this->getResponseTimeMetrics($vendor, $period);

            $data[] = [
                'period' => $this->formatPeriodLabel($periodDate, $periodType),
                'date' => $periodDate->toDateString(),
                'completed_count' => $metrics['total_completed'],
                'avg_days_to_complete' => $metrics['avg_days_to_complete'],
                'median_days_to_complete' => $metrics['median_days_to_complete'],
            ];
        }

        // Detect trend direction (for response time, decreasing is good)
        $avgValues = array_filter(array_column($data, 'avg_days_to_complete'));
        $trend = 'insufficient_data';

        if (count($avgValues) >= 3) {
            $midpoint = (int) floor(count($avgValues) / 2);
            $firstHalf = array_slice($avgValues, 0, $midpoint);
            $secondHalf = array_slice($avgValues, $midpoint);

            $firstAvg = array_sum($firstHalf) / count($firstHalf);
            $secondAvg = array_sum($secondHalf) / count($secondHalf);

            if ($firstAvg > 0) {
                $changePercent = (($secondAvg - $firstAvg) / $firstAvg) * 100;
                $trend = match (true) {
                    $changePercent < -10 => 'improving', // Getting faster
                    $changePercent > 10 => 'slowing', // Getting slower
                    default => 'stable',
                };
            }
        }

        return [
            'data' => $data,
            'trend' => $trend,
            'period_type' => $periodType,
        ];
    }

    /**
     * Calculate the median of an array of values.
     *
     * @param  array  $values  Sorted array of values
     * @return float Median value
     */
    private function calculateMedian(array $values): float
    {
        $count = count($values);
        if ($count === 0) {
            return 0;
        }

        $middle = (int) floor($count / 2);

        if ($count % 2 === 0) {
            return round(($values[$middle - 1] + $values[$middle]) / 2, 1);
        }

        return round($values[$middle], 1);
    }

    // ============================================================
    // Period Calculation Methods
    // ============================================================

    /**
     * Get start and end dates for a period.
     *
     * @param  array  $period  Period configuration
     * @return array{Carbon, Carbon} [startDate, endDate]
     */
    private function getPeriodDates(array $period): array
    {
        $date = $period['date'] ?? now();
        $type = $period['type'] ?? 'month';

        return match ($type) {
            'last_30_days' => [
                $date->copy()->subDays(30),
                $date->copy(),
            ],
            'last_90_days' => [
                $date->copy()->subDays(90),
                $date->copy(),
            ],
            'month' => [
                $date->copy()->startOfMonth(),
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
            'last_12_months' => [
                $date->copy()->subMonths(11)->startOfMonth(),
                $date->copy()->endOfMonth(),
            ],
            'ytd' => [
                $date->copy()->startOfYear(),
                $date->copy(),
            ],
            'custom' => [
                Carbon::parse($period['start']),
                Carbon::parse($period['end']),
            ],
            default => [
                $date->copy()->startOfMonth(),
                $date->copy()->endOfMonth(),
            ],
        };
    }
}
