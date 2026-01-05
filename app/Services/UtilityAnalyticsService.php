<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Property;
use App\Models\UtilityExpense;
use Carbon\Carbon;

/**
 * Utility Analytics Service
 *
 * Calculates utility metrics and comparisons for properties and portfolio.
 * Uses adjusted values for unit counts and square footage when adjustments exist.
 * Excludes properties with utility exclusion flags (HOA, tenant pays utilities).
 */
class UtilityAnalyticsService
{
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

        // Current month
        $currentMonth = $this->getTotalCost($property, $utilityType, [
            'type' => 'month',
            'date' => $date,
        ]);

        // Previous month
        $previousMonth = $this->getTotalCost($property, $utilityType, [
            'type' => 'month',
            'date' => $date->copy()->subMonth(),
        ]);

        // Current quarter
        $currentQuarter = $this->getTotalCost($property, $utilityType, [
            'type' => 'quarter',
            'date' => $date,
        ]);

        // Previous quarter
        $previousQuarter = $this->getTotalCost($property, $utilityType, [
            'type' => 'quarter',
            'date' => $date->copy()->subQuarter(),
        ]);

        // Current year to date
        $currentYtd = $this->getTotalCost($property, $utilityType, [
            'type' => 'ytd',
            'date' => $date,
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
        $properties = Property::active()
            ->forUtilityReports()
            ->get();

        $values = [];
        $totalCost = 0;
        $propertyCount = 0;

        foreach ($properties as $property) {
            $cost = $this->getTotalCost($property, $utilityType, $period);

            if ($cost > 0) {
                $totalCost += $cost;
                $propertyCount++;

                if ($metric === 'per_unit') {
                    $value = $this->getCostPerUnit($property, $utilityType, $period);
                } else {
                    $value = $this->getCostPerSqft($property, $utilityType, $period);
                }

                if ($value !== null) {
                    $values[] = $value;
                }
            }
        }

        $count = count($values);
        $average = $count > 0 ? array_sum($values) / $count : 0;
        $median = $this->calculateMedian($values);
        $stdDev = $this->calculateStdDev($values, $average);

        return [
            'average' => round($average, 2),
            'median' => round($median, 2),
            'std_dev' => round($stdDev, 2),
            'total_cost' => $totalCost,
            'property_count' => $propertyCount,
            'data_points' => $count,
            'min' => $count > 0 ? round(min($values), 2) : 0,
            'max' => $count > 0 ? round(max($values), 2) : 0,
        ];
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
        $portfolioStats = $this->getPortfolioAverage($utilityType, $period, $metric);
        $average = $portfolioStats['average'];
        $stdDev = $portfolioStats['std_dev'];

        if ($stdDev <= 0) {
            return [];
        }

        $lowerBound = $average - ($threshold * $stdDev);
        $upperBound = $average + ($threshold * $stdDev);

        $properties = Property::active()
            ->forUtilityReports()
            ->get();

        $anomalies = [];

        foreach ($properties as $property) {
            if ($metric === 'per_unit') {
                $value = $this->getCostPerUnit($property, $utilityType, $period);
            } else {
                $value = $this->getCostPerSqft($property, $utilityType, $period);
            }

            if ($value === null) {
                continue;
            }

            if ($value < $lowerBound || $value > $upperBound) {
                $deviation = ($value - $average) / $stdDev;
                $anomalies[] = [
                    'property_id' => $property->id,
                    'property_name' => $property->name,
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
        $utilityTypes = ['water', 'electric', 'gas', 'garbage', 'sewer', 'other'];
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
            };

            $cost = $this->getTotalCost($property, $utilityType, [
                'type' => $periodType,
                'date' => $periodDate,
            ]);

            $data[] = [
                'period' => $this->formatPeriodLabel($periodDate, $periodType),
                'date' => $periodDate->toDateString(),
                'cost' => $cost,
                'cost_per_unit' => $this->getCostPerUnit($property, $utilityType, [
                    'type' => $periodType,
                    'date' => $periodDate,
                ]),
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
     * Calculate standard deviation of a set of values.
     */
    private function calculateStdDev(array $values, float $mean): float
    {
        if (count($values) < 2) {
            return 0;
        }

        $sumSquaredDiffs = array_reduce($values, function ($carry, $value) use ($mean) {
            return $carry + pow($value - $mean, 2);
        }, 0);

        return sqrt($sumSquaredDiffs / count($values));
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
}
