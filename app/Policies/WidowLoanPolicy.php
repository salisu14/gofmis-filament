<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WidowLoan;
use Illuminate\Auth\Access\HandlesAuthorization;

class WidowLoanPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        if ($user->isDemoObserver() || $user->hasRole('super_admin')) {
            return true;
        }

        return $user->can('view_loans');
    }

    public function view(User $user, WidowLoan $loan): bool
    {
        if ($user->isDemoObserver() || $user->hasRole('super_admin')) {
            return true;
        }

        return $user->can('view_loans');
    }

    public function create(User $user): bool
    {
        if ($user->isDemoObserver()) {
            return false;
        }

        if ($user->hasRole('super_admin')) {
            return true;
        }

        return $user->can('create_loans');
    }

    public function update(User $user, WidowLoan $loan): bool
    {
        if ($user->isDemoObserver()) {
            return false;
        }

        if ($user->hasRole('super_admin')) {
            return true;
        }

        return $user->can('edit_loans');
    }

    public function delete(User $user, WidowLoan $loan): bool
    {
        if ($user->isDemoObserver()) {
            return false;
        }

        if ($user->hasRole('super_admin')) {
            return true;
        }

        return $user->can('delete_loans');
    }
}
