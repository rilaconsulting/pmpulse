<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Lease;
use App\Models\LedgerTransaction;
use App\Models\Person;
use App\Models\Property;
use App\Models\RawAppfolioEvent;
use App\Models\SyncRun;
use App\Models\Unit;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\App;
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

    /** @var array Raw expense data to be processed for utility mapping */
    private array $expenseData = [];

    public function __construct(
        private readonly AppfolioClient $appfolioClient
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
        $this->expenseData = [];

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

        // Fetch data from AppFolio Reports API V2
        $data = match ($resourceType) {
            'properties' => $this->appfolioClient->getPropertyDirectory($params),
            'units' => $this->appfolioClient->getUnitDirectory($params),
            'vendors' => $this->appfolioClient->getVendorDirectory($params),
            'work_orders' => $this->appfolioClient->getWorkOrderReport($params),
            'expenses' => $this->appfolioClient->getExpenseRegister($params),
            'rent_roll' => $this->appfolioClient->getRentRoll($params),
            'delinquency' => $this->appfolioClient->getDelinquency($params),
            default => throw new \InvalidArgumentException("Unknown resource type: {$resourceType}"),
        };

        // Store raw data and normalize
        $this->processItems($resourceType, $data);

        // Finish tracking and save metrics
        $this->resourceTrackers[$resourceType] = $this->currentTracker;
        $this->currentTracker->finish();
        $this->currentTracker = null;
    }

    /**
     * Build query parameters based on sync mode and resource type.
     *
     * Different resource types require different parameters:
     * - expenses: requires from_date and to_date
     * - work_orders: requires from_date and to_date
     * - others: use modified_since for incremental sync
     */
    private function buildQueryParams(string $resourceType = ''): array
    {
        $params = [
            'per_page' => config('appfolio.sync.batch_size', 100),
        ];

        // Resources that require date range parameters
        $dateRangeResources = ['expenses', 'work_orders'];

        if (in_array($resourceType, $dateRangeResources, true)) {
            // For date range resources, always use from_date and to_date
            if ($this->syncRun->mode === 'incremental') {
                $days = config('appfolio.sync.incremental_days', 7);
                $params['from_date'] = now()->subDays($days)->format('Y-m-d');
            } else {
                // Full sync: look back configured number of days
                $days = config('appfolio.sync.full_sync_lookback_days', 365);
                $params['from_date'] = now()->subDays($days)->format('Y-m-d');
            }
            $params['to_date'] = now()->format('Y-m-d');
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

        // Collect expense data for utility expense processing
        if ($resourceType === 'expenses') {
            $this->expenseData = array_merge($this->expenseData, $items);
        }

        // Prefetch related entities to avoid N+1 queries
        $this->prefetchRelatedEntities($resourceType, $items);

        foreach ($items as $item) {
            try {
                $result = null;

                DB::transaction(function () use ($resourceType, $item, &$result) {
                    // Store raw event
                    $this->storeRawEvent($resourceType, $item);

                    // Normalize and upsert - returns true if created, false if updated, null if skipped
                    $result = $this->normalizeAndUpsert($resourceType, $item);
                });

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

        // Handle pagination if present
        // TODO: Adjust based on actual AppFolio pagination structure
        if (isset($data['next_page_url']) || isset($data['meta']['next_cursor'])) {
            $this->handlePagination($resourceType, $data);
        }
    }

    /**
     * Prefetch related entities to avoid N+1 query problems.
     */
    private function prefetchRelatedEntities(string $resourceType, array $items): void
    {
        match ($resourceType) {
            'units' => $this->prefetchProperties($items),
            'leases' => $this->prefetchUnitsAndPeople($items),
            'ledger_transactions', 'work_orders' => $this->prefetchPropertiesAndUnits($items),
            default => null,
        };
    }

    /**
     * Prefetch properties for unit processing.
     */
    private function prefetchProperties(array $items): void
    {
        $externalIds = collect($items)
            ->map(fn ($item) => (string) ($item['property_id'] ?? $item['property']['id'] ?? null))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($externalIds)) {
            return;
        }

        Property::whereIn('external_id', $externalIds)
            ->pluck('id', 'external_id')
            ->each(fn ($id, $externalId) => $this->propertyCache[$externalId] = $id);
    }

    /**
     * Prefetch units and people for lease processing.
     */
    private function prefetchUnitsAndPeople(array $items): void
    {
        $unitExternalIds = collect($items)
            ->map(fn ($item) => (string) ($item['unit_id'] ?? $item['unit']['id'] ?? null))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $personExternalIds = collect($items)
            ->map(fn ($item) => (string) ($item['tenant_id'] ?? $item['person_id'] ?? $item['resident_id'] ?? null))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (! empty($unitExternalIds)) {
            Unit::whereIn('external_id', $unitExternalIds)
                ->pluck('id', 'external_id')
                ->each(fn ($id, $externalId) => $this->unitCache[$externalId] = $id);
        }

        if (! empty($personExternalIds)) {
            Person::whereIn('external_id', $personExternalIds)
                ->pluck('id', 'external_id')
                ->each(fn ($id, $externalId) => $this->personCache[$externalId] = $id);
        }
    }

    /**
     * Prefetch properties and units for transaction/work order processing.
     */
    private function prefetchPropertiesAndUnits(array $items): void
    {
        $propertyExternalIds = collect($items)
            ->map(fn ($item) => (string) ($item['property_id'] ?? $item['property']['id'] ?? null))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $unitExternalIds = collect($items)
            ->map(fn ($item) => (string) ($item['unit_id'] ?? $item['unit']['id'] ?? null))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (! empty($propertyExternalIds)) {
            Property::whereIn('external_id', $propertyExternalIds)
                ->pluck('id', 'external_id')
                ->each(fn ($id, $externalId) => $this->propertyCache[$externalId] = $id);
        }

        if (! empty($unitExternalIds)) {
            Unit::whereIn('external_id', $unitExternalIds)
                ->pluck('id', 'external_id')
                ->each(fn ($id, $externalId) => $this->unitCache[$externalId] = $id);
        }
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

        RawAppfolioEvent::create([
            'sync_run_id' => $this->syncRun->id,
            'resource_type' => $resourceType,
            'external_id' => $externalId,
            'payload_json' => $item,
            'pulled_at' => now(),
        ]);
    }

    /**
     * Extract the external ID from an item based on resource type.
     *
     * AppFolio Reports API V2 uses different ID field names:
     * - property_directory: property_id
     * - unit_directory: unit_id
     * - vendor_directory: vendor_id
     * - work_order: work_order_id
     * - expense_register: expense_id
     * - rent_roll: lease_id or unit_id
     * - delinquency: unit_id
     */
    private function extractExternalId(string $resourceType, array $item): ?string
    {
        $idField = match ($resourceType) {
            'properties' => 'property_id',
            'units' => 'unit_id',
            'vendors' => 'vendor_id',
            'work_orders' => 'work_order_id',
            'expenses' => 'expense_id',
            'rent_roll' => 'lease_id',
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
            'people' => $this->upsertPerson($item),
            'leases' => $this->upsertLease($item),
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

        // Map AppFolio fields to our schema
        // created_at = when the work order was created
        // completed_on = when the work was completed
        // job_description = main description of the work
        $data = [
            'property_id' => $propertyId,
            'unit_id' => $unitId,
            'opened_at' => $item['created_at'] ?? now(),
            'closed_at' => $item['completed_on'] ?? $item['work_completed_on'] ?? null,
            'status' => $this->mapWorkOrderStatus($item['status'] ?? 'open'),
            'priority' => $this->mapWorkOrderPriority($item['priority'] ?? 'normal'),
            'category' => $item['work_order_type'] ?? $item['work_order_issue'] ?? null,
            'description' => $item['job_description'] ?? $item['service_request_description'] ?? $item['instructions'] ?? null,
        ];

        $externalId = (string) ($item['work_order_id'] ?? $item['id']);

        $workOrder = WorkOrder::updateOrCreate(
            ['external_id' => $externalId],
            $data
        );

        return $workOrder->wasRecentlyCreated;
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
     * Handle pagination for large result sets.
     *
     * TODO: Implement based on actual AppFolio pagination structure.
     */
    private function handlePagination(string $resourceType, array $data): void
    {
        // This is a placeholder implementation
        // TODO: Implement actual pagination handling based on AppFolio API
        Log::info('Pagination detected but not yet implemented', [
            'resource' => $resourceType,
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
     * Process collected expense data to create utility expense records.
     */
    private function processUtilityExpenses(): void
    {
        if (empty($this->expenseData)) {
            return;
        }

        try {
            /** @var UtilityExpenseService $utilityExpenseService */
            $utilityExpenseService = App::make(UtilityExpenseService::class);
            $stats = $utilityExpenseService->processExpenses($this->expenseData);

            Log::info('Utility expenses processed during sync', [
                'sync_run_id' => $this->syncRun->id,
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
