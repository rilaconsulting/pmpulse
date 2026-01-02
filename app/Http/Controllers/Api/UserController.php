<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Display a listing of users.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $query = User::with('role');

        // Filter by active status
        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
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

        $users = $query->orderBy('name')->paginate($request->input('per_page', 15));

        return response()->json($users);
    }

    /**
     * Display the specified user.
     */
    public function show(Request $request, User $user): JsonResponse
    {
        $this->authorizeAdmin($request);

        $user->load('role', 'creator');

        return response()->json([
            'data' => $user,
        ]);
    }

    /**
     * Store a newly created user.
     */
    public function store(CreateUserRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Hash password if provided, otherwise set unusable password for SSO users
        if (! empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            // SSO users get an unusable random password (they can't login with password)
            $validated['password'] = Hash::make(bin2hex(random_bytes(32)));
        }

        // Set created_by to current user
        $validated['created_by'] = $request->user()->id;

        $user = User::create($validated);
        $user->load('role');

        return response()->json([
            'message' => 'User created successfully',
            'data' => $user,
        ], 201);
    }

    /**
     * Update the specified user.
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $validated = $request->validated();

        // Hash password if provided
        if (isset($validated['password']) && ! empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);
        $user->load('role');

        return response()->json([
            'message' => 'User updated successfully',
            'data' => $user,
        ]);
    }

    /**
     * Soft delete (deactivate) the specified user.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorizeAdmin($request);

        // Prevent self-deactivation
        if ($user->id === $request->user()->id) {
            return response()->json([
                'message' => 'Cannot deactivate your own account',
                'errors' => ['user' => ['Cannot deactivate your own account']],
            ], 422);
        }

        // Prevent deactivating the last admin
        if ($user->isAdmin() && $this->isLastActiveAdmin($user)) {
            return response()->json([
                'message' => 'Cannot deactivate the last admin user',
                'errors' => ['user' => ['Cannot deactivate the last admin user']],
            ], 422);
        }

        $user->update(['is_active' => false]);

        return response()->json([
            'message' => 'User deactivated successfully',
        ]);
    }

    /**
     * Get available roles for dropdown.
     */
    public function roles(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $roles = Role::orderBy('name')->get(['id', 'name', 'description']);

        return response()->json([
            'data' => $roles,
        ]);
    }

    /**
     * Authorize that the current user is an admin.
     */
    private function authorizeAdmin(Request $request): void
    {
        if (! $request->user()?->isAdmin()) {
            abort(403, 'Unauthorized. Admin access required.');
        }
    }

    /**
     * Check if the given user is the last active admin.
     */
    private function isLastActiveAdmin(User $user): bool
    {
        $adminRole = Role::where('name', Role::ADMIN)->first();

        if (! $adminRole) {
            return false;
        }

        $activeAdminCount = User::where('role_id', $adminRole->id)
            ->where('is_active', true)
            ->count();

        return $activeAdminCount <= 1;
    }
}
