<?php

namespace App\Services;

use App\Enums\Gender;
use App\Events\OrphanBecameIneligible;
use App\Models\Orphan;
use App\Models\Scopes\EligibleOrphanScope;
use Illuminate\Database\Eloquent\Builder;

class OrphanEligibilityService
{
    /**
     * Check if a specific orphan is eligible for education support.
     */
    public function isEligible(Orphan $orphan): bool
    {
        if (! $orphan->is_eligible || $orphan->status === Orphan::STATUS_ARCHIVED) {
            return false;
        }

        // Rule 1: Boys 18 and older are not eligible
        if (
            $orphan->gender === Gender::MALE &&
            $orphan->birth_date?->age >= 18
        ) {
            return false;
        }

        // Rule 2: Married girls are not eligible
        if (
            $orphan->gender === Gender::FEMALE &&
            $orphan->is_married
        ) {
            return false;
        }

        return true;
    }

    /**
     * Get a query builder for only eligible orphans.
     * Used for dropdown lists or reports.
     */
    public function getEligibleOrphansQuery(): Builder
    {
        return Orphan::withoutGlobalScope(EligibleOrphanScope::class)
            ->where('is_eligible', true)
            ->where('status', '!=', Orphan::STATUS_ARCHIVED)
            ->where(function ($query) {
                $query->where(function ($query) {
                    // Males must be under 18
                    $query->where('gender', Gender::MALE)
                        ->whereDate('birth_date', '>', now()->subYears(18)->toDateString());
                })->orWhere(function ($query) {
                    // Females must not be married
                    $query->where('gender', Gender::FEMALE)
                        ->where('is_married', false);
                });
            });
    }

    /**
     * Bulk check and fire events if eligibility is lost (e.g., run via Cron daily).
     */
    public function checkAndFlagIneligibleOrphans(): void
    {
        Orphan::withoutGlobalScope(EligibleOrphanScope::class)
            ->where('gender', 'MALE')
            ->whereDate('birth_date', '<=', now()->subYears(18)->toDateString())
            ->where(function ($query) {
                $query->where('is_eligible', true)
                    ->orWhere('status', '!=', Orphan::STATUS_ARCHIVED);
            })
            ->get()
            ->each(function ($orphan) {
                event(new OrphanBecameIneligible($orphan, 'AGE_LIMIT'));
            });

        Orphan::withoutGlobalScope(EligibleOrphanScope::class)
            ->where('gender', 'FEMALE')
            ->where('is_married', true)
            ->where(function ($query) {
                $query->where('is_eligible', true)
                    ->orWhere('status', '!=', Orphan::STATUS_ARCHIVED);
            })
            ->get()
            ->each(function ($orphan) {
                event(new OrphanBecameIneligible($orphan, 'MARRIAGE'));
            });
    }
}
