<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class RolePolicy
{
    use HandlesAuthorization;

    private function isProtected(Role $role): bool
    {
        return in_array($role->name, ['super_admin', 'admin', 'coordinator']);
    }

    public function viewAny(User $user): bool
    {
        return $user->isDemoObserver() || $user->can('view_roles') || $user->can('role_access') || $user->isSuperAdmin();
    }

    public function view(User $user, Role $role): bool
    {
        return $user->isDemoObserver() || $user->can('view_roles') || $user->can('role_access') || $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        if ($user->isDemoObserver()) {
            return false;
        }

        return $user->isSuperAdmin();
    }

    public function update(User $user, Role $role): bool
    {
        if ($user->isDemoObserver()) {
            return false;
        }
        if ($role->name === 'super_admin' && ! $user->isSuperAdmin()) {
            return false;
        }

        if (! $user->isSuperAdmin() && ! $user->isAdmin()) {
            return false;
        }

        return $user->can('edit_roles') || $user->can('role_edit') || $user->isSuperAdmin();
    }

    public function delete(User $user, Role $role): bool
    {
        if ($this->isProtected($role)) {
            return false;
        }

        if ($role->users()->exists()) {
            return false;
        }

        return $user->isSuperAdmin();
    }
}
