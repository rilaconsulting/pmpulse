<?php

namespace Tests\Unit;

use App\Models\Property;
use App\Models\Setting;
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

        // Create AppFolio settings
        Setting::set('appfolio', 'client_id', 'test-client-id');
        Setting::set('appfolio', 'client_secret', 'test-client-secret', encrypted: true);
        Setting::set('appfolio', 'database', 'testdb');
        Setting::set('appfolio', 'status', 'configured');

        // Create sync run
        $this->syncRun = SyncRun::create([
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
                'results' => [
                    [
                        'property_id' => 123,
                        'property_address' => '123 Main St Test City, CA 90210',
                        'property_street' => '123 Main St',
                        'property_city' => 'Test City',
                        'property_state' => 'CA',
                        'property_zip' => '90210',
                        'visibility' => 'Active',
                    ],
                ],
            ], 200),
        ]);

        $this->service->startSync($this->syncRun);
        $this->service->processResource('properties');
        $this->service->completeSync();

        $this->assertDatabaseHas('properties', [
            'external_id' => '123',
            'name' => '123 Main St Test City, CA 90210',
            'city' => 'Test City',
        ]);
    }

    public function test_upsert_updates_existing_property(): void
    {
        // Create existing property
        Property::create([
            'external_id' => '123',
            'name' => 'Old Name',
            'city' => 'Old City',
        ]);

        Http::fake([
            '*' => Http::response([
                'results' => [
                    [
                        'property_id' => 123,
                        'property_address' => 'Updated Name',
                        'property_city' => 'Updated City',
                        'visibility' => 'Active',
                    ],
                ],
            ], 200),
        ]);

        $this->service->startSync($this->syncRun);
        $this->service->processResource('properties');
        $this->service->completeSync();

        $this->assertDatabaseCount('properties', 1);
        $this->assertDatabaseHas('properties', [
            'external_id' => '123',
            'name' => 'Updated Name',
            'city' => 'Updated City',
        ]);
    }

    public function test_stores_raw_event(): void
    {
        Http::fake([
            '*' => Http::response([
                'results' => [
                    ['property_id' => 123, 'property_address' => 'Test Property'],
                ],
            ], 200),
        ]);

        $this->service->startSync($this->syncRun);
        $this->service->processResource('properties');
        $this->service->completeSync();

        $this->assertDatabaseHas('raw_appfolio_events', [
            'sync_run_id' => $this->syncRun->id,
            'resource_type' => 'properties',
            'external_id' => '123',
        ]);
    }

    public function test_upsert_creates_unit_with_property_relationship(): void
    {
        // Create property first
        $property = Property::create([
            'external_id' => '123',
            'name' => 'Test Property',
        ]);

        Http::fake([
            '*' => Http::response([
                'results' => [
                    [
                        'unit_id' => 456,
                        'property_id' => 123,
                        'unit_name' => '#101',
                        'bedrooms' => 2,
                        'status' => 'vacant',
                        'visibility' => 'Active',
                    ],
                ],
            ], 200),
        ]);

        $this->service->startSync($this->syncRun);
        $this->service->processResource('units');
        $this->service->completeSync();

        $this->assertDatabaseHas('units', [
            'external_id' => '456',
            'property_id' => $property->id,
            'unit_number' => '#101',
            'bedrooms' => 2,
            'status' => 'vacant',
        ]);
    }

    public function test_maps_unit_status_correctly(): void
    {
        Property::create([
            'external_id' => '123',
            'name' => 'Test Property',
        ]);

        Http::fake([
            '*' => Http::response([
                'results' => [
                    ['unit_id' => 1, 'property_id' => 123, 'unit_name' => '1', 'status' => 'occupied', 'visibility' => 'Active'],
                    ['unit_id' => 2, 'property_id' => 123, 'unit_name' => '2', 'status' => 'available', 'visibility' => 'Active'],
                    ['unit_id' => 3, 'property_id' => 123, 'unit_name' => '3', 'status' => 'maintenance', 'visibility' => 'Active'],
                ],
            ], 200),
        ]);

        $this->service->startSync($this->syncRun);
        $this->service->processResource('units');
        $this->service->completeSync();

        $this->assertDatabaseHas('units', ['external_id' => '1', 'status' => 'occupied']);
        $this->assertDatabaseHas('units', ['external_id' => '2', 'status' => 'vacant']);
        $this->assertDatabaseHas('units', ['external_id' => '3', 'status' => 'not_ready']);
    }

    public function test_sync_run_marked_as_completed(): void
    {
        Http::fake([
            '*' => Http::response(['results' => []], 200),
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
            '*' => Http::response(['results' => []], 200),
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
            'mode' => 'incremental',
            'status' => 'pending',
            'started_at' => now(),
        ]);

        Http::fake([
            '*' => Http::response(['results' => []], 200),
        ]);

        $this->service->startSync($incrementalSyncRun);
        $this->service->processResource('properties');

        // V2 API uses POST with JSON body, so check the body for modified_since
        Http::assertSent(function ($request) {
            // For POST requests, check if modified_since is in the JSON body
            $body = json_decode($request->body(), true);

            return isset($body['modified_since']);
        });
    }
}
