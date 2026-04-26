<?php

namespace App\Policies;

use App\Models\ImprestTransaction;
use App\Models\User;

class ImprestTransactionPolicy
{
    public function viewAny(User $user): bool
    {
        // Use hasPermissionTo() or hasAnyPermission() - NOT hasPermission()
        return $user->hasPermissionTo('imprest.transactions.view')
            || $user->hasPermissionTo('imprest.manage_all');
    }

    public function view(User $user, ImprestTransaction $transaction): bool
    {
        return $user->hasPermissionTo('imprest.transactions.view')
            || $user->hasPermissionTo('imprest.manage_all')
            || $user->id === $transaction->custodian_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('imprest.transactions.create')
            || $user->hasPermissionTo('imprest.manage_all');
    }

    public function update(User $user, ImprestTransaction $transaction): bool
    {
        return $user->hasPermissionTo('imprest.transactions.edit')
            && $transaction->status === 'pending';
    }

    public function delete(User $user, ImprestTransaction $transaction): bool
    {
        return false; // Never delete, only void
    }

    public function approve(User $user, ImprestTransaction $transaction): bool
    {
        return $user->hasPermissionTo('imprest.transactions.approve')
            && $transaction->status === 'pending'
            && $user->id !== $transaction->custodian_id;
    }

    public function void(User $user, ImprestTransaction $transaction): bool
    {
        return $user->hasPermissionTo('imprest.transactions.void')
            && $transaction->isVoidable();
    }
}
