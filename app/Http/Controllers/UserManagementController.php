<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
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
    public function index(Request $request): Response
    {
        $query = User::with('role');

        // Filter by active status
        if ($request->has('active') && $request->input('active') !== '') {
            $query->where('is_active', filter_var($request->input('active'), FILTER_VALIDATE_BOOLEAN));
        }

        // Filter by auth provider
        if ($request->filled('auth_provider')) {
            $query->where('auth_provider', $request->input('auth_provider'));
        }

        // Filter by role
        if ($request->filled('role_id')) {
            $query->where('role_id', $request->input('role_id'));
        }

        // Search by name or email
        if ($request->filled('search')) {
            $search = $request->input('search');
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
    public function create(): Response
    {
        $roles = Role::orderBy('name')->get(['id', 'name', 'description']);

        return Inertia::render('Users/Create', [
            'roles' => $roles,
        ]);
    }

    /**
     * Store a newly created user.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'role_id' => ['required', 'uuid', 'exists:user_roles,id'],
            'auth_provider' => ['required', 'string', Rule::in([User::AUTH_PROVIDER_PASSWORD, User::AUTH_PROVIDER_GOOGLE])],
            'password' => [
                Rule::requiredIf($request->input('auth_provider') === User::AUTH_PROVIDER_PASSWORD),
                'nullable',
                'string',
                Password::min(8)->mixedCase()->numbers(),
            ],
        ]);

        $this->userService->createUser($validated, $request->user());

        return redirect()->route('users.index')
            ->with('success', 'User created successfully.');
    }

    /**
     * Show the form for editing the specified user.
     */
    public function edit(User $user): Response
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
    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'role_id' => ['required', 'uuid', 'exists:user_roles,id'],
            'is_active' => ['sometimes', 'boolean'],
            'password' => [
                'nullable',
                'string',
                Password::min(8)->mixedCase()->numbers(),
            ],
        ]);

        // Prevent deactivating self
        if (isset($validated['is_active']) && ! $validated['is_active'] && $user->id === auth()->id()) {
            return back()->withErrors(['is_active' => 'You cannot deactivate your own account.']);
        }

        // Prevent removing last admin
        if ($this->userService->wouldRemoveLastAdmin($user, $validated['role_id'] ?? null)) {
            return back()->withErrors(['role_id' => 'Cannot change role: this is the last admin user.']);
        }

        // Prevent deactivating last admin
        if (isset($validated['is_active']) && ! $validated['is_active'] && $this->userService->isLastActiveAdmin($user)) {
            return back()->withErrors(['is_active' => 'Cannot deactivate the last admin user.']);
        }

        $this->userService->updateUser($user, $validated);

        return redirect()->route('users.index')
            ->with('success', 'User updated successfully.');
    }

    /**
     * Deactivate the specified user.
     */
    public function destroy(User $user): RedirectResponse
    {
        // Prevent deactivating self
        if ($user->id === auth()->id()) {
            return back()->withErrors(['user' => 'You cannot deactivate your own account.']);
        }

        // Prevent deactivating last admin
        if ($this->userService->isLastActiveAdmin($user)) {
            return back()->withErrors(['user' => 'Cannot deactivate the last admin user.']);
        }

        $this->userService->deactivateUser($user);

        return redirect()->route('users.index')
            ->with('success', 'User deactivated successfully.');
    }
}
