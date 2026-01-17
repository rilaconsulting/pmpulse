<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Property;
use App\Models\User;

class PropertyPolicy
{
    /**
     * Determine whether the user can view any models.
     *
     * For single-tenant app, all authenticated users can view properties.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     *
     * For single-tenant app, all authenticated users can view any property.
     */
    public function view(User $user, Property $property): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     *
     * Only admins can create properties.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can update the model.
     *
     * Only admins can update properties.
     */
    public function update(User $user, Property $property): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can delete the model.
     *
     * Only admins can delete properties.
     */
    public function delete(User $user, Property $property): bool
    {
        return $user->isAdmin();
    }
}
