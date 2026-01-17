<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Property;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
     * Get a filtered, paginated list of properties.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<Property>
     */
    public function getFilteredProperties(array $filters, int $perPage = 15): LengthAwarePaginator
    {
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
        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== '' && $filters['is_active'] !== null) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        // Sorting
        $sortField = $filters['sort'] ?? 'name';
        $sortDirection = $filters['direction'] ?? 'asc';
        $allowedSorts = ['name', 'city', 'unit_count', 'total_sqft', 'property_type', 'is_active'];

        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortDirection === 'desc' ? 'desc' : 'asc');
        }

        $properties = $query->paginate($perPage)->withQueryString();

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
        $aggregates = DB::table('units')
            ->where('property_id', $property->id)
            ->selectRaw("
                COUNT(*) as total_units,
                COUNT(CASE WHEN status = 'occupied' THEN 1 END) as occupied_units,
                COUNT(CASE WHEN status = 'vacant' THEN 1 END) as vacant_units,
                COUNT(CASE WHEN status = 'not_ready' THEN 1 END) as not_ready_units,
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
