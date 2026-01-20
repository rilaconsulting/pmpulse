<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Property;
use Illuminate\Contracts\Pagination\LengthAwarePaginator as LengthAwarePaginatorContract;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Property Service
 *
 * Handles business logic for property management including filtering,
 * searching, and statistics calculation.
 */
class PropertyService
{
    public function __construct(
        private readonly AdjustmentService $adjustmentService
    ) {}

    /**
     * Allowed page size values.
     */
    public const ALLOWED_PAGE_SIZES = [15, 50, 100];

    /**
     * Get a filtered, paginated list of properties.
     *
     * When $perPage is 'all', returns all matching properties in a single page
     * within a LengthAwarePaginator instance (useful for map view or exports).
     *
     * @param  array<string, mixed>  $filters
     * @param  int|string  $perPage  Number of items per page (15, 50, 100), or 'all' to return all results
     * @return LengthAwarePaginatorContract<Property>
     */
    public function getFilteredProperties(array $filters, int|string $perPage = 15): LengthAwarePaginatorContract
    {
        // Validate and normalize perPage
        $showAll = $perPage === 'all';
        if (! $showAll) {
            $perPage = in_array((int) $perPage, self::ALLOWED_PAGE_SIZES, true)
                ? (int) $perPage
                : 15;
        }

        $query = Property::query()
            ->with(['adjustments' => function ($query) {
                $query->activeOn(now()->startOfDay());
            }])
            ->withCount(['units', 'units as occupied_units_count' => function ($query) {
                $query->where('status', 'occupied');
            }, 'units as vacant_units_count' => function ($query) {
                $query->where('status', 'vacant');
            }]);

        // Search by name or address (case-insensitive)
        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        // Filter by portfolio
        if (! empty($filters['portfolio'])) {
            $query->where('portfolio', $filters['portfolio']);
        }

        // Filter by property type
        if (! empty($filters['property_type'])) {
            $query->where('property_type', $filters['property_type']);
        }

        // Filter by active status
        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        // Sorting
        $sortField = $filters['sort'] ?? 'name';
        $sortDirection = $filters['direction'] ?? 'asc';
        $allowedSorts = ['name', 'city', 'unit_count', 'total_sqft', 'property_type', 'is_active'];

        // Map unit_count to units_count (withCount creates units_count)
        if ($sortField === 'unit_count') {
            $sortField = 'units_count';
        }

        if (in_array($filters['sort'] ?? 'name', $allowedSorts)) {
            $query->orderBy($sortField, $sortDirection === 'desc' ? 'desc' : 'asc');
        }

        // Handle 'all' case - return all results in a paginator-like structure
        if ($showAll) {
            $allProperties = $query->get();
            $properties = new LengthAwarePaginator(
                $allProperties,
                $allProperties->count(),
                $allProperties->count() ?: 1, // Avoid division by zero
                1,
                ['path' => request()->url(), 'query' => request()->query()]
            );
        } else {
            $properties = $query->paginate($perPage)->withQueryString();
        }

        // Calculate stats and effective values for each property
        $properties->getCollection()->transform(function ($property) {
            $property->occupancy_rate = $property->units_count > 0
                ? round(($property->occupied_units_count / $property->units_count) * 100, 1)
                : null;

            // Add effective values with metadata for adjusted fields
            $property->effective_values = $this->adjustmentService->getEffectiveValuesWithMetadata($property);

            return $property;
        });

        return $properties;
    }

    /**
     * Get unique portfolios from all properties.
     *
     * @return Collection<int, string>
     */
    public function getPortfolios(): Collection
    {
        return Property::whereNotNull('portfolio')
            ->distinct()
            ->pluck('portfolio')
            ->sort()
            ->values();
    }

    /**
     * Get unique property types from all properties.
     *
     * @return Collection<int, string>
     */
    public function getPropertyTypes(): Collection
    {
        return Property::whereNotNull('property_type')
            ->distinct()
            ->pluck('property_type')
            ->sort()
            ->values();
    }

    /**
     * Calculate property statistics using database-level aggregation.
     *
     * @return array{total_units: int, occupied_units: int, vacant_units: int, not_ready_units: int, occupancy_rate: float, total_market_rent: float, avg_market_rent: float}
     */
    public function getPropertyStats(Property $property): array
    {
        $aggregates = $property->units()
            ->selectRaw("
                COUNT(*) as total_units,
                COUNT(*) FILTER (WHERE status = 'occupied') as occupied_units,
                COUNT(*) FILTER (WHERE status = 'vacant') as vacant_units,
                COUNT(*) FILTER (WHERE status = 'not_ready') as not_ready_units,
                COALESCE(SUM(market_rent), 0) as total_market_rent,
                COALESCE(AVG(market_rent), 0) as avg_market_rent
            ")
            ->first();

        return [
            'total_units' => (int) $aggregates->total_units,
            'occupied_units' => (int) $aggregates->occupied_units,
            'vacant_units' => (int) $aggregates->vacant_units,
            'not_ready_units' => (int) $aggregates->not_ready_units,
            'occupancy_rate' => $aggregates->total_units > 0
                ? round(($aggregates->occupied_units / $aggregates->total_units) * 100, 1)
                : 0,
            'total_market_rent' => (float) $aggregates->total_market_rent,
            'avg_market_rent' => (float) $aggregates->avg_market_rent,
        ];
    }

    /**
     * Get filtered and paginated units for a property.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<\App\Models\Unit>
     */
    public function getFilteredUnits(Property $property, array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $query = $property->units();

        // Filter by status
        $status = $filters['status'] ?? '';
        if ($status && in_array($status, ['occupied', 'vacant', 'not_ready'], true)) {
            $query->where('status', $status);
        }

        // Sort
        $sortField = $filters['sort'] ?? 'unit_number';
        $sortDirection = $filters['direction'] ?? 'asc';

        $allowedSortFields = ['unit_number', 'status', 'bedrooms', 'sqft', 'market_rent', 'unit_type'];
        if (! in_array($sortField, $allowedSortFields, true)) {
            $sortField = 'unit_number';
        }
        if (! in_array($sortDirection, ['asc', 'desc'], true)) {
            $sortDirection = 'asc';
        }

        $query->orderBy($sortField, $sortDirection);

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Search properties for autocomplete.
     *
     * @return Collection<int, array{id: string, name: string, address: string}>
     */
    public function searchProperties(string $search, int $limit = 10): Collection
    {
        if (strlen($search) < 2) {
            return collect([]);
        }

        return Property::query()
            ->active()
            ->search($search)
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'address_line1', 'city', 'state'])
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'address' => implode(', ', array_filter([
                    $p->address_line1,
                    $p->city,
                    $p->state,
                ])),
            ]);
    }
}
