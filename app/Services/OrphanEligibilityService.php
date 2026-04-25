<?php

namespace App\Services;

use App\Models\Orphan;
use App\Events\OrphanBecameIneligible;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class OrphanEligibilityService
{
    /**
     * Check if a specific orphan is eligible for education support.
     */
    public function isEligible(Orphan $orphan): bool
    {
        // Rule 1: Boys > 17 are not eligible
        if ($orphan->gender === 'MALE' && $orphan->age > 17) {
            return false;
        }

        // Rule 2: Married girls are not eligible
        // Assuming there is an 'is_married' boolean on the orphan model
        if ($orphan->gender === 'FEMALE' && ($orphan->is_married ?? false)) {
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
        return Orphan::query()
            ->where(function ($query) {
                // Males must be 17 or younger
                $query->where('gender', 'MALE')
                    ->where('age', '<=', 17);
            })
            ->orWhere(function ($query) {
                // Females must not be married
                $query->where('gender', 'FEMALE')
                    ->where('is_married', false); // Ensure this column exists
            });
    }

    /**
     * Bulk check and fire events if eligibility is lost (e.g., run via Cron daily).
     */
    public function checkAndFlagIneligibleOrphans(): void
    {
        Orphan::where('gender', 'MALE')->where('age', '>', 17)
            ->get()
            ->each(function ($orphan) {
                // Logic to ensure we don't fire the event every day for the same orphan
                // (e.g., check a flag on the db). For simplicity:
                event(new OrphanBecameIneligible($orphan, 'AGE_LIMIT'));
            });

        Orphan::where('gender', 'FEMALE')->where('is_married', true)
            ->where('status', 'active') // Assuming a status column
            ->get()
            ->each(function ($orphan) {
                event(new OrphanBecameIneligible($orphan, 'MARRIAGE'));
            });
    }
}
