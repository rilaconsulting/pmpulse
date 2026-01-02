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
use App\Models\AppfolioConnection;
use App\Models\Role;
use App\Models\Setting;
use App\Models\SyncRun;
use App\Models\User;
use App\Services\BusinessHoursService;
use App\Services\UserService;
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

    public function __construct(
        private readonly UserService $userService
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
        // Prevent deactivating self (additional check beyond form request)
        $validated = $request->validated();
        if (isset($validated['is_active']) && ! $validated['is_active'] && $user->id === auth()->id()) {
            return back()->withErrors(['is_active' => 'You cannot deactivate your own account.']);
        }

        $this->userService->updateUser($user, $validated);

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
        // Get current connection settings (mask the secret)
        $connection = AppfolioConnection::query()->first();

        // Get sync run history (last 20 runs)
        $syncHistory = SyncRun::query()
            ->latest('started_at')
            ->limit(20)
            ->get();

        // Get sync configuration from Settings
        $syncConfig = $this->getSyncConfiguration();

        return Inertia::render('Admin/Integrations', [
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

    // ========================================
    // Authentication (Google SSO)
    // ========================================

    /**
     * Display the authentication settings page.
     */
    public function authentication(): Response
    {
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

        Setting::set('google_sso', 'enabled', $validated['google_enabled']);
        Setting::set('google_sso', 'client_id', $validated['google_client_id']);

        // Only update secret if provided
        if (! empty($validated['google_client_secret'])) {
            Setting::set('google_sso', 'client_secret', $validated['google_client_secret'], encrypted: true);
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
        return Inertia::render('Admin/Settings', [
            'features' => [
                'incremental_sync' => Setting::isFeatureEnabled('incremental_sync', true),
                'notifications' => Setting::isFeatureEnabled('notifications', true),
            ],
        ]);
    }
}
