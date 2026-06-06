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
        return $user->hasAnyRole(['super_admin', 'admin']) || $user->managesZone();
    }

    public function view(User $user, WelfarePackage $package): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin']) || $user->managesZone();
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin']);
    }

    public function update(User $user, WelfarePackage $package): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin']) && $package->isDraft();
    }

    public function delete(User $user, WelfarePackage $package): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin']) && $package->isDraft();
    }

    public function open(User $user, WelfarePackage $package): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin']) && $package->canBeOpened();
    }

    public function close(User $user, WelfarePackage $package): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin']) && $package->canBeClosed();
    }

    public function reopen(User $user, WelfarePackage $package): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin']) && $package->canBeReopened();
    }

    public function duplicate(User $user, WelfarePackage $package): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin']);
    }

    public function manageBeneficiaries(User $user, WelfarePackage $package): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin']) ||
            ($user->managesZone() && $package->isOpen());
    }

    public function collect(User $user, WelfarePackage $package): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin']) && $package->isOpen();
    }
}
