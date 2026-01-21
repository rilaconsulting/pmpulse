<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UtilityDataRequest;
use App\Models\Property;
use App\Models\UtilityExpense;
use App\Models\UtilityType;
use App\Services\UtilityAnalyticsService;
use App\Services\UtilityFormattingService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UtilityDashboardController extends Controller
{
    private const VALID_PERIODS = ['month', 'last_month', 'last_3_months', 'last_6_months', 'last_12_months', 'quarter', 'ytd', 'year'];

    public function __construct(
        private readonly UtilityAnalyticsService $analyticsService,
        private readonly UtilityFormattingService $formattingService
    ) {}

    /**
     * Redirect to data table view (default landing page).
     */
    public function index(): RedirectResponse
    {
        return redirect()->route('utilities.data');
    }

    /**
     * Display the utility dashboard overview.
     */
    public function dashboard(Request $request): Response
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

        // Get utility types from the database
        $utilityTypeModels = UtilityType::ordered()->get();
        $utilityTypeOptions = $utilityTypeModels->pluck('label', 'key')->toArray();
        $utilityTypes = array_keys($utilityTypeOptions);

        // Calculate summary for each utility type using bulk query
        $utilitySummaryRaw = $this->analyticsService->getPortfolioSummaryBulk($utilityTypes, $period);
        $utilitySummary = [];
        foreach ($utilityTypeModels as $typeModel) {
            $type = $typeModel->key;
            $summaryData = $utilitySummaryRaw[$type] ?? [
                'total_cost' => 0,
                'average_per_unit' => 0,
                'property_count' => 0,
            ];
            $utilitySummary[$type] = [
                'type' => $type,
                'label' => $typeModel->label,
                'icon' => $typeModel->icon_or_default,
                'color_scheme' => $typeModel->color_scheme_or_default,
                'total_cost' => $summaryData['total_cost'],
                'average_per_unit' => $summaryData['average_per_unit'],
                'property_count' => $summaryData['property_count'],
            ];
        }

        // Calculate portfolio totals
        $portfolioTotal = array_sum(array_column($utilitySummary, 'total_cost'));

        // Get anomalies across all utility types
        $anomalies = [];
        foreach ($utilityTypeModels as $typeModel) {
            $type = $typeModel->key;
            $typeAnomalies = $this->analyticsService->getAnomalies($type, $period, 2.0);
            foreach ($typeAnomalies as $anomaly) {
                $anomaly['utility_type'] = $type;
                $anomaly['utility_label'] = $typeModel->label;
                $anomalies[] = $anomaly;
            }
        }
        // Sort by absolute deviation and limit to top 10
        usort($anomalies, fn ($a, $b) => abs($b['deviation']) <=> abs($a['deviation']));
        $anomalies = array_slice($anomalies, 0, 10);

        // Get trend data for the portfolio (last 12 months)
        $trendData = $this->getPortfolioTrend($utilityTypes, 12);

        return Inertia::render('Utilities/Dashboard', [
            'period' => $periodType,
            'periodLabel' => $this->getPeriodLabel($periodType, $date),
            'utilitySummary' => array_values($utilitySummary),
            'portfolioTotal' => $portfolioTotal,
            'anomalies' => $anomalies,
            'trendData' => $trendData,
            'utilityTypes' => UtilityType::getAllWithMetadata(),
        ]);
    }

    /**
     * Display the utility data table view with filtering and formatting.
     */
    public function data(UtilityDataRequest $request): Response
    {
        $validated = $request->validated();

        // Get utility types from the database
        $utilityTypeModels = UtilityType::ordered()->get();
        $utilityTypeOptions = $utilityTypeModels->pluck('label', 'key')->toArray();
        $utilityTypes = array_keys($utilityTypeOptions);

        // Get selected utility type (default to first available)
        $selectedUtilityType = $validated['utility_type'] ?? $utilityTypes[0] ?? 'water';
        if (! in_array($selectedUtilityType, $utilityTypes, true)) {
            $selectedUtilityType = $utilityTypes[0] ?? 'water';
        }

        // Build filters array
        $filters = [
            'unit_count_min' => $validated['unit_count_min'] ?? null,
            'unit_count_max' => $validated['unit_count_max'] ?? null,
            'property_types' => $validated['property_types'] ?? [],
        ];

        // Get filtered property comparison data
        $comparisonData = $this->analyticsService->getFilteredPropertyComparisonData(
            $selectedUtilityType,
            $filters
        );

        // Apply conditional formatting to each property
        $comparisonData = $this->formattingService->applyFormattingToComparison(
            $comparisonData,
            $selectedUtilityType
        );

        // Calculate heat map statistics
        $heatMapStats = $this->analyticsService->calculateHeatMapStats($comparisonData['properties']);

        // Attach notes to property comparison data
        $this->analyticsService->attachNotesToComparisonData($comparisonData, $selectedUtilityType);

        // Get property type options for filter dropdown
        $propertyTypeOptions = $this->analyticsService->getPropertyTypeOptions();

        // Get excluded properties info for display
        $excludedProperties = $this->analyticsService->getExcludedPropertiesInfo();

        return Inertia::render('Utilities/Data', [
            'propertyComparison' => $comparisonData,
            'selectedUtilityType' => $selectedUtilityType,
            'utilityTypes' => UtilityType::getAllWithMetadata(),
            'heatMapStats' => $heatMapStats,
            'filters' => $filters,
            'propertyTypeOptions' => $propertyTypeOptions,
        ]);
    }

    /**
     * Display excluded properties page.
     */
    public function excluded(): Response
    {
        $excludedProperties = $this->analyticsService->getExcludedPropertiesInfo();

        return Inertia::render('Utilities/Excluded', [
            'excludedProperties' => $excludedProperties,
        ]);
    }

    /**
     * Display utility details for a specific property.
     */
    public function show(Request $request, Property $property): Response
    {
        $this->authorize('view', $property);

        $periodType = $request->get('period', 'month');
        if (! in_array($periodType, self::VALID_PERIODS, true)) {
            $periodType = 'month';
        }
        $date = Carbon::now();

        $period = [
            'type' => $periodType,
            'date' => $date,
        ];

        // Get utility types from the database
        $utilityTypeModels = UtilityType::ordered()->get();
        $utilityTypes = $utilityTypeModels->pluck('key')->toArray();

        // Get cost breakdown for this property
        $costBreakdown = $this->analyticsService->getCostBreakdown($property, $period);

        // Get period comparison for each utility type
        $comparisons = [];
        foreach ($utilityTypeModels as $typeModel) {
            $type = $typeModel->key;
            $comparison = $this->analyticsService->getPeriodComparison($property, $type, $date);
            $portfolioAvg = $this->analyticsService->getPortfolioAverage($type, $period);
            $costPerUnit = $this->analyticsService->getCostPerUnit($property, $type, $period);

            $comparisons[$type] = [
                'type' => $type,
                'label' => $typeModel->label,
                'icon' => $typeModel->icon_or_default,
                'color_scheme' => $typeModel->color_scheme_or_default,
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

        // Get recent expenses (eager load utilityAccount with utilityType to avoid N+1)
        $recentExpenses = UtilityExpense::query()
            ->with('utilityAccount.utilityType')
            ->forProperty($property->id)
            ->orderByDesc('expense_date')
            ->limit(20)
            ->get()
            ->map(fn ($expense) => [
                'id' => $expense->id,
                'utility_type' => $expense->utility_type,
                'utility_label' => $expense->utility_type_label,
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
            'utilityTypes' => UtilityType::getAllWithMetadata(),
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
