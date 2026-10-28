<?php

namespace App\Policies;

use App\Models\User;

/**
 * User management (create accounts, assign roles, assign OE scope,
 * activate/deactivate) is Admin-only. No self-registration anywhere.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('ADMIN');
    }

    public function view(User $user, User $target): bool
    {
        return $user->hasRole('ADMIN');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('ADMIN');
    }

    public function update(User $user, User $target): bool
    {
        return $user->hasRole('ADMIN');
    }

    /** An Admin may not deactivate their own account — avoids locking everyone out. */
    public function toggleActive(User $user, User $target): bool
    {
        return $user->hasRole('ADMIN') && $user->id !== $target->id;
    }
}
