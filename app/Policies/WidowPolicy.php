<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Widow;
use Illuminate\Auth\Access\HandlesAuthorization;

class WidowPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return $user->can('view_widows');
    }

    public function view(User $user, Widow $widow): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return $user->can('view_widows');
    }

    public function create(User $user): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return $user->can('create_widows');
    }

    public function update(User $user, Widow $widow): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return $user->can('edit_widows');
    }

    public function delete(User $user, Widow $widow): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return $user->can('delete_widows');
    }

    public function restore(User $user, Widow $widow): bool
    {
        return $user->hasRole('super_admin');
    }

    public function forceDelete(User $user, Widow $widow): bool
    {
        return $user->hasRole('super_admin');
    }
}
