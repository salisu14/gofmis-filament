<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    private function canManageTarget(User $actor, User $target): bool
    {
        if ($actor->isDemoObserver()) {
            return false;
        }

        // Super admin can manage anyone
        if ($actor->isSuperAdmin()) {
            return true;
        }

        // Target is super admin: only super admin can manage them
        if ($target->isSuperAdmin()) {
            return false;
        }

        // Target is admin: only super admin can manage them (prevent lateral admin tampering)
        if ($target->isAdmin() && ! $actor->isSuperAdmin()) {
            return false;
        }

        // Coordinator and lower roles must not manage Admin/Super Admin accounts
        if ($actor->isCoordinator()) {
            return false;
        }

        return $actor->hasAnyRole(['admin', 'super_admin']);
    }

    public function viewAny(User $user): bool
    {
        return $user->isDemoObserver() || $user->can('view_users') || $user->can('user_access') || $user->isSuperAdmin();
    }

    public function view(User $user, User $model): bool
    {
        if ($user->isDemoObserver()) {
            return true;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }
        if ($user->isCoordinator() && $model->hasElevatedPrivileges()) {
            return false;
        }

        return $user->can('view_users') || $user->can('user_access');
    }

    public function create(User $user): bool
    {
        if ($user->isDemoObserver() || $user->isCoordinator()) {
            return false;
        }

        return $user->can('create_users') || $user->can('user_create') || $user->isSuperAdmin();
    }

    public function update(User $user, User $model): bool
    {
        if (! $this->canManageTarget($user, $model)) {
            return false;
        }

        return $user->can('edit_users') || $user->can('user_edit') || $user->isSuperAdmin();
    }

    public function delete(User $user, User $model): bool
    {
        // Protected system accounts cannot be deleted by anyone.
        if ($model->isProtectedSystemAccount()) {
            return false;
        }

        // A Super Admin cannot accidentally delete their own account.
        if ($user->id === $model->id) {
            return false;
        }

        // The last active Super Admin cannot be deleted.
        if ($model->isSuperAdmin()) {
            if (User::getActiveSuperAdminCount() <= 1) {
                return false;
            }
        }

        if (! $this->canManageTarget($user, $model)) {
            return false;
        }

        return $user->can('delete_users') || $user->can('user_delete') || $user->isSuperAdmin();
    }

    public function disable(User $user, User $model): bool
    {
        // Protected system accounts cannot be disabled.
        if ($model->isProtectedSystemAccount()) {
            return false;
        }

        // A Super Admin cannot disable themselves.
        if ($user->id === $model->id) {
            return false;
        }

        // The last active Super Admin cannot be disabled.
        if ($model->isSuperAdmin()) {
            if (User::getActiveSuperAdminCount() <= 1) {
                return false;
            }
        }

        return $this->update($user, $model);
    }

    public function suspend(User $user, User $model): bool
    {
        // Protected system accounts cannot be suspended.
        if ($model->isProtectedSystemAccount()) {
            return false;
        }

        // A Super Admin cannot suspend themselves.
        if ($user->id === $model->id) {
            return false;
        }

        // The last active Super Admin cannot be suspended.
        if ($model->isSuperAdmin()) {
            if (User::getActiveSuperAdminCount() <= 1) {
                return false;
            }
        }

        return $this->update($user, $model);
    }

    public function resetPassword(User $user, User $model): bool
    {
        // Super Admin can reset protected system account password through explicit action
        if ($model->isProtectedSystemAccount()) {
            return $user->isSuperAdmin();
        }

        return $this->update($user, $model);
    }

    public function resetMfa(User $user, User $model): bool
    {
        // Must NOT reset any super_admin MFA (even super_admin -> super_admin is denied)
        if ($model->isSuperAdmin()) {
            return false;
        }

        return $this->update($user, $model);
    }

    public function changeRoles(User $user, User $model): bool
    {
        if ($model->isProtectedSystemAccount()) {
            return false;
        }

        return $this->update($user, $model);
    }
}
