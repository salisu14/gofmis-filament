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
        $event->orphan->archiveForIneligibility($event->reason);
    }
}
