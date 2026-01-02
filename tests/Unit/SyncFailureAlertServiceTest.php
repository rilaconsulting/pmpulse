<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\AppfolioConnection;
use App\Models\Setting;
use App\Models\SyncFailureAlert;
use App\Models\SyncRun;
use App\Models\User;
use App\Services\SyncFailureAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SyncFailureAlertServiceTest extends TestCase
{
    use RefreshDatabase;

    private SyncFailureAlertService $service;

    private AppfolioConnection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->service = new SyncFailureAlertService;

        $this->connection = AppfolioConnection::create([
            'name' => 'Test Connection',
            'client_id' => 'test-client',
            'client_secret_encrypted' => encrypt('test-secret'),
            'api_base_url' => 'https://api.appfolio.test',
            'status' => 'connected',
        ]);

        // Enable notifications via Setting
        Setting::set('features', 'notifications', true);

        // Create a user for recipients
        User::factory()->create();

        Notification::fake();
    }

    public function test_successful_sync_resets_failure_count(): void
    {
        // Create an existing alert with failures
        $alert = SyncFailureAlert::create([
            'appfolio_connection_id' => $this->connection->id,
            'consecutive_failures' => 2,
        ]);

        // Create a successful sync run
        $syncRun = SyncRun::create([
            'appfolio_connection_id' => $this->connection->id,
            'mode' => 'incremental',
            'status' => 'completed',
            'started_at' => now(),
            'ended_at' => now(),
        ]);

        $this->service->handleSyncCompleted($syncRun);

        $alert->refresh();
        $this->assertEquals(0, $alert->consecutive_failures);
    }

    public function test_failed_sync_increments_failure_count(): void
    {
        $syncRun = SyncRun::create([
            'appfolio_connection_id' => $this->connection->id,
            'mode' => 'incremental',
            'status' => 'failed',
            'started_at' => now(),
            'ended_at' => now(),
            'error_summary' => 'Connection timeout',
        ]);

        $this->service->handleSyncCompleted($syncRun);

        $alert = SyncFailureAlert::where('appfolio_connection_id', $this->connection->id)->first();
        $this->assertEquals(1, $alert->consecutive_failures);
    }

    public function test_alert_sent_after_threshold_reached(): void
    {
        // Set threshold to 3 via Setting
        Setting::set('alerts', 'failure_threshold', 3);

        // Create 2 failures first
        $alert = SyncFailureAlert::create([
            'appfolio_connection_id' => $this->connection->id,
            'consecutive_failures' => 2,
        ]);

        // Create the 3rd failure
        $syncRun = SyncRun::create([
            'appfolio_connection_id' => $this->connection->id,
            'mode' => 'incremental',
            'status' => 'failed',
            'started_at' => now(),
            'ended_at' => now(),
            'error_summary' => 'API error',
        ]);

        $this->service->handleSyncCompleted($syncRun);

        Notification::assertSentOnDemand(
            \App\Notifications\SyncFailureNotification::class
        );
    }

    public function test_no_alert_before_threshold(): void
    {
        Setting::set('alerts', 'failure_threshold', 3);

        $syncRun = SyncRun::create([
            'appfolio_connection_id' => $this->connection->id,
            'mode' => 'incremental',
            'status' => 'failed',
            'started_at' => now(),
            'ended_at' => now(),
        ]);

        $this->service->handleSyncCompleted($syncRun);

        Notification::assertNothingSent();
    }

    public function test_alert_rate_limited(): void
    {
        Setting::set('alerts', 'failure_threshold', 1);
        Setting::set('alerts', 'cooldown_minutes', 60);

        // Create an alert that was just sent
        $alert = SyncFailureAlert::create([
            'appfolio_connection_id' => $this->connection->id,
            'consecutive_failures' => 1,
            'last_alert_sent_at' => now(),
        ]);

        $syncRun = SyncRun::create([
            'appfolio_connection_id' => $this->connection->id,
            'mode' => 'incremental',
            'status' => 'failed',
            'started_at' => now(),
            'ended_at' => now(),
        ]);

        $this->service->handleSyncCompleted($syncRun);

        // Alert should be rate limited (no new notification)
        Notification::assertNothingSent();
    }

    public function test_alert_sent_after_cooldown(): void
    {
        Setting::set('alerts', 'failure_threshold', 1);
        Setting::set('alerts', 'cooldown_minutes', 60);

        // Create an alert that was sent 2 hours ago
        $alert = SyncFailureAlert::create([
            'appfolio_connection_id' => $this->connection->id,
            'consecutive_failures' => 1,
            'last_alert_sent_at' => now()->subMinutes(120),
        ]);

        $syncRun = SyncRun::create([
            'appfolio_connection_id' => $this->connection->id,
            'mode' => 'incremental',
            'status' => 'failed',
            'started_at' => now(),
            'ended_at' => now(),
        ]);

        $this->service->handleSyncCompleted($syncRun);

        // Alert should be sent after cooldown
        Notification::assertSentOnDemand(
            \App\Notifications\SyncFailureNotification::class
        );
    }

    public function test_acknowledged_alert_cleared_on_new_failure(): void
    {
        Setting::set('alerts', 'failure_threshold', 1);

        $user = User::first();

        // Create an acknowledged alert
        $alert = SyncFailureAlert::create([
            'appfolio_connection_id' => $this->connection->id,
            'consecutive_failures' => 5,
            'acknowledged_at' => now(),
            'acknowledged_by' => $user->id,
        ]);

        $syncRun = SyncRun::create([
            'appfolio_connection_id' => $this->connection->id,
            'mode' => 'incremental',
            'status' => 'failed',
            'started_at' => now(),
            'ended_at' => now(),
        ]);

        $this->service->handleSyncCompleted($syncRun);

        // New failure clears acknowledgment and sends new alert
        $alert->refresh();
        $this->assertNull($alert->acknowledged_at);
        $this->assertEquals(6, $alert->consecutive_failures);

        // Alert should be sent since acknowledgment was cleared
        Notification::assertSentOnDemand(
            \App\Notifications\SyncFailureNotification::class
        );
    }

    public function test_acknowledge_alert(): void
    {
        $user = User::first();
        $alert = SyncFailureAlert::create([
            'appfolio_connection_id' => $this->connection->id,
            'consecutive_failures' => 3,
        ]);

        $this->service->acknowledgeAlert($alert, $user);

        $alert->refresh();
        $this->assertNotNull($alert->acknowledged_at);
        $this->assertEquals($user->id, $alert->acknowledged_by);
    }

    public function test_get_active_alerts(): void
    {
        // Create an unacknowledged alert with failures
        SyncFailureAlert::create([
            'appfolio_connection_id' => $this->connection->id,
            'consecutive_failures' => 3,
        ]);

        $alerts = $this->service->getActiveAlerts();

        $this->assertCount(1, $alerts);
        $this->assertEquals(3, $alerts[0]['consecutive_failures']);
    }

    public function test_failure_details_stored(): void
    {
        $syncRun = SyncRun::create([
            'appfolio_connection_id' => $this->connection->id,
            'mode' => 'incremental',
            'status' => 'failed',
            'started_at' => now(),
            'ended_at' => now(),
            'error_summary' => 'Connection refused',
            'errors_count' => 5,
        ]);

        $this->service->handleSyncCompleted($syncRun);

        $alert = SyncFailureAlert::where('appfolio_connection_id', $this->connection->id)->first();
        $this->assertNotEmpty($alert->failure_details);
        $this->assertArrayHasKey('error', $alert->failure_details[0]['details']);
    }

    public function test_no_alert_when_notifications_disabled(): void
    {
        Setting::set('features', 'notifications', false);

        Setting::set('alerts', 'failure_threshold', 1);

        SyncFailureAlert::create([
            'appfolio_connection_id' => $this->connection->id,
            'consecutive_failures' => 5,
        ]);

        $syncRun = SyncRun::create([
            'appfolio_connection_id' => $this->connection->id,
            'mode' => 'incremental',
            'status' => 'failed',
            'started_at' => now(),
            'ended_at' => now(),
        ]);

        $this->service->handleSyncCompleted($syncRun);

        Notification::assertNothingSent();
    }
}
