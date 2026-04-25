<?php

namespace App\Listeners;

use App\Events\LoanFullyRepaid;
use App\Notifications\LoanClearedNotification;

class NotifyWidowOfLoanClearance
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
    public function handle(LoanFullyRepaid $event): void
    {
        $loan = $event->loan;
        $widow = $loan->widow;

        // Notify the widow (assuming you have a notification set up)
        $widow->notify(new LoanClearedNotification($loan));

        // You could also log this or trigger a SMS service here
    }
}
