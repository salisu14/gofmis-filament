<?php

namespace App\Listeners;

use App\Events\OrphanBecameIneligible;

class HandleOrphanIneligibility
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(OrphanBecameIneligible $event): void
    {
        $orphan = $event->orphan;

        // 1. Cancel any pending intervention requests for this orphan
        $orphan->interventionRequests()
            ->where('status', 'pending')
            ->update(['status' => 'cancelled', 'rejection_reason' => 'Orphan is no longer eligible: ' . $event->reason]);

        // 2. Optional: Add a flag to the orphan model or log it
        $orphan->update(['is_eligible' => false]);
    }
}
