<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\DestroyFlagRequest;
use App\Http\Requests\StoreFlagRequest;
use App\Models\Property;
use App\Models\PropertyAdjustment;
use App\Models\PropertyFlag;
use App\Models\Setting;
use App\Services\AdjustmentService;
use App\Services\PropertyService;
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
    public function show(Request $request, Property $property, AdjustmentService $adjustmentService): Response
    {
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
}
