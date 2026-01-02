<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CreateUserRequest;
use App\Http\Requests\DeactivateUserRequest;
use App\Http\Requests\ListUsersRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    public function __construct(
        private readonly UserService $userService
    ) {}

    /**
     * Display a listing of users.
     */
    public function index(ListUsersRequest $request): Response
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

        return Inertia::render('Users/Index', [
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
    public function create(ListUsersRequest $request): Response
    {
        $roles = Role::orderBy('name')->get(['id', 'name', 'description']);

        return Inertia::render('Users/Create', [
            'roles' => $roles,
        ]);
    }

    /**
     * Store a newly created user.
     */
    public function store(CreateUserRequest $request): RedirectResponse
    {
        $this->userService->createUser($request->validated(), $request->user());

        return redirect()->route('users.index')
            ->with('success', 'User created successfully.');
    }

    /**
     * Show the form for editing the specified user.
     */
    public function edit(ListUsersRequest $request, User $user): Response
    {
        $user->load('role');
        $roles = Role::orderBy('name')->get(['id', 'name', 'description']);

        return Inertia::render('Users/Edit', [
            'user' => $user,
            'roles' => $roles,
            'canDeactivate' => ! $this->userService->isLastActiveAdmin($user) && $user->id !== auth()->id(),
        ]);
    }

    /**
     * Update the specified user.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        // Prevent deactivating self (additional check beyond form request)
        $validated = $request->validated();
        if (isset($validated['is_active']) && ! $validated['is_active'] && $user->id === auth()->id()) {
            return back()->withErrors(['is_active' => 'You cannot deactivate your own account.']);
        }

        $this->userService->updateUser($user, $validated);

        return redirect()->route('users.index')
            ->with('success', 'User updated successfully.');
    }

    /**
     * Deactivate the specified user.
     */
    public function destroy(DeactivateUserRequest $request, User $user): RedirectResponse
    {
        $this->userService->deactivateUser($user);

        return redirect()->route('users.index')
            ->with('success', 'User deactivated successfully.');
    }
}
