<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CreateUserRequest;
use App\Http\Requests\DeactivateUserRequest;
use App\Http\Requests\ListUsersRequest;
use App\Http\Requests\SaveAuthenticationRequest;
use App\Http\Requests\SaveConnectionRequest;
use App\Http\Requests\SaveSyncConfigurationRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Jobs\SyncAppfolioResourceJob;
use App\Models\Role;
use App\Models\Setting;
use App\Models\SyncRun;
use App\Models\User;
use App\Services\AppfolioClient;
use App\Services\BusinessHoursService;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function __construct(
        private readonly UserService $userService,
        private readonly AppfolioClient $appfolioClient
    ) {}

    // ========================================
    // Users Management
    // ========================================

    /**
     * Display a listing of users.
     */
    public function users(ListUsersRequest $request): Response
    {
        $query = User::with('role');

        // Filter by active status
        if ($request->has('active') && $request->validated('active') !== null) {
            $query->where('is_active', $request->validated('active'));
        }

        // Filter by auth provider
        if ($request->filled('auth_provider')) {
            $query->where('auth_provider', $request->validated('auth_provider'));
        }

        // Filter by role
        if ($request->filled('role_id')) {
            $query->where('role_id', $request->validated('role_id'));
        }

        // Search by name or email
        if ($request->filled('search')) {
            $search = $request->validated('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('name')->paginate(15)->withQueryString();
        $roles = Role::orderBy('name')->get(['id', 'name', 'description']);

        return Inertia::render('Admin/Users', [
            'users' => $users,
            'roles' => $roles,
            'filters' => [
                'search' => $request->input('search', ''),
                'active' => $request->input('active', ''),
                'auth_provider' => $request->input('auth_provider', ''),
                'role_id' => $request->input('role_id', ''),
            ],
        ]);
    }

    /**
     * Show the form for creating a new user.
     */
    public function usersCreate(ListUsersRequest $request): Response
    {
        $roles = Role::orderBy('name')->get(['id', 'name', 'description']);

        return Inertia::render('Admin/UserCreate', [
            'roles' => $roles,
        ]);
    }

    /**
     * Store a newly created user.
     */
    public function usersStore(CreateUserRequest $request): RedirectResponse
    {
        $this->userService->createUser($request->validated(), $request->user());

        return redirect()->route('admin.users.index')
            ->with('success', 'User created successfully.');
    }

    /**
     * Show the form for editing the specified user.
     */
    public function usersEdit(ListUsersRequest $request, User $user): Response
    {
        $user->load('role');
        $roles = Role::orderBy('name')->get(['id', 'name', 'description']);

        return Inertia::render('Admin/UserEdit', [
            'user' => $user,
            'roles' => $roles,
            'canDeactivate' => ! $this->userService->isLastActiveAdmin($user) && $user->id !== auth()->id(),
        ]);
    }

    /**
     * Update the specified user.
     */
    public function usersUpdate(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->userService->updateUser($user, $request->validated());

        return redirect()->route('admin.users.index')
            ->with('success', 'User updated successfully.');
    }

    /**
     * Deactivate the specified user.
     */
    public function usersDestroy(DeactivateUserRequest $request, User $user): RedirectResponse
    {
        $this->userService->deactivateUser($user);

        return redirect()->route('admin.users.index')
            ->with('success', 'User deactivated successfully.');
    }

    // ========================================
    // Integrations (AppFolio)
    // ========================================

    /**
     * Display the integrations page.
     */
    public function integrations(BusinessHoursService $businessHoursService): Response
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        // Get current connection settings from settings table
        $appfolioSettings = Setting::getCategory('appfolio');

        // Get sync run history (last 20 runs)
        $syncHistory = SyncRun::query()
            ->latest('started_at')
            ->limit(20)
            ->get();

        // Get sync configuration from Settings
        $syncConfig = $this->getSyncConfiguration();

        $connection = null;
        if (! empty($appfolioSettings['client_id'])) {
            $database = $appfolioSettings['database'] ?? null;
            $connection = [
                'client_id' => $appfolioSettings['client_id'],
                'database' => $database,
                'api_base_url' => $database ? "https://{$database}.appfolio.com" : null,
                'status' => $appfolioSettings['status'] ?? 'configured',
                'last_success_at' => $appfolioSettings['last_success_at'] ?? null,
                'last_error' => $appfolioSettings['last_error'] ?? null,
                'has_secret' => ! empty($appfolioSettings['client_secret']),
            ];
        }

        return Inertia::render('Admin/Integrations', [
            'connection' => $connection,
            'syncHistory' => $syncHistory->toArray(),
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
        $businessHours = Setting::getCategory('business_hours');
        $sync = Setting::getCategory('sync');

        return [
            'business_hours_enabled' => $businessHours['enabled'] ?? true,
            'timezone' => $businessHours['timezone'] ?? 'America/Los_Angeles',
            'start_hour' => $businessHours['start_hour'] ?? 9,
            'end_hour' => $businessHours['end_hour'] ?? 17,
            'weekdays_only' => $businessHours['weekdays_only'] ?? true,
            'business_hours_interval' => $businessHours['business_hours_interval'] ?? 15,
            'off_hours_interval' => $businessHours['off_hours_interval'] ?? 60,
            'full_sync_time' => $sync['full_sync_time'] ?? '02:00',
        ];
    }

    /**
     * Save AppFolio connection settings.
     */
    public function saveConnection(SaveConnectionRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        Setting::set('appfolio', 'client_id', $validated['client_id']);
        Setting::set('appfolio', 'database', $validated['database']);

        // Only update secret if provided
        if (! empty($validated['client_secret'])) {
            Setting::set('appfolio', 'client_secret', $validated['client_secret'], encrypted: true);
        }

        Setting::set('appfolio', 'status', 'configured');

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

        if (! $this->appfolioClient->isConfigured()) {
            return back()->with('error', 'Please configure AppFolio connection first.');
        }

        // Create a new sync run
        $syncRun = SyncRun::create([
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

    // ========================================
    // Authentication (Google SSO)
    // ========================================

    /**
     * Display the authentication settings page.
     */
    public function authentication(): Response
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $googleConfig = Setting::getCategory('google_sso');

        return Inertia::render('Admin/Authentication', [
            'googleSso' => [
                'enabled' => $googleConfig['enabled'] ?? false,
                'client_id' => $googleConfig['client_id'] ?? '',
                'has_secret' => ! empty($googleConfig['client_secret']),
                'configured' => ! empty($googleConfig['client_id']) && ! empty($googleConfig['client_secret']),
                'redirect_uri' => url('/auth/google/callback'),
            ],
        ]);
    }

    /**
     * Save authentication settings.
     */
    public function saveAuthentication(SaveAuthenticationRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $settingsToUpdate = [
            'enabled' => $validated['google_enabled'],
        ];

        // Only update client_id if provided (don't overwrite with empty string)
        if (! empty($validated['google_client_id'])) {
            $settingsToUpdate['client_id'] = $validated['google_client_id'];
        }

        // Only update secret if provided
        if (! empty($validated['google_client_secret'])) {
            $settingsToUpdate['client_secret'] = $validated['google_client_secret'];
        }

        foreach ($settingsToUpdate as $key => $value) {
            $isSecret = ($key === 'client_secret');
            Setting::set('google_sso', $key, $value, encrypted: $isSecret);
        }

        return back()->with('success', 'Authentication settings saved successfully.');
    }

    // ========================================
    // Settings
    // ========================================

    /**
     * Display the general settings page.
     */
    public function settings(): Response
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $googleSettings = Setting::getCategory('google');

        return Inertia::render('Admin/Settings', [
            'features' => [
                'incremental_sync' => Setting::isFeatureEnabled('incremental_sync', true),
                'notifications' => Setting::isFeatureEnabled('notifications', true),
            ],
            'googleMaps' => [
                'has_api_key' => ! empty($googleSettings['maps_api_key']),
            ],
        ]);
    }

    /**
     * Save Google Maps settings.
     */
    public function saveGoogleMapsSettings(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'maps_api_key' => ['nullable', 'string', 'max:100'],
        ]);

        if (! empty($validated['maps_api_key'])) {
            Setting::set('google', 'maps_api_key', $validated['maps_api_key'], encrypted: true);
        }

        return back()->with('success', 'Google Maps settings saved successfully.');
    }
}
