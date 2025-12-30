<?php

namespace Tests\Unit;

use App\Models\AppfolioConnection;
use App\Models\Property;
use App\Models\SyncRun;
use App\Services\IngestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IngestionServiceTest extends TestCase
{
    use RefreshDatabase;

    private IngestionService $service;

    private SyncRun $syncRun;

    protected function setUp(): void
    {
        parent::setUp();

        // Create connection
        $connection = AppfolioConnection::create([
            'name' => 'Test Connection',
            'client_id' => 'test-client-id',
            'client_secret_encrypted' => encrypt('test-client-secret'),
            'api_base_url' => 'https://api.appfolio.test',
            'status' => 'configured',
        ]);

        // Create sync run
        $this->syncRun = SyncRun::create([
            'appfolio_connection_id' => $connection->id,
            'mode' => 'full',
            'status' => 'pending',
            'started_at' => now(),
        ]);

        $this->service = app(IngestionService::class);
    }

    public function test_upsert_creates_new_property(): void
    {
        Http::fake([
            '*' => Http::response([
                'data' => [
                    [
                        'id' => 'prop-123',
                        'name' => 'Test Property',
                        'address' => '123 Main St',
                        'city' => 'Test City',
                        'state' => 'CA',
                        'zip' => '90210',
                    ],
                ],
            ], 200),
        ]);

        $this->service->startSync($this->syncRun);
        $this->service->processResource('properties');
        $this->service->completeSync();

        $this->assertDatabaseHas('properties', [
            'external_id' => 'prop-123',
            'name' => 'Test Property',
            'city' => 'Test City',
        ]);
    }

    public function test_upsert_updates_existing_property(): void
    {
        // Create existing property
        Property::create([
            'external_id' => 'prop-123',
            'name' => 'Old Name',
            'city' => 'Old City',
        ]);

        Http::fake([
            '*' => Http::response([
                'data' => [
                    [
                        'id' => 'prop-123',
                        'name' => 'Updated Name',
                        'city' => 'Updated City',
                    ],
                ],
            ], 200),
        ]);

        $this->service->startSync($this->syncRun);
        $this->service->processResource('properties');
        $this->service->completeSync();

        $this->assertDatabaseCount('properties', 1);
        $this->assertDatabaseHas('properties', [
            'external_id' => 'prop-123',
            'name' => 'Updated Name',
            'city' => 'Updated City',
        ]);
    }

    public function test_stores_raw_event(): void
    {
        Http::fake([
            '*' => Http::response([
                'data' => [
                    ['id' => 'prop-123', 'name' => 'Test Property'],
                ],
            ], 200),
        ]);

        $this->service->startSync($this->syncRun);
        $this->service->processResource('properties');
        $this->service->completeSync();

        $this->assertDatabaseHas('raw_appfolio_events', [
            'sync_run_id' => $this->syncRun->id,
            'resource_type' => 'properties',
            'external_id' => 'prop-123',
        ]);
    }

    public function test_upsert_creates_unit_with_property_relationship(): void
    {
        // Create property first
        $property = Property::create([
            'external_id' => 'prop-123',
            'name' => 'Test Property',
        ]);

        Http::fake([
            '*' => Http::response([
                'data' => [
                    [
                        'id' => 'unit-456',
                        'property_id' => 'prop-123',
                        'unit_number' => '101',
                        'bedrooms' => 2,
                        'status' => 'vacant',
                    ],
                ],
            ], 200),
        ]);

        $this->service->startSync($this->syncRun);
        $this->service->processResource('units');
        $this->service->completeSync();

        $this->assertDatabaseHas('units', [
            'external_id' => 'unit-456',
            'property_id' => $property->id,
            'unit_number' => '101',
            'bedrooms' => 2,
            'status' => 'vacant',
        ]);
    }

    public function test_maps_unit_status_correctly(): void
    {
        Property::create([
            'external_id' => 'prop-123',
            'name' => 'Test Property',
        ]);

        Http::fake([
            '*' => Http::response([
                'data' => [
                    ['id' => 'unit-1', 'property_id' => 'prop-123', 'unit_number' => '1', 'status' => 'occupied'],
                    ['id' => 'unit-2', 'property_id' => 'prop-123', 'unit_number' => '2', 'status' => 'available'],
                    ['id' => 'unit-3', 'property_id' => 'prop-123', 'unit_number' => '3', 'status' => 'maintenance'],
                ],
            ], 200),
        ]);

        $this->service->startSync($this->syncRun);
        $this->service->processResource('units');
        $this->service->completeSync();

        $this->assertDatabaseHas('units', ['external_id' => 'unit-1', 'status' => 'occupied']);
        $this->assertDatabaseHas('units', ['external_id' => 'unit-2', 'status' => 'vacant']);
        $this->assertDatabaseHas('units', ['external_id' => 'unit-3', 'status' => 'not_ready']);
    }

    public function test_sync_run_marked_as_completed(): void
    {
        Http::fake([
            '*' => Http::response(['data' => []], 200),
        ]);

        $this->service->startSync($this->syncRun);
        $this->service->processAll();
        $this->service->completeSync();

        $this->syncRun->refresh();

        $this->assertEquals('completed', $this->syncRun->status);
        $this->assertNotNull($this->syncRun->ended_at);
    }

    public function test_sync_run_marked_as_running_when_started(): void
    {
        $this->service->startSync($this->syncRun);

        $this->syncRun->refresh();

        $this->assertEquals('running', $this->syncRun->status);
    }

    public function test_handles_empty_response(): void
    {
        Http::fake([
            '*' => Http::response(['data' => []], 200),
        ]);

        $this->service->startSync($this->syncRun);
        $this->service->processResource('properties');
        $this->service->completeSync();

        $this->assertEquals(0, $this->service->getProcessedCount());
        $this->assertEquals(0, $this->service->getErrorCount());
    }

    public function test_incremental_sync_includes_modified_since_param(): void
    {
        // Create incremental sync run
        $incrementalSyncRun = SyncRun::create([
            'appfolio_connection_id' => $this->syncRun->appfolio_connection_id,
            'mode' => 'incremental',
            'status' => 'pending',
            'started_at' => now(),
        ]);

        Http::fake([
            '*' => Http::response(['data' => []], 200),
        ]);

        $this->service->startSync($incrementalSyncRun);
        $this->service->processResource('properties');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'modified_since');
        });
    }
}
