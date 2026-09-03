<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WelfareBeneficiary;
use Illuminate\Auth\Access\HandlesAuthorization;

class WelfareBeneficiaryPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->isDemoObserver() || $user->hasAnyRole(['super_admin', 'admin']) || $user->managesZone();
    }

    public function view(User $user, WelfareBeneficiary $beneficiary): bool
    {
        $zoneId = $beneficiary->deceased()->withoutGlobalScopes()->value('zone_id');

        return $user->isDemoObserver() ||
            $user->hasAnyRole(['super_admin', 'admin']) ||
            ($zoneId && $user->managesZone($zoneId));
    }

    public function create(User $user): bool
    {
        if ($user->isDemoObserver()) {
            return false;
        }

        return $user->hasAnyRole(['super_admin', 'admin']) || $user->managesZone();
    }

    public function suggest(User $user): bool
    {
        if ($user->isDemoObserver()) {
            return false;
        }

        return $user->managesZone();
    }

    public function approve(User $user, WelfareBeneficiary $beneficiary): bool
    {
        if ($user->isDemoObserver()) {
            return false;
        }

        return $user->hasRole('admin') && $beneficiary->canBeApproved();
    }

    public function reject(User $user, WelfareBeneficiary $beneficiary): bool
    {
        if ($user->isDemoObserver()) {
            return false;
        }

        return $user->hasRole('admin') && $beneficiary->canBeRejected();
    }

    public function collect(User $user, WelfareBeneficiary $beneficiary): bool
    {
        if ($user->isDemoObserver()) {
            return false;
        }

        return $user->hasRole('admin') && $beneficiary->canBeCollected();
    }

    public function delete(User $user, WelfareBeneficiary $beneficiary): bool
    {
        return $user->hasRole('admin') && $beneficiary->isPending();
    }
}
