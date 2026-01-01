<?php

namespace Tests\Unit;

use App\Models\AppfolioConnection;
use App\Models\SyncRun;
use App\Services\ResourceSyncTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResourceSyncTrackerTest extends TestCase
{
    use RefreshDatabase;

    private SyncRun $syncRun;

    protected function setUp(): void
    {
        parent::setUp();

        $connection = AppfolioConnection::create([
            'name' => 'Test Connection',
            'client_id' => 'test-client',
            'client_secret_encrypted' => encrypt('test-secret'),
            'api_base_url' => 'https://api.appfolio.test',
            'status' => 'configured',
        ]);

        $this->syncRun = SyncRun::create([
            'appfolio_connection_id' => $connection->id,
            'mode' => 'full',
            'status' => 'running',
            'started_at' => now(),
        ]);
    }

    public function test_tracks_created_records(): void
    {
        $tracker = new ResourceSyncTracker($this->syncRun, 'properties');

        $tracker->recordCreated();
        $tracker->recordCreated();
        $tracker->recordCreated();

        $metrics = $tracker->getMetrics();

        $this->assertEquals(3, $metrics['created']);
        $this->assertEquals(0, $metrics['updated']);
        $this->assertEquals(0, $metrics['skipped']);
        $this->assertEquals(0, $metrics['errors']);
    }

    public function test_tracks_updated_records(): void
    {
        $tracker = new ResourceSyncTracker($this->syncRun, 'units');

        $tracker->recordUpdated();
        $tracker->recordUpdated();

        $metrics = $tracker->getMetrics();

        $this->assertEquals(0, $metrics['created']);
        $this->assertEquals(2, $metrics['updated']);
    }

    public function test_tracks_skipped_records(): void
    {
        $tracker = new ResourceSyncTracker($this->syncRun, 'leases');

        $tracker->recordSkipped('Missing unit');
        $tracker->recordSkipped('Missing tenant');

        $metrics = $tracker->getMetrics();

        $this->assertEquals(2, $metrics['skipped']);
    }

    public function test_tracks_errors(): void
    {
        $tracker = new ResourceSyncTracker($this->syncRun, 'work_orders');

        $tracker->recordError('Database error', ['item_id' => '123']);
        $tracker->recordError('Validation failed');

        $metrics = $tracker->getMetrics();

        $this->assertEquals(2, $metrics['errors']);
        $this->assertTrue($tracker->hasErrors());
        $this->assertCount(2, $tracker->getErrorMessages());
        $this->assertContains('Database error', $tracker->getErrorMessages());
    }

    public function test_has_errors_returns_false_when_no_errors(): void
    {
        $tracker = new ResourceSyncTracker($this->syncRun, 'properties');

        $tracker->recordCreated();

        $this->assertFalse($tracker->hasErrors());
    }

    public function test_get_processed_count_includes_created_and_updated(): void
    {
        $tracker = new ResourceSyncTracker($this->syncRun, 'properties');

        $tracker->recordCreated();
        $tracker->recordCreated();
        $tracker->recordUpdated();
        $tracker->recordSkipped();

        $this->assertEquals(3, $tracker->getProcessedCount());
    }

    public function test_finish_saves_metrics_to_sync_run(): void
    {
        $tracker = new ResourceSyncTracker($this->syncRun, 'properties');

        $tracker->recordCreated();
        $tracker->recordCreated();
        $tracker->recordUpdated();
        $tracker->recordSkipped('Test skip');
        $tracker->recordError('Test error');

        $metrics = $tracker->finish();

        $this->syncRun->refresh();
        $resourceMetrics = $this->syncRun->getResourceMetrics();

        $this->assertArrayHasKey('properties', $resourceMetrics);
        $this->assertEquals(2, $resourceMetrics['properties']['created']);
        $this->assertEquals(1, $resourceMetrics['properties']['updated']);
        $this->assertEquals(1, $resourceMetrics['properties']['skipped']);
        $this->assertEquals(1, $resourceMetrics['properties']['errors']);
        $this->assertArrayHasKey('duration_ms', $resourceMetrics['properties']);
    }

    public function test_tracks_duration(): void
    {
        $tracker = new ResourceSyncTracker($this->syncRun, 'properties');

        // Small delay to ensure measurable duration
        usleep(10000); // 10ms

        $metrics = $tracker->getMetrics();

        $this->assertArrayHasKey('duration_ms', $metrics);
        $this->assertGreaterThan(0, $metrics['duration_ms']);
    }

    public function test_error_stored_in_sync_run(): void
    {
        $tracker = new ResourceSyncTracker($this->syncRun, 'units');

        $tracker->recordError('Connection timeout');

        $this->syncRun->refresh();
        $errors = $this->syncRun->getResourceErrors('units');

        $this->assertCount(1, $errors);
        $this->assertEquals('Connection timeout', $errors[0]['message']);
        $this->assertArrayHasKey('timestamp', $errors[0]);
    }
}
