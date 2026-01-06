<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\UtilityAccount;
use App\Models\UtilityExpense;
use App\Services\UtilityAnalyticsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UtilityDashboardController extends Controller
{
    private const VALID_PERIODS = ['month', 'last_month', 'last_3_months', 'last_6_months', 'last_12_months', 'quarter', 'ytd', 'year'];

    public function __construct(
        private readonly UtilityAnalyticsService $analyticsService
    ) {}

    /**
     * Display the utility dashboard overview.
     */
    public function index(Request $request): Response
    {
        $periodType = $request->get('period', 'month');
        if (! in_array($periodType, self::VALID_PERIODS, true)) {
            $periodType = 'month';
        }
        $date = Carbon::now();

        $period = [
            'type' => $periodType,
            'date' => $date,
        ];

        // Get utility types from configured accounts
        $utilityTypeOptions = UtilityAccount::getUtilityTypeOptions();
        $utilityTypes = array_keys($utilityTypeOptions);

        // Calculate summary for each utility type
        $utilitySummary = [];
        foreach ($utilityTypes as $type) {
            $portfolioData = $this->analyticsService->getPortfolioAverage($type, $period);
            $utilitySummary[$type] = [
                'type' => $type,
                'label' => $utilityTypeOptions[$type],
                'total_cost' => $portfolioData['total_cost'],
                'average_per_unit' => $portfolioData['average'],
                'property_count' => $portfolioData['property_count'],
            ];
        }

        // Calculate portfolio totals
        $portfolioTotal = array_sum(array_column($utilitySummary, 'total_cost'));

        // Get anomalies across all utility types
        $anomalies = [];
        foreach ($utilityTypes as $type) {
            $typeAnomalies = $this->analyticsService->getAnomalies($type, $period, 2.0);
            foreach ($typeAnomalies as $anomaly) {
                $anomaly['utility_type'] = $type;
                $anomaly['utility_label'] = $utilityTypeOptions[$type];
                $anomalies[] = $anomaly;
            }
        }
        // Sort by absolute deviation and limit to top 10
        usort($anomalies, fn ($a, $b) => abs($b['deviation']) <=> abs($a['deviation']));
        $anomalies = array_slice($anomalies, 0, 10);

        // Get trend data for the portfolio (last 12 months)
        $trendData = $this->getPortfolioTrend($utilityTypes, 12);

        // Get selected utility type for comparison table (default to first available)
        $selectedUtilityType = $request->get('utility_type', $utilityTypes[0] ?? 'water');
        if (! in_array($selectedUtilityType, $utilityTypes, true)) {
            $selectedUtilityType = $utilityTypes[0] ?? 'water';
        }

        // Get property comparison data for the selected utility type
        $propertyComparison = $this->getPropertyComparisonData($selectedUtilityType);

        return Inertia::render('Utilities/Index', [
            'period' => $periodType,
            'periodLabel' => $this->getPeriodLabel($periodType, $date),
            'utilitySummary' => array_values($utilitySummary),
            'portfolioTotal' => $portfolioTotal,
            'anomalies' => $anomalies,
            'trendData' => $trendData,
            'propertyComparison' => $propertyComparison,
            'selectedUtilityType' => $selectedUtilityType,
            'utilityTypes' => $utilityTypeOptions,
        ]);
    }

    /**
     * Display utility details for a specific property.
     */
    public function show(Request $request, Property $property): Response
    {
        $periodType = $request->get('period', 'month');
        if (! in_array($periodType, self::VALID_PERIODS, true)) {
            $periodType = 'month';
        }
        $date = Carbon::now();

        $period = [
            'type' => $periodType,
            'date' => $date,
        ];

        $utilityTypeOptions = UtilityAccount::getUtilityTypeOptions();
        $utilityTypes = array_keys($utilityTypeOptions);

        // Get cost breakdown for this property
        $costBreakdown = $this->analyticsService->getCostBreakdown($property, $period);

        // Get period comparison for each utility type
        $comparisons = [];
        foreach ($utilityTypes as $type) {
            $comparison = $this->analyticsService->getPeriodComparison($property, $type, $date);
            $portfolioAvg = $this->analyticsService->getPortfolioAverage($type, $period);
            $costPerUnit = $this->analyticsService->getCostPerUnit($property, $type, $period);

            $comparisons[$type] = [
                'type' => $type,
                'label' => $utilityTypeOptions[$type],
                'current_month' => $comparison['current_month'],
                'previous_month' => $comparison['previous_month'],
                'month_change' => $comparison['month_change'],
                'current_quarter' => $comparison['current_quarter'],
                'previous_quarter' => $comparison['previous_quarter'],
                'quarter_change' => $comparison['quarter_change'],
                'current_ytd' => $comparison['current_ytd'],
                'previous_ytd' => $comparison['previous_ytd'],
                'ytd_change' => $comparison['ytd_change'],
                'cost_per_unit' => $costPerUnit,
                'portfolio_avg' => $portfolioAvg['average'],
                'vs_portfolio' => $costPerUnit && $portfolioAvg['average'] > 0
                    ? round((($costPerUnit - $portfolioAvg['average']) / $portfolioAvg['average']) * 100, 1)
                    : null,
            ];
        }

        // Get trend data for this property
        $propertyTrend = [];
        foreach ($utilityTypes as $type) {
            $trend = $this->analyticsService->getTrend($property, $type, 12, 'month');
            $propertyTrend[$type] = $trend;
        }

        // Get recent expenses (eager load utilityAccount to avoid N+1)
        $recentExpenses = UtilityExpense::query()
            ->with('utilityAccount')
            ->forProperty($property->id)
            ->orderByDesc('expense_date')
            ->limit(20)
            ->get()
            ->map(fn ($expense) => [
                'id' => $expense->id,
                'utility_type' => $expense->utility_type,
                'utility_label' => $utilityTypeOptions[$expense->utility_type] ?? $expense->utility_type ?? 'Unknown',
                'amount' => $expense->amount,
                'expense_date' => $expense->expense_date->toDateString(),
                'vendor_name' => $expense->vendor_name,
            ]);

        return Inertia::render('Utilities/Show', [
            'property' => [
                'id' => $property->id,
                'name' => $property->name,
                'unit_count' => $property->unit_count,
                'total_sqft' => $property->total_sqft,
            ],
            'period' => $periodType,
            'periodLabel' => $this->getPeriodLabel($periodType, $date),
            'costBreakdown' => $costBreakdown,
            'comparisons' => array_values($comparisons),
            'propertyTrend' => $propertyTrend,
            'recentExpenses' => $recentExpenses,
            'utilityTypes' => $utilityTypeOptions,
        ]);
    }

    /**
     * Get portfolio trend data across all utility types.
     */
    private function getPortfolioTrend(array $utilityTypes, int $months): array
    {
        $data = [];
        $date = Carbon::now();

        for ($i = $months - 1; $i >= 0; $i--) {
            $periodDate = $date->copy()->subMonths($i);
            $period = [
                'type' => 'month',
                'date' => $periodDate,
            ];

            $row = [
                'period' => $periodDate->format('M Y'),
                'date' => $periodDate->toDateString(),
            ];

            $total = 0;
            foreach ($utilityTypes as $type) {
                $portfolioData = $this->analyticsService->getPortfolioAverage($type, $period);
                $row[$type] = $portfolioData['total_cost'];
                $total += $portfolioData['total_cost'];
            }
            $row['total'] = $total;

            $data[] = $row;
        }

        return $data;
    }

    /**
     * Get property comparison data for a single utility type.
     *
     * Columns:
     * - Current Period (current calendar month)
     * - Previous Period (previous full calendar month)
     * - Previous 3 Months (monthly average)
     * - Previous 12 Months (monthly average)
     * - Avg $/unit (12-month average)
     * - Avg $/sqft (12-month average)
     */
    private function getPropertyComparisonData(string $utilityType): array
    {
        $properties = Property::active()
            ->forUtilityReports()
            ->orderBy('name')
            ->get();

        $now = Carbon::now();

        // Define periods - all based on full calendar months
        $currentMonthPeriod = ['type' => 'month', 'date' => $now];
        $prevMonthPeriod = ['type' => 'month', 'date' => $now->copy()->subMonth()];

        // Previous 3 full months (excluding current month)
        $prev3MonthStart = $now->copy()->subMonths(3)->startOfMonth();
        $prev3MonthEnd = $now->copy()->subMonth()->endOfMonth();

        // Previous 12 full months (excluding current month)
        $prev12MonthStart = $now->copy()->subMonths(12)->startOfMonth();
        $prev12MonthEnd = $now->copy()->subMonth()->endOfMonth();

        $data = [];
        $totals = [
            'current_month' => 0,
            'prev_month' => 0,
            'prev_3_months' => 0,
            'prev_12_months' => 0,
        ];

        foreach ($properties as $property) {
            // Current month cost
            $currentMonth = $this->analyticsService->getTotalCost($property, $utilityType, $currentMonthPeriod);

            // Previous month cost
            $prevMonth = $this->analyticsService->getTotalCost($property, $utilityType, $prevMonthPeriod);

            // Previous 3 months total (for monthly average)
            $prev3Total = (float) UtilityExpense::query()
                ->forProperty($property->id)
                ->ofType($utilityType)
                ->inDateRange($prev3MonthStart, $prev3MonthEnd)
                ->sum('amount');
            $prev3Monthly = $prev3Total > 0 ? round($prev3Total / 3, 2) : null;

            // Previous 12 months total (for monthly average and per-unit/sqft)
            $prev12Total = (float) UtilityExpense::query()
                ->forProperty($property->id)
                ->ofType($utilityType)
                ->inDateRange($prev12MonthStart, $prev12MonthEnd)
                ->sum('amount');
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
     * Get a human-readable period label.
     */
    private function getPeriodLabel(string $periodType, Carbon $date): string
    {
        return match ($periodType) {
            'month' => $date->format('F Y'),
            'last_month' => $date->copy()->subMonth()->format('F Y'),
            'last_3_months' => $date->copy()->subMonths(2)->format('M Y').' - '.$date->format('M Y'),
            'last_6_months' => $date->copy()->subMonths(5)->format('M Y').' - '.$date->format('M Y'),
            'last_12_months' => $date->copy()->subMonths(11)->format('M Y').' - '.$date->format('M Y'),
            'quarter' => 'Q'.$date->quarter.' '.$date->year,
            'year' => (string) $date->year,
            'ytd' => 'YTD '.$date->year,
            default => $date->format('F Y'),
        };
    }
}
