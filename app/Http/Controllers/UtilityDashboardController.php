<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\PropertyFlag;
use App\Models\PropertyUtilityExclusion;
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

        // Get excluded properties info for display
        $excludedProperties = $this->getExcludedPropertiesInfo();

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
            'excludedProperties' => $excludedProperties,
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
     *
     * Uses a single bulk query to fetch all monthly totals instead of
     * N+1 queries per month/utility type combination.
     */
    private function getPortfolioTrend(array $utilityTypes, int $months): array
    {
        $date = Carbon::now();

        // Fetch all trend data in a single query
        $trendData = $this->analyticsService->getPortfolioTrendData($utilityTypes, $months, $date);

        // Group by month for efficient lookup
        $monthlyData = $trendData->groupBy(fn ($item) => Carbon::parse($item->month)->format('Y-m'));

        $data = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $periodDate = $date->copy()->subMonths($i);
            $monthKey = $periodDate->format('Y-m');

            $row = [
                'period' => $periodDate->format('M Y'),
                'date' => $periodDate->toDateString(),
            ];

            // Initialize all utility types to 0
            $total = 0;
            foreach ($utilityTypes as $type) {
                $row[$type] = 0;
            }

            // Fill in actual values from the query result
            $monthItems = $monthlyData->get($monthKey, collect());
            foreach ($monthItems as $item) {
                $row[$item->utility_type] = (float) $item->total;
                $total += (float) $item->total;
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
     *
     * Uses a single bulk query instead of per-property queries for optimal performance.
     */
    private function getPropertyComparisonData(string $utilityType): array
    {
        return $this->analyticsService->getPropertyComparisonDataBulk($utilityType);
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

    /**
     * Get information about properties excluded from utility reports.
     *
     * Returns both flag-based exclusions (all utilities) and utility-specific exclusions.
     */
    private function getExcludedPropertiesInfo(): array
    {
        $utilityTypeOptions = UtilityAccount::getUtilityTypeOptions();

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
            ->with(['property' => function ($query) {
                $query->where('is_active', true);
            }, 'creator'])
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
