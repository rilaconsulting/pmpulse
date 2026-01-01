<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveConnectionRequest;
use App\Jobs\SyncAppfolioResourceJob;
use App\Models\AppfolioConnection;
use App\Models\SyncConfiguration;
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

        // Get sync configuration
        $syncConfig = SyncConfiguration::query()->first();

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
                'incremental_sync' => config('features.incremental_sync'),
                'notifications' => config('features.notifications'),
            ],
            'syncConfiguration' => $syncConfig ? [
                'business_hours_enabled' => $syncConfig->business_hours_enabled,
                'timezone' => $syncConfig->timezone,
                'start_hour' => $syncConfig->start_hour,
                'end_hour' => $syncConfig->end_hour,
                'weekdays_only' => $syncConfig->weekdays_only,
                'business_hours_interval' => $syncConfig->business_hours_interval,
                'off_hours_interval' => $syncConfig->off_hours_interval,
                'full_sync_time' => $syncConfig->full_sync_time,
            ] : null,
            'syncStatus' => $businessHoursService->getConfiguration(),
            'timezones' => SyncConfiguration::getTimezones(),
        ]);
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
    public function saveSyncConfiguration(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'business_hours_enabled' => ['required', 'boolean'],
            'timezone' => ['required', 'string', 'timezone'],
            'start_hour' => ['required', 'integer', 'min:0', 'max:23'],
            'end_hour' => ['required', 'integer', 'min:1', 'max:24', 'gt:start_hour'],
            'weekdays_only' => ['required', 'boolean'],
            'business_hours_interval' => ['required', 'integer', 'min:5', 'max:60'],
            'off_hours_interval' => ['required', 'integer', 'min:15', 'max:240'],
            'full_sync_time' => ['required', 'string', 'regex:/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/'],
        ]);

        $config = SyncConfiguration::query()->first() ?? new SyncConfiguration;

        $config->fill($validated);
        $config->save();

        return back()->with('success', 'Sync configuration saved. Changes will take effect on next scheduler run.');
    }
}
