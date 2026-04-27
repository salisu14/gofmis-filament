<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WelfarePackage;
use Illuminate\Auth\Access\HandlesAuthorization;

class WelfarePackagePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin', 'coordinator']);
    }

    public function view(User $user, WelfarePackage $package): bool
    {
        return $user->hasRole(['super_admin', 'admin', 'coordinator']);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin']);
    }

    public function update(User $user, WelfarePackage $package): bool
    {
        return $user->hasRole(['super_admin', 'admin']) && $package->isDraft();
    }

    public function delete(User $user, WelfarePackage $package): bool
    {
        return $user->hasRole(['super_admin', 'admin']) && $package->isDraft();
    }

    public function open(User $user, WelfarePackage $package): bool
    {
        return $user->hasRole(['super_admin', 'admin']) && $package->canBeOpened();
    }

    public function close(User $user, WelfarePackage $package): bool
    {
        return $user->hasRole(['super_admin', 'admin']) && $package->canBeClosed();
    }

    public function reopen(User $user, WelfarePackage $package): bool
    {
        return $user->hasRole(['super_admin', 'admin']) && $package->canBeReopened();
    }

    public function duplicate(User $user, WelfarePackage $package): bool
    {
        return $user->hasRole(['super_admin', 'admin']);
    }

    public function manageBeneficiaries(User $user, WelfarePackage $package): bool
    {
        return $user->hasRole(['super_admin', 'admin']) ||
            ($user->hasRole('coordinator') && $package->isOpen());
    }

    public function collect(User $user, WelfarePackage $package): bool
    {
        return $user->hasRole(['super_admin', 'admin']) && $package->isOpen();
    }
}
