<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserSecurityService
{
    /**
     * Administratively reset a target user's password.
     */
    public function resetPassword(User $actor, User $target, string $newPassword): void
    {
        \App\Services\Security\DemoReadOnlyGuard::ensureCanMutate($actor);

        if (Gate::forUser($actor)->denies('resetPassword', $target)) {
            throw ValidationException::withMessages([
                'user' => ['Unauthorized: You are not authorized to reset this user\'s password.'],
            ]);
        }

        DB::transaction(function () use ($actor, $target, $newPassword) {
            $target->update([
                'password' => Hash::make($newPassword),
                'password_reset_required' => true,
            ]);

            SecurityAuditService::log(
                'ADMIN_PASSWORD_RESET',
                "Password reset for user {$target->email} by administrative actor {$actor->email}",
                $actor,
                $target,
                ['target_user_id' => $target->id, 'actor_id' => $actor->id]
            );
        });
    }

    /**
     * Disable a target user account.
     */
    public function disableUser(User $actor, User $target): void
    {
        \App\Services\Security\DemoReadOnlyGuard::ensureCanMutate($actor);

        if ($target->isProtectedSystemAccount() || Gate::forUser($actor)->denies('disable', $target)) {
            throw ValidationException::withMessages([
                'user' => ['Unauthorized: Protected system accounts cannot be disabled or deactivated.'],
            ]);
        }

        DB::transaction(function () use ($actor, $target) {
            if ($target->isSuperAdmin()) {
                if (User::getActiveSuperAdminCount() <= 1) {
                    throw ValidationException::withMessages([
                        'user' => ['Unauthorized: Cannot disable the last active Super Admin.'],
                    ]);
                }
            }

            $target->disable($actor);
        });
    }

    /**
     * Suspend a target user account.
     */
    public function suspendUser(User $actor, User $target, string $reason): void
    {
        \App\Services\Security\DemoReadOnlyGuard::ensureCanMutate($actor);

        if ($target->isProtectedSystemAccount() || Gate::forUser($actor)->denies('suspend', $target)) {
            throw ValidationException::withMessages([
                'user' => ['Unauthorized: Protected system accounts cannot be suspended.'],
            ]);
        }

        DB::transaction(function () use ($actor, $target, $reason) {
            if ($target->isSuperAdmin()) {
                if (User::getActiveSuperAdminCount() <= 1) {
                    throw ValidationException::withMessages([
                        'user' => ['Unauthorized: Cannot suspend the last active Super Admin.'],
                    ]);
                }
            }

            $target->suspend($actor, $reason);
        });
    }

    /**
     * Delete a target user account.
     */
    public function deleteUser(User $actor, User $target): void
    {
        \App\Services\Security\DemoReadOnlyGuard::ensureCanMutate($actor);

        if ($target->isProtectedSystemAccount() || Gate::forUser($actor)->denies('delete', $target)) {
            throw ValidationException::withMessages([
                'user' => ['Unauthorized: Protected system accounts cannot be deleted.'],
            ]);
        }

        DB::transaction(function () use ($target) {
            if ($target->isSuperAdmin()) {
                if (User::getActiveSuperAdminCount() <= 1) {
                    throw ValidationException::withMessages([
                        'user' => ['Unauthorized: Cannot delete the last active Super Admin.'],
                    ]);
                }
            }

            $target->delete();
        });
    }
}
