<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateUserRequest;
use App\Http\Requests\DeactivateUserRequest;
use App\Http\Requests\ListUsersRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService $userService
    ) {}

    /**
     * Display a listing of users.
     */
    public function index(ListUsersRequest $request): JsonResponse
    {
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

        $perPage = $request->integer('per_page', 15);
        $users = $query->orderBy('name')->paginate($perPage);

        return response()->json($users);
    }

    /**
     * Display the specified user.
     */
    public function show(ListUsersRequest $request, User $user): JsonResponse
    {
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
        $user = $this->userService->createUser(
            $request->validated(),
            $request->user()
        );

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
        $user = $this->userService->updateUser($user, $request->validated());

        return response()->json([
            'message' => 'User updated successfully',
            'data' => $user,
        ]);
    }

    /**
     * Soft delete (deactivate) the specified user.
     */
    public function destroy(DeactivateUserRequest $request, User $user): JsonResponse
    {
        $this->userService->deactivateUser($user);

        return response()->json([
            'message' => 'User deactivated successfully',
        ]);
    }

    /**
     * Get available roles for dropdown.
     */
    public function roles(ListUsersRequest $request): JsonResponse
    {
        $roles = Role::orderBy('name')->get(['id', 'name', 'description']);

        return response()->json([
            'data' => $roles,
        ]);
    }
}
