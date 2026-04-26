<?php

namespace App\Listeners\Imprest;

use App\Events\Imprest\TransactionCreated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class CheckMissingReceipts
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
    public function handle(TransactionCreated $event): void
    {
        if (!$event->transaction->receipt_attached) {
            // Schedule reminder notification after 48 hours
            // or dispatch immediate alert to supervisor
        }
    }
}
