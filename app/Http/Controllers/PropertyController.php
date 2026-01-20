<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\DestroyFlagRequest;
use App\Http\Requests\StoreFlagRequest;
use App\Models\Property;
use App\Models\PropertyAdjustment;
use App\Models\PropertyFlag;
use App\Models\Setting;
use App\Models\UtilityExpense;
use App\Models\UtilityType;
use App\Models\WorkOrder;
use App\Services\AdjustmentService;
use App\Services\PropertyService;
use App\Services\UtilityAnalyticsService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PropertyController extends Controller
{
    public function __construct(
        private readonly PropertyService $propertyService
    ) {}

    /**
     * Display a listing of properties.
     */
    public function index(Request $request): Response
    {
        $filters = [
            'search' => $request->get('search', ''),
            'portfolio' => $request->get('portfolio', ''),
            'property_type' => $request->get('property_type', ''),
            'is_active' => $request->get('is_active', ''),
            'sort' => $request->get('sort', 'name'),
            'direction' => $request->get('direction', 'asc'),
        ];

        // Extract and validate perPage from request
        $perPageInput = $request->get('per_page', 15);
        $perPage = $perPageInput === 'all' ? 'all' : (int) $perPageInput;

        $properties = $this->propertyService->getFilteredProperties($filters, $perPage);
        $portfolios = $this->propertyService->getPortfolios();
        $propertyTypes = $this->propertyService->getPropertyTypes();

        // Normalize perPage for frontend (if invalid, service returns 15)
        $effectivePerPage = $perPageInput === 'all' ? 'all' : $properties->perPage();

        return Inertia::render('Properties/Index', [
            'properties' => $properties,
            'portfolios' => $portfolios,
            'propertyTypes' => $propertyTypes,
            'filters' => $filters,
            'perPage' => $effectivePerPage,
            'allowedPageSizes' => PropertyService::ALLOWED_PAGE_SIZES,
            'googleMapsApiKey' => Setting::get('google', 'maps_api_key'),
        ]);
    }

    /**
     * Search properties for autocomplete.
     */
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $search = $validated['q'] ?? '';
        $results = $this->propertyService->searchProperties($search);

        return response()->json($results);
    }

    /**
     * Display the specified property.
     */
    public function show(
        Request $request,
        Property $property,
        AdjustmentService $adjustmentService,
        UtilityAnalyticsService $utilityAnalyticsService
    ): Response {
        $this->authorize('view', $property);

        $property->load([
            'flags' => function ($query) {
                $query->with('creator:id,name')->orderBy('created_at', 'desc');
            },
            'adjustments' => function ($query) {
                $query->with('creator:id,name')->orderBy('created_at', 'desc');
            },
        ]);

        // Get unit filters from request
        $unitFilters = [
            'status' => $request->get('unit_status', ''),
            'sort' => $request->get('unit_sort', 'unit_number'),
            'direction' => $request->get('unit_direction', 'asc'),
        ];

        // Get paginated units
        $units = $this->propertyService->getFilteredUnits($property, $unitFilters);

        // Calculate property stats
        $stats = $this->propertyService->getPropertyStats($property);

        // Build AppFolio URL if database is configured and property has external_id
        $appfolioUrl = null;
        $appfolioDatabase = Setting::get('appfolio', 'database');
        if ($appfolioDatabase && $property->external_id) {
            $appfolioUrl = "https://{$appfolioDatabase}.appfolio.com/properties/{$property->external_id}";
        }

        // Separate active and historical adjustments using partition for better performance
        $today = now()->startOfDay();
        [$activeAdjustments, $historicalAdjustments] = $property->adjustments->partition(fn ($adj) => $adj->isActiveOn($today));

        // Get effective values with metadata for display
        $effectiveValues = $adjustmentService->getEffectiveValuesWithMetadata($property);

        // Get initial tab from URL parameter (validated against allowed values)
        $allowedTabs = ['overview', 'units', 'utilities', 'work-orders', 'settings'];
        $initialTab = $request->get('tab');
        if (! in_array($initialTab, $allowedTabs)) {
            $initialTab = 'overview';
        }

        // Load utility data for the Utilities tab
        $utilityData = $this->loadUtilityData($property, $utilityAnalyticsService);

        // Load work order data for the Work Orders tab
        $workOrderData = $this->loadWorkOrderData($property);

        return Inertia::render('Properties/Show', [
            'property' => $property,
            'units' => $units,
            'unitFilters' => $unitFilters,
            'stats' => $stats,
            'flagTypes' => PropertyFlag::FLAG_TYPES,
            'appfolioUrl' => $appfolioUrl,
            'googleMapsApiKey' => Setting::get('google', 'maps_api_key'),
            'adjustableFields' => PropertyAdjustment::ADJUSTABLE_FIELDS,
            'activeAdjustments' => $activeAdjustments->values(),
            'historicalAdjustments' => $historicalAdjustments->values(),
            'effectiveValues' => $effectiveValues,
            'initialTab' => $initialTab,
            'utilityData' => $utilityData,
            'workOrderData' => $workOrderData,
        ]);
    }

    /**
     * Store a new flag for the property.
     */
    public function storeFlag(StoreFlagRequest $request, Property $property): RedirectResponse
    {
        $validated = $request->validated();

        // Check if flag already exists
        if ($property->hasFlag($validated['flag_type'])) {
            return back()->withErrors(['flag_type' => 'This flag already exists for this property.']);
        }

        try {
            $property->flags()->create([
                'flag_type' => $validated['flag_type'],
                'reason' => $validated['reason'] ?? null,
                'created_by' => $request->user()->id,
            ]);
        } catch (QueryException $e) {
            // Handle race condition where flag was created between check and insert
            if (str_contains($e->getMessage(), 'unique') || str_contains($e->getMessage(), 'duplicate')) {
                return back()->withErrors(['flag_type' => 'This flag already exists for this property.']);
            }
            throw $e;
        }

        return back()->with('success', 'Flag added successfully.');
    }

    /**
     * Remove a flag from the property.
     */
    public function destroyFlag(DestroyFlagRequest $request, Property $property, PropertyFlag $flag): RedirectResponse
    {
        // Ensure the flag belongs to the property
        if ($flag->property_id !== $property->id) {
            abort(404);
        }

        $flag->delete();

        return back()->with('success', 'Flag removed successfully.');
    }

    /**
     * Load utility data for a property's Utilities tab.
     */
    private function loadUtilityData(Property $property, UtilityAnalyticsService $analyticsService): array
    {
        $date = Carbon::now();
        $period = ['type' => 'month', 'date' => $date];

        // Get utility types from the database
        $utilityTypeModels = UtilityType::ordered()->get();
        $utilityTypes = $utilityTypeModels->pluck('key')->toArray();

        // Get cost breakdown for this property
        $costBreakdown = $analyticsService->getCostBreakdown($property, $period);

        // Get trend data for each utility type (last 12 months)
        $propertyTrend = [];
        foreach ($utilityTypes as $type) {
            $propertyTrend[$type] = $analyticsService->getTrend($property, $type, 12, 'month');
        }

        // Get recent expenses
        $recentExpenses = UtilityExpense::query()
            ->with('utilityAccount.utilityType')
            ->forProperty($property->id)
            ->orderByDesc('expense_date')
            ->limit(10)
            ->get()
            ->map(fn ($expense) => [
                'id' => $expense->id,
                'utility_type' => $expense->utility_type,
                'utility_label' => $expense->utility_type_label,
                'amount' => $expense->amount,
                'expense_date' => $expense->expense_date->toDateString(),
                'vendor_name' => $expense->vendor_name,
            ]);

        return [
            'costBreakdown' => $costBreakdown,
            'propertyTrend' => $propertyTrend,
            'recentExpenses' => $recentExpenses,
            'utilityTypes' => UtilityType::getAllWithMetadata(),
        ];
    }

    /**
     * Load work order data for a property's Work Orders tab.
     */
    private function loadWorkOrderData(Property $property): array
    {
        // Get status counts
        $statusCounts = WorkOrder::query()
            ->where('property_id', $property->id)
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open,
                SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
            ")
            ->first();

        // Get recent work orders with vendor info
        $recentWorkOrders = WorkOrder::query()
            ->where('property_id', $property->id)
            ->with(['vendor:id,company_name', 'unit:id,unit_number'])
            ->orderByDesc('opened_at')
            ->limit(20)
            ->get()
            ->map(fn ($wo) => [
                'id' => $wo->id,
                'external_id' => $wo->external_id,
                'status' => $wo->status,
                'priority' => $wo->priority,
                'category' => $wo->category,
                'description' => $wo->description,
                'vendor_name' => $wo->vendor?->company_name ?? $wo->vendor_name,
                'unit_number' => $wo->unit?->unit_number,
                'amount' => $wo->amount,
                'opened_at' => $wo->opened_at?->toDateString(),
                'closed_at' => $wo->closed_at?->toDateString(),
                'days_open' => $wo->days_open,
            ]);

        // Calculate total spend (completed work orders in last 12 months)
        $totalSpend = WorkOrder::query()
            ->where('property_id', $property->id)
            ->where('status', 'completed')
            ->where('closed_at', '>=', now()->subMonths(12))
            ->sum('amount');

        // Calculate average completion time for completed work orders
        $avgCompletionDays = WorkOrder::query()
            ->where('property_id', $property->id)
            ->where('status', 'completed')
            ->whereNotNull('opened_at')
            ->whereNotNull('closed_at')
            ->where('closed_at', '>=', now()->subMonths(12))
            ->selectRaw('AVG(EXTRACT(EPOCH FROM (closed_at - opened_at)) / 86400) as avg_days')
            ->value('avg_days');

        return [
            'statusCounts' => [
                'total' => (int) ($statusCounts->total ?? 0),
                'open' => (int) ($statusCounts->open ?? 0),
                'in_progress' => (int) ($statusCounts->in_progress ?? 0),
                'completed' => (int) ($statusCounts->completed ?? 0),
                'cancelled' => (int) ($statusCounts->cancelled ?? 0),
            ],
            'recentWorkOrders' => $recentWorkOrders,
            'totalSpend' => (float) $totalSpend,
            'avgCompletionDays' => $avgCompletionDays ? round((float) $avgCompletionDays, 1) : null,
        ];
    }
}
