<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserService
{
    /**
     * Check if the given user is the last active admin.
     */
    public function isLastActiveAdmin(User $user): bool
    {
        if (! $user->isAdmin()) {
            return false;
        }

        $adminRole = Role::where('name', Role::ADMIN)->first();

        if (! $adminRole) {
            return false;
        }

        $activeAdminCount = User::where('role_id', $adminRole->id)
            ->where('is_active', true)
            ->count();

        return $activeAdminCount <= 1;
    }

    /**
     * Check if removing admin role would leave no active admins.
     */
    public function wouldRemoveLastAdmin(User $user, ?string $newRoleId): bool
    {
        $adminRole = Role::where('name', Role::ADMIN)->first();

        if (! $adminRole) {
            return false;
        }

        // User is not currently an admin
        if ($user->role_id !== $adminRole->id) {
            return false;
        }

        // New role is still admin
        if ($newRoleId === $adminRole->id) {
            return false;
        }

        return $this->isLastActiveAdmin($user);
    }

    /**
     * Create a new user with proper password handling.
     */
    public function createUser(array $data, User $createdBy): User
    {
        // Hash password if provided, otherwise set unusable password for SSO users
        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            // SSO users get an unusable random password (they can't login with password)
            $data['password'] = Hash::make(bin2hex(random_bytes(32)));
        }

        $data['created_by'] = $createdBy->id;

        $user = User::create($data);
        $user->load('role');

        return $user;
    }

    /**
     * Update a user with proper password handling.
     */
    public function updateUser(User $user, array $data): User
    {
        // Hash password if provided
        if (isset($data['password']) && ! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);
        $user->load('role');

        return $user;
    }

    /**
     * Deactivate a user (soft delete).
     */
    public function deactivateUser(User $user): void
    {
        $user->update(['is_active' => false]);
    }
}
