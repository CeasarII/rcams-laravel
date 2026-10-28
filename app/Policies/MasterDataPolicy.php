<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * One policy shared by the master-data models (Company, Department,
 * VesselType, Vessel) — registered per model in AppServiceProvider.
 * Maintenance is Admin-only; there is no delete ability by design.
 */
class MasterDataPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('ADMIN');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('ADMIN');
    }

    public function update(User $user, Model $model): bool
    {
        return $user->hasRole('ADMIN');
    }
}
