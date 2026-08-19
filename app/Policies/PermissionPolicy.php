<?php

namespace App\Policies;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PermissionPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->can('view_permissions');
    }

    public function view(User $user, Permission $permission): bool
    {
        return $user->isSuperAdmin() || $user->can('view_permissions');
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, Permission $permission): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(User $user, Permission $permission): bool
    {
        return $user->isSuperAdmin();
    }
}
