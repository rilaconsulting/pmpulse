<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PropertyController extends Controller
{
    /**
     * Display a listing of properties.
     */
    public function index(Request $request): Response
    {
        $query = Property::query()
            ->withCount(['units', 'units as occupied_units_count' => function ($query) {
                $query->where('status', 'occupied');
            }, 'units as vacant_units_count' => function ($query) {
                $query->where('status', 'vacant');
            }]);

        // Search by name or address (case-insensitive)
        if ($search = $request->get('search')) {
            $query->search($search);
        }

        // Filter by portfolio
        if ($portfolio = $request->get('portfolio')) {
            $query->where('portfolio', $portfolio);
        }

        // Filter by property type
        if ($propertyType = $request->get('property_type')) {
            $query->where('property_type', $propertyType);
        }

        // Filter by active status
        if ($request->has('is_active') && $request->get('is_active') !== '') {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Sorting
        $sortField = $request->get('sort', 'name');
        $sortDirection = $request->get('direction', 'asc');
        $allowedSorts = ['name', 'city', 'unit_count', 'total_sqft', 'property_type', 'is_active'];

        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortDirection === 'desc' ? 'desc' : 'asc');
        }

        $properties = $query->paginate(15)->withQueryString();

        // Get unique portfolios and property types for filters
        $portfolios = Property::whereNotNull('portfolio')
            ->distinct()
            ->pluck('portfolio')
            ->sort()
            ->values();

        $propertyTypes = Property::whereNotNull('property_type')
            ->distinct()
            ->pluck('property_type')
            ->sort()
            ->values();

        // Calculate stats for each property
        $properties->getCollection()->transform(function ($property) {
            $property->occupancy_rate = $property->units_count > 0
                ? round(($property->occupied_units_count / $property->units_count) * 100, 1)
                : null;

            return $property;
        });

        return Inertia::render('Properties/Index', [
            'properties' => $properties,
            'portfolios' => $portfolios,
            'propertyTypes' => $propertyTypes,
            'filters' => [
                'search' => $request->get('search', ''),
                'portfolio' => $request->get('portfolio', ''),
                'property_type' => $request->get('property_type', ''),
                'is_active' => $request->get('is_active', ''),
                'sort' => $sortField,
                'direction' => $sortDirection,
            ],
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

        if (strlen($search) < 2) {
            return response()->json([]);
        }

        $properties = Property::query()
            ->active()
            ->search($search)
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'address_line1', 'city', 'state']);

        return response()->json($properties->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'address' => implode(', ', array_filter([
                $p->address_line1,
                $p->city,
                $p->state,
            ])),
        ]));
    }

    /**
     * Display the specified property.
     */
    public function show(Property $property): Response
    {
        $property->load([
            'units' => function ($query) {
                $query->orderBy('unit_number');
            },
        ]);

        // Calculate property stats
        $stats = [
            'total_units' => $property->units->count(),
            'occupied_units' => $property->units->where('status', 'occupied')->count(),
            'vacant_units' => $property->units->where('status', 'vacant')->count(),
            'not_ready_units' => $property->units->where('status', 'not_ready')->count(),
            'occupancy_rate' => $property->units->count() > 0
                ? round(($property->units->where('status', 'occupied')->count() / $property->units->count()) * 100, 1)
                : 0,
            'total_market_rent' => $property->units->sum('market_rent'),
            'avg_market_rent' => $property->units->avg('market_rent'),
        ];

        return Inertia::render('Properties/Show', [
            'property' => $property,
            'stats' => $stats,
        ]);
    }
}
