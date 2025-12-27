<?php

namespace App\Services;

use App\Models\Lease;
use App\Models\LedgerTransaction;
use App\Models\Person;
use App\Models\Property;
use App\Models\RawAppfolioEvent;
use App\Models\SyncRun;
use App\Models\Unit;
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
                Log::error("Failed to process resource", [
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
        Log::info("Processing resource type", ['type' => $resourceType]);

        $params = $this->buildQueryParams();

        // Fetch data from AppFolio
        $data = match ($resourceType) {
            'properties' => $this->appfolioClient->getProperties($params),
            'units' => $this->appfolioClient->getUnits($params),
            'people' => $this->appfolioClient->getPeople($params),
            'leases' => $this->appfolioClient->getLeases($params),
            'ledger_transactions' => $this->appfolioClient->getLedgerTransactions($params),
            'work_orders' => $this->appfolioClient->getWorkOrders($params),
            default => throw new \InvalidArgumentException("Unknown resource type: {$resourceType}"),
        };

        // Store raw data and normalize
        $this->processItems($resourceType, $data);
    }

    /**
     * Build query parameters based on sync mode.
     */
    private function buildQueryParams(): array
    {
        $params = [
            'per_page' => config('appfolio.sync.batch_size', 100),
        ];

        if ($this->syncRun->mode === 'incremental') {
            // For incremental sync, only fetch recently modified records
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
        // TODO: Adjust based on actual AppFolio API response structure
        // This assumes the response has a 'data' key with an array of items
        $items = $data['data'] ?? $data;

        if (! is_array($items)) {
            Log::warning("No items found for resource type", ['type' => $resourceType]);
            return;
        }

        foreach ($items as $item) {
            try {
                DB::transaction(function () use ($resourceType, $item) {
                    // Store raw event
                    $this->storeRawEvent($resourceType, $item);

                    // Normalize and upsert
                    $this->normalizeAndUpsert($resourceType, $item);
                });

                $this->processedCount++;
            } catch (\Exception $e) {
                $this->errorCount++;
                Log::error("Failed to process item", [
                    'type' => $resourceType,
                    'item' => $item,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Handle pagination if present
        // TODO: Adjust based on actual AppFolio pagination structure
        if (isset($data['next_page_url']) || isset($data['meta']['next_cursor'])) {
            $this->handlePagination($resourceType, $data);
        }
    }

    /**
     * Store raw API response for debugging and replay.
     */
    private function storeRawEvent(string $resourceType, array $item): void
    {
        // TODO: Adjust 'id' field name based on actual AppFolio response
        $externalId = $item['id'] ?? $item['external_id'] ?? uniqid();

        RawAppfolioEvent::create([
            'sync_run_id' => $this->syncRun->id,
            'resource_type' => $resourceType,
            'external_id' => (string) $externalId,
            'payload_json' => $item,
            'pulled_at' => now(),
        ]);
    }

    /**
     * Normalize and upsert data to the appropriate table.
     */
    private function normalizeAndUpsert(string $resourceType, array $item): void
    {
        match ($resourceType) {
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
     * TODO: Adjust field mappings based on actual AppFolio API response.
     * The field names below are placeholders.
     */
    private function upsertProperty(array $item): void
    {
        // Map AppFolio fields to our schema
        // TODO: Update these mappings when API documentation is available
        $data = [
            'name' => $item['name'] ?? $item['property_name'] ?? 'Unknown Property',
            'address_line1' => $item['address'] ?? $item['address_line1'] ?? $item['street_address'] ?? null,
            'address_line2' => $item['address2'] ?? $item['address_line2'] ?? $item['unit'] ?? null,
            'city' => $item['city'] ?? null,
            'state' => $item['state'] ?? $item['state_code'] ?? null,
            'zip' => $item['zip'] ?? $item['postal_code'] ?? $item['zip_code'] ?? null,
            'property_type' => $item['type'] ?? $item['property_type'] ?? 'residential',
            'unit_count' => $item['unit_count'] ?? $item['units_count'] ?? 0,
            'is_active' => $item['active'] ?? $item['is_active'] ?? true,
        ];

        $externalId = (string) ($item['id'] ?? $item['property_id']);

        Property::updateOrCreate(
            ['external_id' => $externalId],
            $data
        );
    }

    /**
     * Upsert a unit record.
     *
     * TODO: Adjust field mappings based on actual AppFolio API response.
     */
    private function upsertUnit(array $item): void
    {
        // First, ensure the property exists
        $propertyExternalId = (string) ($item['property_id'] ?? $item['property']['id'] ?? null);
        $property = Property::where('external_id', $propertyExternalId)->first();

        if (! $property) {
            Log::warning("Property not found for unit", [
                'property_external_id' => $propertyExternalId,
                'unit_data' => $item,
            ]);
            return;
        }

        // Map AppFolio fields to our schema
        // TODO: Update these mappings when API documentation is available
        $data = [
            'property_id' => $property->id,
            'unit_number' => $item['unit_number'] ?? $item['number'] ?? $item['name'] ?? 'Unknown',
            'sqft' => $item['sqft'] ?? $item['square_feet'] ?? $item['size'] ?? null,
            'bedrooms' => $item['bedrooms'] ?? $item['beds'] ?? $item['bedroom_count'] ?? null,
            'bathrooms' => $item['bathrooms'] ?? $item['baths'] ?? $item['bathroom_count'] ?? null,
            'status' => $this->mapUnitStatus($item['status'] ?? $item['occupancy_status'] ?? 'vacant'),
            'market_rent' => $item['market_rent'] ?? $item['rent'] ?? $item['asking_rent'] ?? null,
            'is_active' => $item['active'] ?? $item['is_active'] ?? true,
        ];

        $externalId = (string) ($item['id'] ?? $item['unit_id']);

        Unit::updateOrCreate(
            ['external_id' => $externalId],
            $data
        );
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
     */
    private function upsertPerson(array $item): void
    {
        // Map AppFolio fields to our schema
        // TODO: Update these mappings when API documentation is available
        $data = [
            'name' => $item['name'] ?? trim(($item['first_name'] ?? '') . ' ' . ($item['last_name'] ?? '')),
            'email' => $item['email'] ?? $item['primary_email'] ?? null,
            'phone' => $item['phone'] ?? $item['primary_phone'] ?? $item['mobile'] ?? null,
            'type' => $this->mapPersonType($item['type'] ?? $item['person_type'] ?? 'tenant'),
            'is_active' => $item['active'] ?? $item['is_active'] ?? true,
        ];

        $externalId = (string) ($item['id'] ?? $item['person_id']);

        Person::updateOrCreate(
            ['external_id' => $externalId],
            $data
        );
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
     */
    private function upsertLease(array $item): void
    {
        // Look up unit and person
        $unitExternalId = (string) ($item['unit_id'] ?? $item['unit']['id'] ?? null);
        $unit = Unit::where('external_id', $unitExternalId)->first();

        $personExternalId = (string) ($item['tenant_id'] ?? $item['person_id'] ?? $item['resident_id'] ?? null);
        $person = Person::where('external_id', $personExternalId)->first();

        if (! $unit) {
            Log::warning("Unit not found for lease", ['unit_external_id' => $unitExternalId]);
            return;
        }

        // Map AppFolio fields to our schema
        // TODO: Update these mappings when API documentation is available
        $data = [
            'unit_id' => $unit->id,
            'person_id' => $person?->id,
            'start_date' => $item['start_date'] ?? $item['lease_start'] ?? $item['move_in_date'] ?? now(),
            'end_date' => $item['end_date'] ?? $item['lease_end'] ?? $item['move_out_date'] ?? null,
            'rent' => $item['rent'] ?? $item['monthly_rent'] ?? $item['rent_amount'] ?? 0,
            'security_deposit' => $item['security_deposit'] ?? $item['deposit'] ?? null,
            'status' => $this->mapLeaseStatus($item['status'] ?? $item['lease_status'] ?? 'active'),
        ];

        $externalId = (string) ($item['id'] ?? $item['lease_id']);

        Lease::updateOrCreate(
            ['external_id' => $externalId],
            $data
        );
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
     */
    private function upsertLedgerTransaction(array $item): void
    {
        // Look up property and unit
        $propertyExternalId = (string) ($item['property_id'] ?? $item['property']['id'] ?? null);
        $property = $propertyExternalId ? Property::where('external_id', $propertyExternalId)->first() : null;

        $unitExternalId = (string) ($item['unit_id'] ?? $item['unit']['id'] ?? null);
        $unit = $unitExternalId ? Unit::where('external_id', $unitExternalId)->first() : null;

        // Map AppFolio fields to our schema
        // TODO: Update these mappings when API documentation is available
        $data = [
            'property_id' => $property?->id,
            'unit_id' => $unit?->id,
            'date' => $item['date'] ?? $item['transaction_date'] ?? $item['posted_date'] ?? now(),
            'type' => $this->mapTransactionType($item['type'] ?? $item['transaction_type'] ?? 'charge'),
            'amount' => abs($item['amount'] ?? $item['transaction_amount'] ?? 0),
            'category' => $item['category'] ?? $item['charge_type'] ?? $item['gl_account'] ?? null,
            'description' => $item['description'] ?? $item['memo'] ?? $item['notes'] ?? null,
            'balance' => $item['balance'] ?? $item['running_balance'] ?? null,
        ];

        $externalId = (string) ($item['id'] ?? $item['transaction_id']);

        LedgerTransaction::updateOrCreate(
            ['external_id' => $externalId],
            $data
        );
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
     * TODO: Adjust field mappings based on actual AppFolio API response.
     */
    private function upsertWorkOrder(array $item): void
    {
        // Look up property and unit
        $propertyExternalId = (string) ($item['property_id'] ?? $item['property']['id'] ?? null);
        $property = $propertyExternalId ? Property::where('external_id', $propertyExternalId)->first() : null;

        $unitExternalId = (string) ($item['unit_id'] ?? $item['unit']['id'] ?? null);
        $unit = $unitExternalId ? Unit::where('external_id', $unitExternalId)->first() : null;

        // Map AppFolio fields to our schema
        // TODO: Update these mappings when API documentation is available
        $data = [
            'property_id' => $property?->id,
            'unit_id' => $unit?->id,
            'opened_at' => $item['opened_at'] ?? $item['created_at'] ?? $item['date_created'] ?? now(),
            'closed_at' => $item['closed_at'] ?? $item['completed_at'] ?? $item['date_completed'] ?? null,
            'status' => $this->mapWorkOrderStatus($item['status'] ?? 'open'),
            'priority' => $this->mapWorkOrderPriority($item['priority'] ?? 'normal'),
            'category' => $item['category'] ?? $item['work_type'] ?? $item['type'] ?? null,
            'description' => $item['description'] ?? $item['details'] ?? $item['notes'] ?? null,
        ];

        $externalId = (string) ($item['id'] ?? $item['work_order_id']);

        WorkOrder::updateOrCreate(
            ['external_id' => $externalId],
            $data
        );
    }

    /**
     * Map AppFolio work order status to our status values.
     *
     * TODO: Adjust mappings based on actual AppFolio status values.
     */
    private function mapWorkOrderStatus(string $status): string
    {
        return match (strtolower($status)) {
            'open', 'new', 'pending' => 'open',
            'in_progress', 'assigned', 'working' => 'in_progress',
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
        Log::info("Pagination detected but not yet implemented", [
            'resource' => $resourceType,
        ]);
    }

    /**
     * Complete the sync run.
     */
    public function completeSync(): void
    {
        if ($this->errorCount > 0) {
            $errorSummary = implode("\n", array_slice($this->errors, 0, 10));
            if (count($this->errors) > 10) {
                $errorSummary .= "\n... and " . (count($this->errors) - 10) . " more errors";
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
