<?php

namespace App\Services;

use App\Enums\Gender;
use App\Enums\OrphanStatus;
use App\Models\Orphan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrphanStatusService
{
    /**
     * @throws \Throwable
     */
    public function approve(
        Orphan $orphan,
        User $actor,
    ): void {
        DB::transaction(function () use ($orphan, $actor) {
            $orphan = Orphan::withoutGlobalScope(
                \App\Models\Scopes\EligibleOrphanScope::class
            )
                ->lockForUpdate()
                ->findOrFail($orphan->id);

            if ($orphan->status !== OrphanStatus::PENDING_REVIEW) {
                throw ValidationException::withMessages([
                    'status' => 'Only orphans pending review can be approved.',
                ]);
            }

            if (
                $orphan->gender === Gender::MALE
                && $orphan->age >= 18
            ) {
                throw ValidationException::withMessages([
                    'status' => 'A male orphan aged 18 or above cannot be approved.',
                ]);
            }

            if (
                $orphan->gender === Gender::FEMALE
                && $orphan->is_married
            ) {
                throw ValidationException::withMessages([
                    'status' => 'A married female orphan cannot be approved.',
                ]);
            }

            $orphan->update([
                'status' => OrphanStatus::ACTIVE,
                'is_eligible' => true,
                'rejection_reason' => null,
            ]);

            activity()
                ->performedOn($orphan)
                ->causedBy($actor)
                ->log('orphan_approved');
        });
    }

    /**
     * @throws \Throwable
     */
    public function reject(
        Orphan $orphan,
        User $actor,
        string $reason,
    ): void {
        DB::transaction(function () use ($orphan, $actor, $reason) {
            $orphan = Orphan::withoutGlobalScope(
                \App\Models\Scopes\EligibleOrphanScope::class
            )
                ->lockForUpdate()
                ->findOrFail($orphan->id);

            if ($orphan->status !== OrphanStatus::PENDING_REVIEW) {
                throw ValidationException::withMessages([
                    'status' => 'Only orphans pending review can be rejected.',
                ]);
            }

            $orphan->update([
                'status' => OrphanStatus::REJECTED,
                'is_eligible' => false,
                'rejection_reason' => $reason,
            ]);

            activity()
                ->performedOn($orphan)
                ->causedBy($actor)
                ->withProperties([
                    'reason' => $reason,
                ])
                ->log('orphan_rejected');
        });
    }

    /**
     * @throws \Throwable
     */
    public function archive(
        Orphan $orphan,
        User $actor,
        string $reason,
    ): void {
        DB::transaction(function () use ($orphan, $actor, $reason) {
            $orphan = Orphan::withoutGlobalScope(
                \App\Models\Scopes\EligibleOrphanScope::class
            )
                ->lockForUpdate()
                ->findOrFail($orphan->id);

            $orphan->archiveForIneligibility($reason);

            activity()
                ->performedOn($orphan)
                ->causedBy($actor)
                ->withProperties([
                    'reason' => $reason,
                ])
                ->log('orphan_archived');
        });
    }
}
