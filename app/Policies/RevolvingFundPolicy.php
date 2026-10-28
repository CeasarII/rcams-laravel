<?php

namespace App\Policies;

use App\Models\RevolvingFund;
use App\Models\User;
use App\Support\LiquidationAccess;

class RevolvingFundPolicy
{
    /** The list: everyone who works the process, plus Requestors (they see only their own funds). */
    public function viewAny(User $user): bool
    {
        $roles = LiquidationAccess::roles($user);

        return LiquidationAccess::seesAll($roles) || in_array('REQUESTOR', $roles, true);
    }

    public function view(User $user, RevolvingFund $fund): bool
    {
        return LiquidationAccess::seesAll(LiquidationAccess::roles($user)) || $fund->custodian_id === $user->id;
    }

    /** Setting a fund up (and changing it) is Admin work. */
    public function create(User $user): bool
    {
        return $user->hasRole('ADMIN');
    }

    public function update(User $user, RevolvingFund $fund): bool
    {
        return $user->hasRole('ADMIN');
    }

    /** Start/end a voyage and prepare its expenses: the fund's custodian only, while it is active. */
    public function manageVoyage(User $user, RevolvingFund $fund): bool
    {
        return $fund->custodian_id === $user->id && $fund->isActive();
    }
}
