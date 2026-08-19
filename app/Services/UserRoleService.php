<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class UserRoleService
{
    /**
     * Sync roles for a target user, enforcing the hierarchical privileges of the actor.
     */
    public function syncRoles(User $actor, User $target, array $roleIdsOrNames): void
    {
        // 1. Enforce hierarchy for the target user modification
        if ($target->isSuperAdmin() && ! $actor->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'roles' => ['Unauthorized: Only super administrators can modify a super administrator account.'],
            ]);
        }

        if ($target->isAdmin() && ! $actor->isSuperAdmin() && $target->id !== $actor->id) {
            throw ValidationException::withMessages([
                'roles' => ['Unauthorized: Only super administrators can modify an administrator account.'],
            ]);
        }

        if ($actor->isCoordinator()) {
            throw ValidationException::withMessages([
                'roles' => ['Unauthorized: Coordinators cannot manage roles.'],
            ]);
        }

        // Fetch target roles to resolve
        $rolesToSync = Role::whereIn('uuid', $roleIdsOrNames)
            ->orWhereIn('name', $roleIdsOrNames)
            ->get();

        // 2. Validate privilege escalation
        foreach ($rolesToSync as $role) {
            // Cannot assign super_admin role unless the actor is super_admin
            if ($role->name === 'super_admin' && ! $actor->isSuperAdmin()) {
                throw ValidationException::withMessages([
                    'roles' => ['Unauthorized: Only super administrators can assign the Super Admin role.'],
                ]);
            }

            // Cannot assign admin role unless the actor is super_admin (Admin cannot assign admin)
            if ($role->name === 'admin' && ! $actor->isSuperAdmin()) {
                throw ValidationException::withMessages([
                    'roles' => ['Unauthorized: Only super administrators can assign the Admin role.'],
                ]);
            }
        }

        // 3. Super Admin Protections: The last active Super Admin cannot lose Super Admin role
        if ($target->isSuperAdmin() && ! $rolesToSync->pluck('name')->contains('super_admin')) {
            if (User::getActiveSuperAdminCount() <= 1) {
                throw ValidationException::withMessages([
                    'roles' => ['Unauthorized: Cannot remove the Super Admin role from the last active Super Admin.'],
                ]);
            }
        }

        // Perform the sync
        $target->syncRoles($rolesToSync);
    }
}
