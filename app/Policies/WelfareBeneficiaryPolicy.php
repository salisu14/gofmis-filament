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
        return $user->hasRole(['super_admin', 'admin', 'coordinator']);
    }

    public function view(User $user, WelfareBeneficiary $beneficiary): bool
    {
        return $user->hasRole(['super_admin', 'admin']) ||
            ($user->hasRole('coordinator') && $beneficiary->suggested_by === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin', 'coordinator']);
    }

    public function suggest(User $user): bool
    {
        return $user->hasRole('coordinator');
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
