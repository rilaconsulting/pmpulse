<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BillDetail;
use App\Models\Lease;
use App\Models\LedgerTransaction;
use App\Models\Person;
use App\Models\Property;
use App\Models\RawAppfolioEvent;
use App\Models\SyncRun;
use App\Models\Unit;
use App\Models\Vendor;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Ingestion Service
 *
 * This service handles the normalization and storage of AppFolio data.
 * It implements idempotent upsert patterns to prevent duplicate records.
 *
 * IMPORTANT: All field mappings in this file are placeholders.
 * TODO: Update field mappings when actual AppFolio API response
 * structures are documented.
 */
class IngestionService
{
    private SyncRun $syncRun;

    private int $processedCount = 0;

    private int $errorCount = 0;

    private array $errors = [];

    /** @var ResourceSyncTracker|null Current resource tracker */
    private ?ResourceSyncTracker $currentTracker = null;

    /** @var array<string, ResourceSyncTracker> Completed trackers by resource type */
    private array $resourceTrackers = [];

    /** @var array<string, int> Cache of property external_id => id mappings */
    private array $propertyCache = [];

    /** @var array<string, int> Cache of unit external_id => id mappings */
    private array $unitCache = [];

    /** @var array<string, int> Cache of person external_id => id mappings */
    private array $personCache = [];

    /** @var array<string, string> Cache of vendor external_id => id mappings */
    private array $vendorCache = [];

    public function __construct(
        private readonly AppfolioClient $appfolioClient,
        private readonly UtilityExpenseService $utilityExpenseService
    ) {}

    /**
     * Start a sync run.
     */
    public function startSync(SyncRun $syncRun): self
    {
        $this->syncRun = $syncRun;
        $this->processedCount = 0;
        $this->errorCount = 0;
        $this->errors = [];
        $this->currentTracker = null;
        $this->resourceTrackers = [];
        $this->propertyCache = [];
        $this->unitCache = [];
        $this->personCache = [];
        $this->vendorCache = [];

        $syncRun->markAsRunning();

        Log::info('Sync run started', [
            'run_id' => $syncRun->id,
            'mode' => $syncRun->mode,
        ]);

        return $this;
    }

    /**
     * Process all resource types.
     */
    public function processAll(): void
    {
        $resources = config('appfolio.resources', []);

        foreach ($resources as $resource) {
            try {
                $this->processResource($resource);
            } catch (\Exception $e) {
                $this->errorCount++;
                $this->errors[] = "Failed to process {$resource}: {$e->getMessage()}";
                Log::error('Failed to process resource', [
                    'resource' => $resource,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Process a specific resource type.
     */
    public function processResource(string $resourceType): void
    {
        // Create a new tracker for this resource type
        $this->currentTracker = new ResourceSyncTracker($this->syncRun, $resourceType);

        $params = $this->buildQueryParams($resourceType);

        // Map resource types to AppfolioClient method names
        $methodMap = [
            'properties' => 'getPropertyDirectory',
            'units' => 'getUnitDirectory',
            'vendors' => 'getVendorDirectory',
            'work_orders' => 'getWorkOrderReport',
            'bill_details' => 'getBillDetail',
            'rent_roll' => 'getRentRoll',
            'delinquency' => 'getDelinquency',
        ];

        if (! isset($methodMap[$resourceType])) {
            throw new \InvalidArgumentException("Unknown resource type: {$resourceType}");
        }

        $method = $methodMap[$resourceType];

        // Fetch ALL pages from AppFolio Reports API V2
        // This uses pagination to get the complete dataset
        $allResults = $this->appfolioClient->fetchAllPages(
            $method,
            $params,
            function (int $page, int $recordsFetched, bool $hasMore) use ($resourceType) {
                Log::info("Fetched page {$page} for {$resourceType}", [
                    'records_so_far' => $recordsFetched,
                    'has_more' => $hasMore,
                ]);
            }
        );

        // Wrap results in expected format for processItems
        $data = ['results' => $allResults];

        // Store raw data and normalize
        $this->processItems($resourceType, $data);

        // After processing rent_roll, update unit statuses based on active leases
        if ($resourceType === 'rent_roll') {
            $this->updateUnitStatusFromLeases();
        }

        // Finish tracking and save metrics
        $this->resourceTrackers[$resourceType] = $this->currentTracker;
        $this->currentTracker->finish();
        $this->currentTracker = null;
    }

    /**
     * Build query parameters based on sync mode and resource type.
     *
     * Different resource types require different parameters:
     * - bill_details: requires from_date and to_date
     * - work_orders: requires from_date and to_date
     * - others: use modified_since for incremental sync
     */
    private function buildQueryParams(string $resourceType = ''): array
    {
        $params = [
            'per_page' => config('appfolio.sync.batch_size', 100),
        ];

        // Resources that require date range parameters
        $dateRangeResources = ['bill_details', 'work_orders'];

        // Check for custom date range in SyncRun metadata (takes priority)
        $customDateRange = $this->syncRun->getCustomDateRange();

        if (in_array($resourceType, $dateRangeResources, true)) {
            // For date range resources, always use from_date and to_date
            if ($customDateRange) {
                // Use custom date range from sync run metadata
                $params['from_date'] = $customDateRange['from_date'];
                $params['to_date'] = $customDateRange['to_date'];
            } elseif ($this->syncRun->mode === 'incremental') {
                $days = config('appfolio.sync.incremental_days', 7);
                $params['from_date'] = now()->subDays($days)->format('Y-m-d');
                $params['to_date'] = now()->format('Y-m-d');
            } else {
                // Full sync: look back configured number of days
                $days = config('appfolio.sync.full_sync_lookback_days', 365);
                $params['from_date'] = now()->subDays($days)->format('Y-m-d');
                $params['to_date'] = now()->format('Y-m-d');
            }
        } elseif ($this->syncRun->mode === 'incremental') {
            // For other resources, use modified_since for incremental sync
            $days = config('appfolio.sync.incremental_days', 7);
            $params['modified_since'] = now()->subDays($days)->toIso8601String();
        }

        return $params;
    }

    /**
     * Process items for a resource type.
     */
    private function processItems(string $resourceType, array $data): void
    {
        // AppFolio Reports API V2 returns results under the 'results' key
        // If paginate_results=false, the response is just an array of rows
        $items = $data['results'] ?? $data;

        if (! is_array($items)) {
            Log::warning('No items found for resource type', ['type' => $resourceType]);

            return;
        }

        // Prefetch related entities to avoid N+1 queries
        $this->prefetchRelatedEntities($resourceType, $items);

        foreach ($items as $item) {
            try {
                $result = null;

                // Bill details go directly to bill_details table (has unique txn_id)
                if ($resourceType === 'bill_details') {
                    DB::transaction(function () use ($resourceType, $item, &$result) {
                        // Store raw event for audit trail
                        $this->storeRawEvent($resourceType, $item);
                        $result = $this->upsertBillDetail($item);
                    });
                } else {
                    DB::transaction(function () use ($resourceType, $item, &$result) {
                        // Store raw event
                        $this->storeRawEvent($resourceType, $item);

                        // Normalize and upsert - returns true if created, false if updated, null if skipped
                        $result = $this->normalizeAndUpsert($resourceType, $item);
                    });
                }

                // Track result: created, updated, or skipped
                if ($this->currentTracker) {
                    if ($result === true) {
                        $this->currentTracker->recordCreated();
                        $this->processedCount++;
                    } elseif ($result === false) {
                        $this->currentTracker->recordUpdated();
                        $this->processedCount++;
                    } else {
                        // null = skipped (e.g., missing related entity)
                        $this->currentTracker->recordSkipped('Missing related entity');
                    }
                } else {
                    if ($result !== null) {
                        $this->processedCount++;
                    }
                }
            } catch (\Exception $e) {
                $this->errorCount++;

                // Track error with context
                if ($this->currentTracker) {
                    $this->currentTracker->recordError($e->getMessage(), [
                        'item_id' => $item['id'] ?? $item['external_id'] ?? 'unknown',
                    ]);
                } else {
                    Log::error('Failed to process item', [
                        'type' => $resourceType,
                        'item' => $item,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        // Note: Pagination is now handled by AppfolioClient::fetchAllPages()
        // before data reaches this method, so no pagination handling needed here.
    }

    /**
     * Prefetch related entities to avoid N+1 query problems.
     */
    private function prefetchRelatedEntities(string $resourceType, array $items): void
    {
        match ($resourceType) {
            'units' => $this->prefetchProperties($items),
            'leases' => $this->prefetchUnitsAndPeople($items),
            'rent_roll' => $this->prefetchUnitsForRentRoll($items),
            'ledger_transactions' => $this->prefetchPropertiesAndUnits($items),
            'work_orders' => $this->prefetchWorkOrderRelations($items),
            default => null,
        };
    }

    /**
     * Cache model IDs by their external IDs for efficient lookups.
     *
     * This helper method extracts the common pattern of fetching model IDs
     * and storing them in a cache property to avoid N+1 queries.
     *
     * @param  class-string  $modelClass  The model class to query
     * @param  array<string>  $externalIds  The external IDs to look up
     * @param  string  $cacheProperty  The name of the cache property to populate
     */
    private function cacheIdsByExternalIds(string $modelClass, array $externalIds, string $cacheProperty): void
    {
        if (empty($externalIds)) {
            return;
        }

        $modelClass::whereIn('external_id', $externalIds)
            ->pluck('id', 'external_id')
            ->each(fn ($id, $externalId) => $this->{$cacheProperty}[$externalId] = $id);
    }

    /**
     * Extract unique external IDs from items for a given field path.
     *
     * @param  array  $items  The items to extract IDs from
     * @param  string  $primaryField  The primary field name (e.g., 'property_id')
     * @param  string|null  $nestedPath  Optional nested path (e.g., 'property.id')
     * @return array<string>
     */
    private function extractExternalIds(array $items, string $primaryField, ?string $nestedPath = null): array
    {
        return collect($items)
            ->map(function ($item) use ($primaryField, $nestedPath) {
                $value = $item[$primaryField] ?? null;

                if ($value === null && $nestedPath !== null) {
                    $parts = explode('.', $nestedPath);
                    $value = $item;
                    foreach ($parts as $part) {
                        $value = $value[$part] ?? null;
                        if ($value === null) {
                            break;
                        }
                    }
                }

                return $value !== null ? (string) $value : null;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Prefetch properties for unit processing.
     */
    private function prefetchProperties(array $items): void
    {
        $externalIds = $this->extractExternalIds($items, 'property_id', 'property.id');
        $this->cacheIdsByExternalIds(Property::class, $externalIds, 'propertyCache');
    }

    /**
     * Prefetch units and people for lease processing.
     */
    private function prefetchUnitsAndPeople(array $items): void
    {
        $unitExternalIds = $this->extractExternalIds($items, 'unit_id', 'unit.id');
        $this->cacheIdsByExternalIds(Unit::class, $unitExternalIds, 'unitCache');

        $personExternalIds = collect($items)
            ->map(fn ($item) => (string) ($item['tenant_id'] ?? $item['person_id'] ?? $item['resident_id'] ?? null))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $this->cacheIdsByExternalIds(Person::class, $personExternalIds, 'personCache');
    }

    /**
     * Prefetch properties and units for transaction/work order processing.
     */
    private function prefetchPropertiesAndUnits(array $items): void
    {
        $propertyExternalIds = $this->extractExternalIds($items, 'property_id', 'property.id');
        $this->cacheIdsByExternalIds(Property::class, $propertyExternalIds, 'propertyCache');

        $unitExternalIds = $this->extractExternalIds($items, 'unit_id', 'unit.id');
        $this->cacheIdsByExternalIds(Unit::class, $unitExternalIds, 'unitCache');
    }

    /**
     * Prefetch properties, units, and vendors for work order processing.
     */
    private function prefetchWorkOrderRelations(array $items): void
    {
        $propertyExternalIds = $this->extractExternalIds($items, 'property_id');
        $this->cacheIdsByExternalIds(Property::class, $propertyExternalIds, 'propertyCache');

        $unitExternalIds = $this->extractExternalIds($items, 'unit_id');
        $this->cacheIdsByExternalIds(Unit::class, $unitExternalIds, 'unitCache');

        $vendorExternalIds = $this->extractExternalIds($items, 'vendor_id');
        $this->cacheIdsByExternalIds(Vendor::class, $vendorExternalIds, 'vendorCache');
    }

    /**
     * Prefetch units for rent roll processing.
     */
    private function prefetchUnitsForRentRoll(array $items): void
    {
        $unitExternalIds = $this->extractExternalIds($items, 'unit_id');
        $this->cacheIdsByExternalIds(Unit::class, $unitExternalIds, 'unitCache');
    }

    /**
     * Look up a property ID from cache or database.
     */
    private function lookupPropertyId(string $externalId): ?string
    {
        if (isset($this->propertyCache[$externalId])) {
            return $this->propertyCache[$externalId];
        }

        $property = Property::where('external_id', $externalId)->first();
        if ($property) {
            $this->propertyCache[$externalId] = $property->id;

            return $property->id;
        }

        return null;
    }

    /**
     * Look up a unit ID from cache or database.
     */
    private function lookupUnitId(string $externalId): ?string
    {
        if (isset($this->unitCache[$externalId])) {
            return $this->unitCache[$externalId];
        }

        $unit = Unit::where('external_id', $externalId)->first();
        if ($unit) {
            $this->unitCache[$externalId] = $unit->id;

            return $unit->id;
        }

        return null;
    }

    /**
     * Look up a person ID from cache or database.
     */
    private function lookupPersonId(string $externalId): ?string
    {
        if (isset($this->personCache[$externalId])) {
            return $this->personCache[$externalId];
        }

        $person = Person::where('external_id', $externalId)->first();
        if ($person) {
            $this->personCache[$externalId] = $person->id;

            return $person->id;
        }

        return null;
    }

    /**
     * Store raw API response for debugging and replay.
     *
     * @throws \InvalidArgumentException If item is missing an external ID
     */
    private function storeRawEvent(string $resourceType, array $item): void
    {
        // Get the external ID field based on resource type
        $externalId = $this->extractExternalId($resourceType, $item);

        if (empty($externalId)) {
            throw new \InvalidArgumentException('Item is missing an external ID and cannot be processed.');
        }

        $pulledAt = now();

        // Use updateOrCreate to handle duplicates gracefully.
        // This can happen if AppFolio returns duplicate records or pagination overlaps.
        RawAppfolioEvent::updateOrCreate(
            [
                'resource_type' => $resourceType,
                'external_id' => $externalId,
                'pulled_at' => $pulledAt,
            ],
            [
                'sync_run_id' => $this->syncRun->id,
                'payload_json' => $item,
            ]
        );
    }

    /**
     * Extract the external ID from an item based on resource type.
     *
     * AppFolio Reports API V2 uses different ID field names:
     * - property_directory: property_id
     * - unit_directory: unit_id
     * - vendor_directory: vendor_id
     * - work_order: work_order_id
     * - bill_detail: txn_id (handled separately via upsertBillDetail)
     * - rent_roll: occupancy_id (unique per lease/occupancy)
     * - delinquency: unit_id
     */
    private function extractExternalId(string $resourceType, array $item): ?string
    {
        $idField = match ($resourceType) {
            'properties' => 'property_id',
            'units' => 'unit_id',
            'vendors' => 'vendor_id',
            'work_orders' => 'work_order_id',
            'bill_details' => 'txn_id',
            'rent_roll' => 'occupancy_id',
            'delinquency' => 'unit_id',
            default => 'id',
        };

        // Try the specific ID field first, then fallback to 'id'
        $value = $item[$idField] ?? $item['id'] ?? null;

        return $value !== null ? (string) $value : null;
    }

    /**
     * Normalize and upsert data to the appropriate table.
     *
     * @return bool|null True if record was created, false if updated, null if skipped
     */
    private function normalizeAndUpsert(string $resourceType, array $item): ?bool
    {
        return match ($resourceType) {
            'properties' => $this->upsertProperty($item),
            'units' => $this->upsertUnit($item),
            'vendors' => $this->upsertVendor($item),
            'people' => $this->upsertPerson($item),
            'leases' => $this->upsertLease($item),
            'rent_roll' => $this->upsertRentRollData($item),
            'ledger_transactions' => $this->upsertLedgerTransaction($item),
            'work_orders' => $this->upsertWorkOrder($item),
            default => null,
        };
    }

    /**
     * Upsert a property record.
     *
     * Maps AppFolio property_directory.json response fields to our schema.
     *
     * @return bool True if record was created, false if updated
     */
    private function upsertProperty(array $item): bool
    {
        // Map AppFolio property_directory fields to our schema
        // Note: property_name is often null in AppFolio - use property_address or property_street as fallback
        $name = $item['property_name']
            ?? $item['property_address']
            ?? $item['property']
            ?? $item['property_street']
            ?? 'Unknown Property';

        $data = [
            'name' => $name,
            'address_line1' => $item['property_street'] ?? $item['address'] ?? null,
            'address_line2' => $item['property_street2'] ?? $item['address2'] ?? null,
            'city' => $item['property_city'] ?? $item['city'] ?? null,
            'state' => $item['property_state'] ?? $item['state'] ?? null,
            'zip' => $item['property_zip'] ?? $item['zip'] ?? null,
            'property_type' => $item['property_type'] ?? $item['type'] ?? 'residential',
            'unit_count' => $item['units'] ?? $item['number_of_units'] ?? $item['unit_count'] ?? 0,
            // visibility = "Active" means is_active = true
            'is_active' => ($item['visibility'] ?? 'Active') === 'Active',
            // Enhanced fields from property_directory
            'portfolio' => $item['portfolio'] ?? null,
            'portfolio_id' => isset($item['portfolio_id']) ? (int) $item['portfolio_id'] : null,
            'year_built' => isset($item['year_built']) ? (int) $item['year_built'] : null,
            'total_sqft' => isset($item['sqft']) ? (int) $item['sqft'] : null,
            'county' => $item['property_county'] ?? $item['county'] ?? null,
        ];

        $externalId = (string) ($item['property_id'] ?? $item['id']);

        $property = Property::updateOrCreate(
            ['external_id' => $externalId],
            $data
        );

        return $property->wasRecentlyCreated;
    }

    /**
     * Upsert a unit record.
     *
     * Maps AppFolio unit_directory.json response fields to our schema.
     *
     * @return bool|null True if record was created, false if updated, null if skipped
     */
    private function upsertUnit(array $item): ?bool
    {
        // Look up property using cache to avoid N+1 queries
        $propertyExternalId = (string) ($item['property_id'] ?? $item['property']['id'] ?? null);
        $propertyId = $propertyExternalId ? $this->lookupPropertyId($propertyExternalId) : null;

        if (! $propertyId) {
            Log::warning('Property not found for unit', [
                'property_external_id' => $propertyExternalId,
                'unit_data' => $item,
            ]);

            // Return null since we're skipping
            return null;
        }

        // Map AppFolio unit_directory fields to our schema
        // Note: rentable is "Yes"/"No" string, visibility is "Active"/"Inactive"
        $data = [
            'property_id' => $propertyId,
            'unit_number' => $item['unit_name'] ?? $item['unit_number'] ?? $item['name'] ?? 'Unknown',
            'unit_type' => $item['unit_type'] ?? $item['billed_as'] ?? null,
            'sqft' => isset($item['sqft']) ? (int) $item['sqft'] : null,
            'bedrooms' => isset($item['bedrooms']) ? (int) $item['bedrooms'] : null,
            'bathrooms' => isset($item['bathrooms']) ? (float) $item['bathrooms'] : null,
            'status' => $this->mapUnitStatus($item['unit_status'] ?? $item['status'] ?? 'vacant'),
            'market_rent' => isset($item['market_rent']) ? (float) $item['market_rent'] : null,
            'advertised_rent' => isset($item['advertised_rent']) ? (float) $item['advertised_rent'] : null,
            // visibility = "Active" means is_active = true
            'is_active' => ($item['visibility'] ?? 'Active') === 'Active',
            // rentable is "Yes"/"No" string
            'rentable' => ($item['rentable'] ?? 'Yes') === 'Yes',
        ];

        $externalId = (string) ($item['unit_id'] ?? $item['id']);

        $unit = Unit::updateOrCreate(
            ['external_id' => $externalId],
            $data
        );

        return $unit->wasRecentlyCreated;
    }

    /**
     * Map AppFolio unit status to our status values.
     *
     * TODO: Adjust mappings based on actual AppFolio status values.
     */
    private function mapUnitStatus(string $status): string
    {
        return match (strtolower($status)) {
            'occupied', 'rented', 'leased' => 'occupied',
            'vacant', 'available', 'empty' => 'vacant',
            'not ready', 'not_ready', 'maintenance' => 'not_ready',
            default => 'vacant',
        };
    }

    /**
     * Upsert a vendor record.
     *
     * Maps AppFolio vendor_directory.json response fields to our schema.
     *
     * @return bool True if record was created, false if updated
     */
    private function upsertVendor(array $item): bool
    {
        // Map AppFolio vendor_directory fields to our schema
        // Parse date fields - AppFolio returns dates as strings
        $workersCompExpires = $this->parseDate($item['workers_comp_expires'] ?? null);
        $liabilityInsExpires = $this->parseDate($item['liability_ins_expires'] ?? null);
        $autoInsExpires = $this->parseDate($item['auto_ins_expires'] ?? null);
        $stateLicExpires = $this->parseDate($item['state_lic_expires'] ?? null);

        $data = [
            'company_name' => $item['company_name'] ?? $item['name'] ?? 'Unknown Vendor',
            'contact_name' => $item['name'] ?? $item['contact_name'] ?? null,
            'email' => $item['email'] ?? $item['primary_email'] ?? null,
            'phone' => $item['phone'] ?? $item['primary_phone'] ?? null,
            'address_street' => $item['address'] ?? $item['street'] ?? null,
            'address_city' => $item['city'] ?? null,
            'address_state' => $item['state'] ?? null,
            'address_zip' => $item['zip'] ?? $item['postal_code'] ?? null,
            'vendor_type' => $item['vendor_type'] ?? null,
            'vendor_trades' => $item['vendor_trades'] ?? null,
            'workers_comp_expires' => $workersCompExpires,
            'liability_ins_expires' => $liabilityInsExpires,
            'auto_ins_expires' => $autoInsExpires,
            'state_lic_expires' => $stateLicExpires,
            // do_not_use_for_work_order is a boolean or "Yes"/"No" string
            'do_not_use' => $this->parseBoolean($item['do_not_use_for_work_order'] ?? $item['do_not_use'] ?? false),
            'is_active' => ($item['visibility'] ?? 'Active') === 'Active',
        ];

        $externalId = (string) ($item['vendor_id'] ?? $item['id']);

        $vendor = Vendor::updateOrCreate(
            ['external_id' => $externalId],
            $data
        );

        // Cache the vendor ID for later use (work order linking)
        $this->vendorCache[$externalId] = $vendor->id;

        return $vendor->wasRecentlyCreated;
    }

    /**
     * Parse a date string from AppFolio API response.
     */
    private function parseDate(?string $dateString): ?\Carbon\Carbon
    {
        if (empty($dateString)) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($dateString);
        } catch (\Exception $e) {
            Log::warning('Failed to parse date', [
                'date_string' => $dateString,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Parse a boolean value from AppFolio API response.
     *
     * Handles "Yes"/"No" strings as well as actual booleans.
     */
    private function parseBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return strtolower($value) === 'yes' || strtolower($value) === 'true' || $value === '1';
        }

        return (bool) $value;
    }

    /**
     * Look up a vendor ID from cache or database.
     */
    private function lookupVendorId(string $externalId): ?string
    {
        if (isset($this->vendorCache[$externalId])) {
            return $this->vendorCache[$externalId];
        }

        $vendor = Vendor::where('external_id', $externalId)->first();
        if ($vendor) {
            $this->vendorCache[$externalId] = $vendor->id;

            return $vendor->id;
        }

        return null;
    }

    /**
     * Upsert a person record.
     *
     * TODO: Adjust field mappings based on actual AppFolio API response.
     *
     * @return bool True if record was created, false if updated
     */
    private function upsertPerson(array $item): bool
    {
        // Map AppFolio fields to our schema
        // TODO: Update these mappings when API documentation is available
        $data = [
            'name' => $item['name'] ?? trim(($item['first_name'] ?? '').' '.($item['last_name'] ?? '')),
            'email' => $item['email'] ?? $item['primary_email'] ?? null,
            'phone' => $item['phone'] ?? $item['primary_phone'] ?? $item['mobile'] ?? null,
            'type' => $this->mapPersonType($item['type'] ?? $item['person_type'] ?? 'tenant'),
            'is_active' => $item['active'] ?? $item['is_active'] ?? true,
        ];

        $externalId = (string) ($item['id'] ?? $item['person_id']);

        $person = Person::updateOrCreate(
            ['external_id' => $externalId],
            $data
        );

        return $person->wasRecentlyCreated;
    }

    /**
     * Map AppFolio person type to our type values.
     *
     * TODO: Adjust mappings based on actual AppFolio type values.
     */
    private function mapPersonType(string $type): string
    {
        return match (strtolower($type)) {
            'tenant', 'resident', 'renter' => 'tenant',
            'owner', 'landlord', 'property_owner' => 'owner',
            'vendor', 'contractor', 'service_provider' => 'vendor',
            default => 'tenant',
        };
    }

    /**
     * Upsert a lease record.
     *
     * TODO: Adjust field mappings based on actual AppFolio API response.
     *
     * @return bool|null True if record was created, false if updated, null if skipped
     */
    private function upsertLease(array $item): ?bool
    {
        // Look up unit and person using cache to avoid N+1 queries
        $unitExternalId = (string) ($item['unit_id'] ?? $item['unit']['id'] ?? null);
        $unitId = $unitExternalId ? $this->lookupUnitId($unitExternalId) : null;

        $personExternalId = (string) ($item['tenant_id'] ?? $item['person_id'] ?? $item['resident_id'] ?? null);
        $personId = $personExternalId ? $this->lookupPersonId($personExternalId) : null;

        if (! $unitId) {
            Log::warning('Unit not found for lease', ['unit_external_id' => $unitExternalId]);

            // Return null since we're skipping
            return null;
        }

        // Map AppFolio fields to our schema
        // TODO: Update these mappings when API documentation is available
        $data = [
            'unit_id' => $unitId,
            'person_id' => $personId,
            'start_date' => $item['start_date'] ?? $item['lease_start'] ?? $item['move_in_date'] ?? now(),
            'end_date' => $item['end_date'] ?? $item['lease_end'] ?? $item['move_out_date'] ?? null,
            'rent' => $item['rent'] ?? $item['monthly_rent'] ?? $item['rent_amount'] ?? 0,
            'security_deposit' => $item['security_deposit'] ?? $item['deposit'] ?? null,
            'status' => $this->mapLeaseStatus($item['status'] ?? $item['lease_status'] ?? 'active'),
        ];

        $externalId = (string) ($item['id'] ?? $item['lease_id']);

        $lease = Lease::updateOrCreate(
            ['external_id' => $externalId],
            $data
        );

        return $lease->wasRecentlyCreated;
    }

    /**
     * Map AppFolio lease status to our status values.
     *
     * TODO: Adjust mappings based on actual AppFolio status values.
     */
    private function mapLeaseStatus(string $status): string
    {
        return match (strtolower($status)) {
            'active', 'current', 'in_progress' => 'active',
            'past', 'expired', 'ended', 'terminated' => 'past',
            'future', 'pending', 'upcoming' => 'future',
            default => 'active',
        };
    }

    /**
     * Upsert a lease record from rent_roll data.
     *
     * Maps AppFolio rent_roll.json response fields to our leases table.
     * Does NOT store tenant PII - only lease dates, rent, and unit linkage.
     *
     * @return bool|null True if record was created, false if updated, null if skipped
     */
    private function upsertRentRollData(array $item): ?bool
    {
        // Look up unit using cache to avoid N+1 queries
        $unitExternalId = isset($item['unit_id']) ? (string) $item['unit_id'] : null;
        $unitId = $unitExternalId ? $this->lookupUnitId($unitExternalId) : null;

        if (! $unitId) {
            Log::warning('Unit not found for rent roll entry', [
                'unit_external_id' => $unitExternalId,
                'occupancy_id' => $item['occupancy_id'] ?? 'unknown',
            ]);

            return null;
        }

        // Use occupancy_id as the unique identifier for the lease
        $externalId = isset($item['occupancy_id']) ? (string) $item['occupancy_id'] : null;

        if (! $externalId) {
            Log::warning('Rent roll entry missing occupancy_id', [
                'unit_id' => $unitExternalId,
            ]);

            return null;
        }

        // Map AppFolio rent_roll fields to our schema
        // Note: We intentionally skip tenant name and tenant_id to avoid storing PII
        $data = [
            'unit_id' => $unitId,
            'person_id' => null, // Explicitly null - no tenant PII stored
            'start_date' => $this->parseDate($item['lease_from'] ?? $item['move_in'] ?? null) ?? now(),
            'end_date' => $this->parseDate($item['lease_to'] ?? null),
            'rent' => $this->parseAmount($item['rent'] ?? null) ?? 0,
            'security_deposit' => $this->parseAmount($item['deposit'] ?? null),
            'status' => $this->mapRentRollStatus($item['status'] ?? 'Current'),
        ];

        $lease = Lease::updateOrCreate(
            ['external_id' => $externalId],
            $data
        );

        return $lease->wasRecentlyCreated;
    }

    /**
     * Map AppFolio rent roll status to our lease status values.
     *
     * AppFolio rent_roll status values: Current, Past, Future, Notice, Evict
     */
    private function mapRentRollStatus(string $status): string
    {
        return match (strtolower($status)) {
            'current', 'notice' => 'active',
            'past', 'evict' => 'past',
            'future' => 'future',
            default => 'active',
        };
    }

    /**
     * Upsert a ledger transaction record.
     *
     * TODO: Adjust field mappings based on actual AppFolio API response.
     *
     * @return bool True if record was created, false if updated
     */
    private function upsertLedgerTransaction(array $item): bool
    {
        // Look up property and unit using cache to avoid N+1 queries
        $propertyExternalId = (string) ($item['property_id'] ?? $item['property']['id'] ?? null);
        $propertyId = $propertyExternalId ? $this->lookupPropertyId($propertyExternalId) : null;

        $unitExternalId = (string) ($item['unit_id'] ?? $item['unit']['id'] ?? null);
        $unitId = $unitExternalId ? $this->lookupUnitId($unitExternalId) : null;

        // Map AppFolio fields to our schema
        // TODO: Update these mappings when API documentation is available
        $data = [
            'property_id' => $propertyId,
            'unit_id' => $unitId,
            'date' => $item['date'] ?? $item['transaction_date'] ?? $item['posted_date'] ?? now(),
            'type' => $this->mapTransactionType($item['type'] ?? $item['transaction_type'] ?? 'charge'),
            'amount' => abs($item['amount'] ?? $item['transaction_amount'] ?? 0),
            'category' => $item['category'] ?? $item['charge_type'] ?? $item['gl_account'] ?? null,
            'description' => $item['description'] ?? $item['memo'] ?? $item['notes'] ?? null,
            'balance' => $item['balance'] ?? $item['running_balance'] ?? null,
        ];

        $externalId = (string) ($item['id'] ?? $item['transaction_id']);

        $transaction = LedgerTransaction::updateOrCreate(
            ['external_id' => $externalId],
            $data
        );

        return $transaction->wasRecentlyCreated;
    }

    /**
     * Map AppFolio transaction type to our type values.
     *
     * TODO: Adjust mappings based on actual AppFolio type values.
     */
    private function mapTransactionType(string $type): string
    {
        return match (strtolower($type)) {
            'charge', 'debit', 'invoice' => 'charge',
            'payment', 'credit', 'receipt' => 'payment',
            'adjustment', 'correction' => 'adjustment',
            default => 'charge',
        };
    }

    /**
     * Upsert a work order record.
     *
     * Maps AppFolio work_order.json response fields to our schema.
     *
     * @return bool True if record was created, false if updated
     */
    private function upsertWorkOrder(array $item): bool
    {
        // Look up property and unit using cache to avoid N+1 queries
        $propertyExternalId = (string) ($item['property_id'] ?? null);
        $propertyId = $propertyExternalId ? $this->lookupPropertyId($propertyExternalId) : null;

        // unit_id can be null for building-wide work orders
        $unitExternalId = $item['unit_id'] ? (string) $item['unit_id'] : null;
        $unitId = $unitExternalId ? $this->lookupUnitId($unitExternalId) : null;

        // Look up vendor using cache - vendor_id in AppFolio is an external ID
        $vendorExternalId = isset($item['vendor_id']) ? (string) $item['vendor_id'] : null;
        $vendorId = $vendorExternalId ? $this->lookupVendorId($vendorExternalId) : null;

        // Map AppFolio fields to our schema
        // created_at = when the work order was created
        // completed_on = when the work was completed
        // job_description = main description of the work
        $data = [
            'property_id' => $propertyId,
            'unit_id' => $unitId,
            'vendor_id' => $vendorId,
            'vendor_name' => $item['vendor_name'] ?? $item['vendor'] ?? null,
            'opened_at' => $item['created_at'] ?? now(),
            'closed_at' => $item['completed_on'] ?? $item['work_completed_on'] ?? null,
            'status' => $this->mapWorkOrderStatus($item['status'] ?? 'open'),
            'priority' => $this->mapWorkOrderPriority($item['priority'] ?? 'normal'),
            'category' => $item['work_order_type'] ?? $item['work_order_issue'] ?? null,
            'description' => $item['job_description'] ?? $item['service_request_description'] ?? $item['instructions'] ?? null,
            // Cost fields
            'amount' => $this->parseAmount($item['amount'] ?? null),
            'vendor_bill_amount' => $this->parseAmount($item['vendor_bill_amount'] ?? null),
            'estimate_amount' => $this->parseAmount($item['estimate_amount'] ?? $item['estimate'] ?? null),
            // Additional metadata
            'vendor_trade' => $item['vendor_trade'] ?? null,
            'work_order_type' => $item['work_order_type'] ?? null,
        ];

        $externalId = (string) ($item['work_order_id'] ?? $item['id']);

        $workOrder = WorkOrder::updateOrCreate(
            ['external_id' => $externalId],
            $data
        );

        return $workOrder->wasRecentlyCreated;
    }

    /**
     * Upsert a bill detail record.
     *
     * Maps AppFolio bill_detail.json response fields to our schema.
     * Uses txn_id as the unique identifier.
     *
     * @return bool|null True if created, false if updated, null if skipped
     */
    private function upsertBillDetail(array $item): ?bool
    {
        // Validate txn_id exists to avoid collisions on 0
        if (! isset($item['txn_id']) || $item['txn_id'] === null || $item['txn_id'] === '') {
            Log::warning('Skipping bill detail with missing txn_id', [
                'item' => array_intersect_key($item, array_flip(['reference_number', 'bill_date', 'description'])),
            ]);

            return null;
        }

        // Look up property and unit using cache to avoid N+1 queries
        $propertyExternalId = isset($item['property_id']) ? (string) $item['property_id'] : null;
        $propertyId = $propertyExternalId ? $this->lookupPropertyId($propertyExternalId) : null;

        $unitExternalId = isset($item['unit_id']) ? (string) $item['unit_id'] : null;
        $unitId = $unitExternalId ? $this->lookupUnitId($unitExternalId) : null;

        // Extract GL account number from various formats
        $glAccountNumber = $this->extractGlAccountNumber($item);

        // Map AppFolio bill_detail fields to our schema
        $data = [
            'sync_run_id' => $this->syncRun->id,
            'payable_invoice_detail_id' => $item['payable_invoice_detail_id'] ?? null,
            'reference_number' => $item['reference_number'] ?? null,
            'bill_date' => $this->parseDate($item['bill_date'] ?? null),
            'due_date' => $this->parseDate($item['due_date'] ?? null),
            'description' => $item['description'] ?? null,
            'gl_account' => $item['account'] ?? null,
            'gl_account_name' => $item['account_name'] ?? null,
            'gl_account_number' => $glAccountNumber,
            'gl_account_id' => $item['gl_account_id'] ?? null,
            'property_external_id' => $propertyExternalId,
            'property_id' => $propertyId,
            'unit_external_id' => $unitExternalId,
            'unit_id' => $unitId,
            'payee_name' => $item['payee_name'] ?? null,
            'party_id' => $item['party_id'] ?? null,
            'party_type' => $item['party_type'] ?? null,
            'vendor_id' => $item['vendor_id'] ?? null,
            'vendor_account_number' => $item['vendor_account_number'] ?? null,
            'paid' => $this->parseAmount($item['paid'] ?? null),
            'unpaid' => $this->parseAmount($item['unpaid'] ?? null),
            'quantity' => $this->parseAmount($item['quantity'] ?? null),
            'rate' => $this->parseAmount($item['rate'] ?? null),
            'check_number' => $item['check_number'] ?? null,
            'payment_date' => $this->parseDate($item['payment_date'] ?? null),
            'cash_account' => $item['cash_account'] ?? null,
            'bank_account' => $item['bank_account'] ?? null,
            'other_payment_type' => $item['other_payment_type'] ?? null,
            'work_order_number' => $item['work_order'] ?? null,
            'work_order_id' => $item['work_order_id'] ?? null,
            'work_order_assignee' => $item['work_order_assignee'] ?? null,
            'work_order_issue' => $item['work_order_issue'] ?? null,
            'service_request_id' => $item['service_request_id'] ?? null,
            'purchase_order_number' => $item['purchase_order_number'] ?? null,
            'purchase_order_id' => $item['purchase_order_id'] ?? null,
            'service_from' => $this->parseDate($item['service_from'] ?? null),
            'service_to' => $this->parseDate($item['service_to'] ?? null),
            'approval_status' => $item['approval_status'] ?? null,
            'approved_by' => $item['approved_by'] ?? null,
            'last_approver' => $item['last_approver'] ?? null,
            'next_approvers' => $item['next_approvers'] ?? null,
            'days_pending_approval' => $item['days_pending_approval'] ?? null,
            'board_approval_status' => $item['board_approval_status'] ?? null,
            'cost_center_name' => $item['cost_center_name'] ?? null,
            'cost_center_number' => $item['cost_center_number'] ?? null,
            'created_by' => $item['created_by'] ?? null,
            'txn_created_at' => $this->parseDateTime($item['txn_created_at'] ?? null),
            'txn_updated_at' => $this->parseDateTime($item['txn_updated_at'] ?? null),
            'pulled_at' => now(),
        ];

        $txnId = (int) $item['txn_id'];

        $billDetail = BillDetail::updateOrCreate(
            ['txn_id' => $txnId],
            $data
        );

        return $billDetail->wasRecentlyCreated;
    }

    /**
     * Extract GL account number from bill detail data.
     */
    private function extractGlAccountNumber(array $item): ?string
    {
        $glAccount = $item['account_number']
            ?? $item['gl_account_number']
            ?? $item['account']
            ?? null;

        if ($glAccount === null) {
            return null;
        }

        // AppFolio returns GL accounts in format "6210 - Water" or just "6210"
        // Extract just the numeric prefix
        if (preg_match('/^(\d+)/', (string) $glAccount, $matches)) {
            return $matches[1];
        }

        return (string) $glAccount;
    }

    /**
     * Parse an amount string from AppFolio API response.
     *
     * Handles amounts that may be strings with currency symbols or commas.
     */
    private function parseAmount(mixed $amount): ?float
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        if (is_numeric($amount)) {
            return (float) $amount;
        }

        if (is_string($amount)) {
            // Remove currency symbols, commas, and whitespace
            $cleaned = preg_replace('/[^0-9.\-]/', '', $amount);

            return is_numeric($cleaned) ? (float) $cleaned : null;
        }

        return null;
    }

    /**
     * Parse a datetime string from AppFolio API response.
     *
     * Similar to parseDate but semantically for timestamps.
     */
    private function parseDateTime(?string $dateTimeString): ?\Carbon\Carbon
    {
        if (empty($dateTimeString)) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($dateTimeString);
        } catch (\Exception $e) {
            Log::warning('Failed to parse datetime', [
                'datetime_string' => $dateTimeString,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Map AppFolio work order status to our status values.
     *
     * AppFolio statuses: Open, Assigned, Completed, Canceled, etc.
     */
    private function mapWorkOrderStatus(string $status): string
    {
        return match (strtolower($status)) {
            'open', 'new', 'pending', 'submitted' => 'open',
            'in_progress', 'assigned', 'working', 'scheduled' => 'in_progress',
            'completed', 'done', 'closed', 'resolved' => 'completed',
            'cancelled', 'canceled', 'rejected' => 'cancelled',
            default => 'open',
        };
    }

    /**
     * Map AppFolio work order priority to our priority values.
     *
     * TODO: Adjust mappings based on actual AppFolio priority values.
     */
    private function mapWorkOrderPriority(string $priority): string
    {
        return match (strtolower($priority)) {
            'low', 'minor' => 'low',
            'normal', 'medium', 'standard' => 'normal',
            'high', 'urgent', 'important' => 'high',
            'emergency', 'critical', 'immediate' => 'emergency',
            default => 'normal',
        };
    }

    /**
     * Update unit statuses based on active lease presence.
     *
     * Logic:
     * - Unit is 'occupied' if it has an active lease (start_date <= today AND (end_date >= today OR end_date IS NULL))
     * - Unit is 'vacant' if no active lease exists
     * - Unit with 'not_ready' status is preserved (maintenance flag)
     */
    private function updateUnitStatusFromLeases(): void
    {
        $today = now()->toDateString();

        // Get all units that are NOT marked as 'not_ready' (preserve maintenance status)
        $units = Unit::where('status', '!=', 'not_ready')->get();

        $updatedCount = 0;

        foreach ($units as $unit) {
            // Check if unit has an active lease
            $hasActiveLease = Lease::where('unit_id', $unit->id)
                ->where('start_date', '<=', $today)
                ->where(function ($query) use ($today) {
                    $query->where('end_date', '>=', $today)
                        ->orWhereNull('end_date');
                })
                ->exists();

            $newStatus = $hasActiveLease ? 'occupied' : 'vacant';

            if ($unit->status !== $newStatus) {
                $unit->status = $newStatus;
                $unit->save();
                $updatedCount++;
            }
        }

        Log::info('Updated unit statuses from leases', [
            'units_checked' => $units->count(),
            'units_updated' => $updatedCount,
        ]);
    }

    /**
     * Complete the sync run.
     */
    public function completeSync(): void
    {
        // Process utility expenses if we have expense data
        $this->processUtilityExpenses();

        if ($this->errorCount > 0) {
            $errorSummary = implode("\n", array_slice($this->errors, 0, 10));
            if (count($this->errors) > 10) {
                $errorSummary .= "\n... and ".(count($this->errors) - 10).' more errors';
            }

            $this->syncRun->update([
                'status' => 'completed',
                'ended_at' => now(),
                'resources_synced' => $this->processedCount,
                'errors_count' => $this->errorCount,
                'error_summary' => $errorSummary,
            ]);
        } else {
            $this->syncRun->markAsCompleted($this->processedCount);
        }

        // Update connection status
        if ($this->errorCount === 0) {
            $this->syncRun->connection?->markAsSuccess();
        }

        Log::info('Sync run completed', [
            'run_id' => $this->syncRun->id,
            'processed' => $this->processedCount,
            'errors' => $this->errorCount,
        ]);
    }

    /**
     * Process bill details to create utility expense records.
     *
     * Reads from bill_details table and creates utility_expenses
     * for bills matching configured GL accounts.
     */
    private function processUtilityExpenses(): void
    {
        // Check if there are bill details from this sync run
        $billDetailCount = BillDetail::where('sync_run_id', $this->syncRun->id)->count();

        if ($billDetailCount === 0) {
            return;
        }

        try {
            $stats = $this->utilityExpenseService->processFromBillDetails($this->syncRun->id);

            Log::info('Utility expenses processed during sync', [
                'sync_run_id' => $this->syncRun->id,
                'bill_details_processed' => $billDetailCount,
                'created' => $stats['created'],
                'updated' => $stats['updated'],
                'skipped' => $stats['skipped'],
                'unmatched' => $stats['unmatched'],
            ]);
        } catch (\Exception $e) {
            $this->errors[] = "Failed to process utility expenses: {$e->getMessage()}";
            Log::error('Failed to process utility expenses', [
                'sync_run_id' => $this->syncRun->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Fail the sync run.
     */
    public function failSync(string $error): void
    {
        $this->syncRun->markAsFailed($error);
        $this->syncRun->connection?->markAsError($error);

        Log::error('Sync run failed', [
            'run_id' => $this->syncRun->id,
            'error' => $error,
        ]);
    }

    /**
     * Get the count of processed items.
     */
    public function getProcessedCount(): int
    {
        return $this->processedCount;
    }

    /**
     * Get the count of errors.
     */
    public function getErrorCount(): int
    {
        return $this->errorCount;
    }
}
