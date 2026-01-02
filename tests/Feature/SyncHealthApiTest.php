<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\SyncRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncHealthApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        // Create AppFolio settings
        Setting::set('appfolio', 'client_id', 'test-client');
        Setting::set('appfolio', 'client_secret', 'test-secret', encrypted: true);
        Setting::set('appfolio', 'database', 'testdb');
        Setting::set('appfolio', 'status', 'connected');
    }

    public function test_sync_health_endpoint_requires_authentication(): void
    {
        $response = $this->getJson('/api/sync/health');

        $response->assertStatus(401);
    }

    public function test_sync_health_returns_expected_structure(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/sync/health');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'connection' => ['status', 'last_success_at'],
                'lastRun',
                'lastSuccessAt',
                'period' => ['days', 'total_runs', 'success_count', 'failure_count', 'success_rate'],
                'chartData',
                'resourceTotals',
                'recentRuns',
                'recentErrors',
            ]);
    }

    public function test_sync_health_includes_connection_status(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/sync/health');

        $response->assertStatus(200)
            ->assertJsonPath('connection.status', 'connected');
    }

    public function test_sync_health_includes_last_run_details(): void
    {
        // Create a sync run
        $syncRun = SyncRun::create([
            'mode' => 'full',
            'status' => 'completed',
            'started_at' => now()->subHour(),
            'ended_at' => now()->subMinutes(55),
            'resources_synced' => 100,
            'errors_count' => 0,
            'metadata' => [
                'resource_metrics' => [
                    'properties' => ['created' => 5, 'updated' => 10, 'skipped' => 0, 'errors' => 0, 'duration_ms' => 1234],
                ],
            ],
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/sync/health');

        $response->assertStatus(200)
            ->assertJsonPath('lastRun.id', $syncRun->id)
            ->assertJsonPath('lastRun.status', 'completed')
            ->assertJsonPath('lastRun.mode', 'full')
            ->assertJsonPath('lastRun.resources_synced', 100);
    }

    public function test_sync_health_calculates_success_rate(): void
    {
        // Create 8 completed and 2 failed runs
        for ($i = 0; $i < 8; $i++) {
            SyncRun::create([
                'mode' => 'incremental',
                'status' => 'completed',
                'started_at' => now()->subHours($i),
                'ended_at' => now()->subHours($i)->addMinutes(5),
                'resources_synced' => 50,
            ]);
        }

        for ($i = 0; $i < 2; $i++) {
            SyncRun::create([
                'mode' => 'incremental',
                'status' => 'failed',
                'started_at' => now()->subDays(1)->subHours($i),
                'ended_at' => now()->subDays(1)->subHours($i)->addMinutes(5),
                'errors_count' => 1,
            ]);
        }

        $response = $this->actingAs($this->user)->getJson('/api/sync/health');

        $response->assertStatus(200)
            ->assertJsonPath('period.total_runs', 10)
            ->assertJsonPath('period.success_count', 8)
            ->assertJsonPath('period.failure_count', 2);

        // Success rate is 80% (8/10)
        $this->assertEquals(80, $response->json('period.success_rate'));
    }

    public function test_sync_health_aggregates_resource_metrics(): void
    {
        SyncRun::create([
            'mode' => 'full',
            'status' => 'completed',
            'started_at' => now()->subHour(),
            'ended_at' => now()->subMinutes(55),
            'resources_synced' => 100,
            'metadata' => [
                'resource_metrics' => [
                    'properties' => ['created' => 5, 'updated' => 10, 'skipped' => 0, 'errors' => 0],
                    'units' => ['created' => 15, 'updated' => 20, 'skipped' => 2, 'errors' => 1],
                ],
            ],
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/sync/health');

        $response->assertStatus(200);

        $data = $response->json();
        $this->assertArrayHasKey('properties', $data['resourceTotals']);
        $this->assertArrayHasKey('units', $data['resourceTotals']);
    }

    public function test_sync_health_includes_recent_errors(): void
    {
        SyncRun::create([
            'mode' => 'full',
            'status' => 'completed',
            'started_at' => now()->subHour(),
            'ended_at' => now()->subMinutes(55),
            'resources_synced' => 100,
            'errors_count' => 2,
            'metadata' => [
                'resource_errors' => [
                    'properties' => [
                        ['message' => 'Invalid property data', 'timestamp' => now()->toIso8601String()],
                    ],
                    'units' => [
                        ['message' => 'Missing property reference', 'timestamp' => now()->toIso8601String()],
                    ],
                ],
            ],
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/sync/health');

        $response->assertStatus(200);

        $data = $response->json();
        $this->assertCount(2, $data['recentErrors']);
    }

    public function test_sync_health_respects_days_parameter(): void
    {
        // Create a run from 10 days ago (should be excluded with days=7)
        SyncRun::create([
            'mode' => 'full',
            'status' => 'completed',
            'started_at' => now()->subDays(10),
            'ended_at' => now()->subDays(10)->addMinutes(5),
            'resources_synced' => 100,
        ]);

        // Create a run from 3 days ago (should be included)
        SyncRun::create([
            'mode' => 'incremental',
            'status' => 'completed',
            'started_at' => now()->subDays(3),
            'ended_at' => now()->subDays(3)->addMinutes(5),
            'resources_synced' => 50,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/sync/health?days=7');

        $response->assertStatus(200)
            ->assertJsonPath('period.total_runs', 1);
    }
}
