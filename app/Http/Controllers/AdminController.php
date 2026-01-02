<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SaveConnectionRequest;
use App\Http\Requests\SaveSyncConfigurationRequest;
use App\Jobs\SyncAppfolioResourceJob;
use App\Models\AppfolioConnection;
use App\Models\Setting;
use App\Models\SyncRun;
use App\Services\BusinessHoursService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Inertia\Inertia;
use Inertia\Response;

class AdminController extends Controller
{
    /**
     * Available timezone options for business hours configuration.
     */
    private const TIMEZONES = [
        'America/Los_Angeles' => 'Pacific Time (PT)',
        'America/Denver' => 'Mountain Time (MT)',
        'America/Chicago' => 'Central Time (CT)',
        'America/New_York' => 'Eastern Time (ET)',
        'America/Anchorage' => 'Alaska Time (AKT)',
        'Pacific/Honolulu' => 'Hawaii Time (HT)',
    ];

    /**
     * Display the admin page.
     */
    public function index(BusinessHoursService $businessHoursService): Response
    {
        // Get current connection settings (mask the secret)
        $connection = AppfolioConnection::query()->first();

        // Get sync run history (last 20 runs)
        $syncHistory = SyncRun::query()
            ->latest('started_at')
            ->limit(20)
            ->get();

        // Get sync configuration from Settings
        $syncConfig = $this->getSyncConfiguration();

        return Inertia::render('Admin', [
            'connection' => $connection ? [
                'id' => $connection->id,
                'name' => $connection->name,
                'client_id' => $connection->client_id,
                'api_base_url' => $connection->api_base_url,
                'status' => $connection->status,
                'last_success_at' => $connection->last_success_at,
                'last_error' => $connection->last_error,
                'has_secret' => ! empty($connection->client_secret_encrypted),
            ] : null,
            'syncHistory' => $syncHistory->toArray(),
            'features' => [
                'incremental_sync' => Setting::isFeatureEnabled('incremental_sync', true),
                'notifications' => Setting::isFeatureEnabled('notifications', true),
            ],
            'syncConfiguration' => $syncConfig,
            'syncStatus' => $businessHoursService->getConfiguration(),
            'timezones' => self::TIMEZONES,
        ]);
    }

    /**
     * Get sync configuration from Settings model.
     */
    private function getSyncConfiguration(): array
    {
        return [
            'business_hours_enabled' => Setting::get('business_hours', 'enabled', true),
            'timezone' => Setting::get('business_hours', 'timezone', 'America/Los_Angeles'),
            'start_hour' => Setting::get('business_hours', 'start_hour', 9),
            'end_hour' => Setting::get('business_hours', 'end_hour', 17),
            'weekdays_only' => Setting::get('business_hours', 'weekdays_only', true),
            'business_hours_interval' => Setting::get('business_hours', 'business_hours_interval', 15),
            'off_hours_interval' => Setting::get('business_hours', 'off_hours_interval', 60),
            'full_sync_time' => Setting::get('sync', 'full_sync_time', '02:00'),
        ];
    }

    /**
     * Save AppFolio connection settings.
     */
    public function saveConnection(SaveConnectionRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $connection = AppfolioConnection::query()->first() ?? new AppfolioConnection;

        $connection->name = $validated['name'];
        $connection->client_id = $validated['client_id'];
        $connection->api_base_url = $validated['api_base_url'];

        // Only update secret if provided
        if (! empty($validated['client_secret'])) {
            $connection->client_secret_encrypted = Crypt::encryptString($validated['client_secret']);
        }

        $connection->status = 'configured';
        $connection->save();

        return back()->with('success', 'Connection settings saved successfully.');
    }

    /**
     * Trigger a manual sync.
     */
    public function triggerSync(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'mode' => ['required', 'in:incremental,full'],
        ]);

        $connection = AppfolioConnection::query()->first();

        if (! $connection) {
            return back()->with('error', 'Please configure AppFolio connection first.');
        }

        // Create a new sync run
        $syncRun = SyncRun::create([
            'appfolio_connection_id' => $connection->id,
            'mode' => $validated['mode'],
            'status' => 'pending',
            'started_at' => now(),
            'metadata' => ['triggered_by' => 'manual'],
        ]);

        // Dispatch the sync job
        SyncAppfolioResourceJob::dispatch($syncRun);

        return back()->with('success', 'Sync job has been queued.');
    }

    /**
     * Save sync configuration settings.
     */
    public function saveSyncConfiguration(SaveSyncConfigurationRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        // Save business hours settings
        Setting::set('business_hours', 'enabled', $validated['business_hours_enabled']);
        Setting::set('business_hours', 'timezone', $validated['timezone']);
        Setting::set('business_hours', 'start_hour', $validated['start_hour']);
        Setting::set('business_hours', 'end_hour', $validated['end_hour']);
        Setting::set('business_hours', 'weekdays_only', $validated['weekdays_only']);
        Setting::set('business_hours', 'business_hours_interval', $validated['business_hours_interval']);
        Setting::set('business_hours', 'off_hours_interval', $validated['off_hours_interval']);

        // Save sync settings
        Setting::set('sync', 'full_sync_time', $validated['full_sync_time']);

        return back()->with('success', 'Sync configuration saved. Changes will take effect on next scheduler run.');
    }
}
