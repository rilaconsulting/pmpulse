<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Property;
use App\Models\PropertyUtilityExclusion;
use App\Models\UtilityAccount;
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
     * @param  string  $utilityType  The utility type
     * @param  array  $period  Period config
     * @param  string  $metric  The metric ('per_unit' or 'per_sqft')
     * @return array Portfolio data including per-property values
     */
    private function computePortfolioData(string $utilityType, array $period, string $metric): array
    {
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

        return [
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
}
