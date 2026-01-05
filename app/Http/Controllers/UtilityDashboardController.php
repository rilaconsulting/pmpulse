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
    private const VALID_PERIODS = ['month', 'quarter', 'ytd', 'year'];

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
        $utilityTypes = array_keys(UtilityAccount::UTILITY_TYPES);

        // Calculate summary for each utility type
        $utilitySummary = [];
        foreach ($utilityTypes as $type) {
            $portfolioData = $this->analyticsService->getPortfolioAverage($type, $period);
            $utilitySummary[$type] = [
                'type' => $type,
                'label' => UtilityAccount::UTILITY_TYPES[$type],
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
                $anomaly['utility_label'] = UtilityAccount::UTILITY_TYPES[$type];
                $anomalies[] = $anomaly;
            }
        }
        // Sort by absolute deviation and limit to top 10
        usort($anomalies, fn ($a, $b) => abs($b['deviation']) <=> abs($a['deviation']));
        $anomalies = array_slice($anomalies, 0, 10);

        // Get trend data for the portfolio (last 12 months)
        $trendData = $this->getPortfolioTrend($utilityTypes, 12);

        // Get heat map data for properties
        $heatMapData = $this->getHeatMapData($utilityTypes, $period);

        return Inertia::render('Utilities/Index', [
            'period' => $periodType,
            'periodLabel' => $this->getPeriodLabel($periodType, $date),
            'utilitySummary' => array_values($utilitySummary),
            'portfolioTotal' => $portfolioTotal,
            'anomalies' => $anomalies,
            'trendData' => $trendData,
            'heatMapData' => $heatMapData,
            'utilityTypes' => UtilityAccount::UTILITY_TYPES,
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

        $utilityTypes = array_keys(UtilityAccount::UTILITY_TYPES);

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
                'label' => UtilityAccount::UTILITY_TYPES[$type],
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

        // Get recent expenses
        $recentExpenses = UtilityExpense::query()
            ->forProperty($property->id)
            ->orderByDesc('expense_date')
            ->limit(20)
            ->get()
            ->map(fn ($expense) => [
                'id' => $expense->id,
                'utility_type' => $expense->utility_type,
                'utility_label' => UtilityAccount::UTILITY_TYPES[$expense->utility_type] ?? $expense->utility_type,
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
            'utilityTypes' => UtilityAccount::UTILITY_TYPES,
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
     * Get heat map data showing each property's utility costs vs portfolio average.
     */
    private function getHeatMapData(array $utilityTypes, array $period): array
    {
        $properties = Property::active()
            ->forUtilityReports()
            ->orderBy('name')
            ->get();

        // Get portfolio averages for each type
        $portfolioAvg = [];
        foreach ($utilityTypes as $type) {
            $avg = $this->analyticsService->getPortfolioAverage($type, $period);
            $portfolioAvg[$type] = $avg['average'];
        }

        $data = [];
        foreach ($properties as $property) {
            $row = [
                'property_id' => $property->id,
                'property_name' => $property->name,
                'unit_count' => $property->unit_count,
            ];

            foreach ($utilityTypes as $type) {
                $costPerUnit = $this->analyticsService->getCostPerUnit($property, $type, $period);
                $avg = $portfolioAvg[$type];

                $row[$type] = [
                    'value' => $costPerUnit,
                    'vs_avg' => $costPerUnit !== null && $avg > 0
                        ? round((($costPerUnit - $avg) / $avg) * 100, 1)
                        : null,
                ];
            }

            $data[] = $row;
        }

        return [
            'properties' => $data,
            'averages' => $portfolioAvg,
        ];
    }

    /**
     * Get a human-readable period label.
     */
    private function getPeriodLabel(string $periodType, Carbon $date): string
    {
        return match ($periodType) {
            'month' => $date->format('F Y'),
            'quarter' => 'Q'.$date->quarter.' '.$date->year,
            'year' => (string) $date->year,
            'ytd' => 'YTD '.$date->year,
            default => $date->format('F Y'),
        };
    }
}
