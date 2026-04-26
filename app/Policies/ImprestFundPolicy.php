<?php

namespace App\Policies;

use App\Models\ImprestFund;
use App\Models\User;

class ImprestFundPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('imprest.funds.view')
            || $user->hasPermissionTo('imprest.manage_all');
    }

    public function view(User $user, ImprestFund $fund): bool
    {
        return $user->hasPermissionTo('imprest.funds.view')
            || $user->hasPermissionTo('imprest.manage_all')
            || $user->id === $fund->custodian_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('imprest.funds.create')
            || $user->hasPermissionTo('imprest.manage_all');
    }

    public function update(User $user, ImprestFund $fund): bool
    {
        return $user->hasPermissionTo('imprest.funds.edit')
            || $user->hasPermissionTo('imprest.manage_all');
    }

    public function reconcile(User $user, ImprestFund $fund): bool
    {
        return $user->hasPermissionTo('imprest.funds.reconcile')
            && $user->id !== $fund->custodian_id;
    }

    public function replenish(User $user, ImprestFund $fund): bool
    {
        return $user->hasPermissionTo('imprest.funds.replenish')
            || $user->hasPermissionTo('imprest.manage_all');
    }
}
