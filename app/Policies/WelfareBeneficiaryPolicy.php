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
        return $user->hasAnyRole(['super_admin', 'admin']) || $user->managesZone();
    }

    public function view(User $user, WelfareBeneficiary $beneficiary): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin']) ||
            ($user->managesZone($beneficiary->deceased?->zone_id) && $beneficiary->suggested_by === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin']) || $user->managesZone();
    }

    public function suggest(User $user): bool
    {
        return $user->managesZone();
    }

    public function approve(User $user, WelfareBeneficiary $beneficiary): bool
    {
        return $user->hasRole('admin') && $beneficiary->canBeApproved();
    }

    public function reject(User $user, WelfareBeneficiary $beneficiary): bool
    {
        return $user->hasRole('admin') && $beneficiary->canBeRejected();
    }

    public function collect(User $user, WelfareBeneficiary $beneficiary): bool
    {
        return $user->hasRole('admin') && $beneficiary->canBeCollected();
    }

    public function delete(User $user, WelfareBeneficiary $beneficiary): bool
    {
        return $user->hasRole('admin') && $beneficiary->isPending();
    }
}
