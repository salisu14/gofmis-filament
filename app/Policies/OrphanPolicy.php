<?php

namespace App\Policies;

use App\Enums\OrphanStatus;
use App\Models\Orphan;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class OrphanPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return $user->can('view_orphans');
    }

    public function view(User $user, Orphan $orphan): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return $user->can('view_orphans');
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin', 'coordinator']);
    }

    public function update(User $user, Orphan $orphan): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin']);
    }

    public function delete(User $user, Orphan $orphan): bool
    {
        if ($this->isArchivedOrHasHistory($orphan)) {
            return false;
        }

        return $user->hasAnyRole(['super_admin', 'admin']);
    }

    public function forceDelete(User $user, Orphan $orphan): bool
    {
        if ($this->isArchivedOrHasHistory($orphan)) {
            return false;
        }

        return $user->hasRole('super_admin');
    }

    public function restore(User $user, Orphan $orphan): bool
    {
        return $user->hasRole('super_admin');
    }

    protected function isArchivedOrHasHistory(Orphan $orphan): bool
    {
        $status = $orphan->status instanceof OrphanStatus ? $orphan->status : OrphanStatus::tryFrom((string) $orphan->status);

        if ($status === OrphanStatus::ARCHIVED || ! $orphan->is_eligible) {
            return true;
        }

        return $orphan->hasHistoricalRecords();
    }
}
