<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// =========================================================================
// GOF MIS Production Scheduled Tasks
// =========================================================================
// Only read-only diagnostic and safe idempotent reconciliation tasks are
// scheduled for unattended production execution.
// Mutating repair commands (e.g. finance:repair-bank-balances) MUST NOT be
// scheduled unattended.
// =========================================================================

// Daily off-peak delinquency evaluation (00:00)
Schedule::command('widow-loans:evaluate-delinquency')
    ->dailyAt('00:00')
    ->withoutOverlapping();

// Daily off-peak financial diagnostic audit (01:00)
Schedule::command('finance:reconcile')
    ->dailyAt('01:00')
    ->withoutOverlapping();

// Daily off-peak inventory ledger reconciliation (01:30)
Schedule::command('inventory:reconcile')
    ->dailyAt('01:30')
    ->withoutOverlapping();

// Daily off-peak widow loan portfolio reconciliation (02:00)
Schedule::command('widow-loans:reconcile')
    ->dailyAt('02:00')
    ->withoutOverlapping();

// Weekly ID card status & expiration audit (Sunday at 02:30)
Schedule::command('id-cards:reconcile')
    ->weeklyOn(0, '02:30')
    ->withoutOverlapping();

// Weekly security & RBAC diagnostic audit (Sunday at 03:00)
Schedule::command('security:rbac-audit')
    ->weeklyOn(0, '03:00')
    ->withoutOverlapping();

// Monthly zone coordinator assignment history audit (1st of month at 04:00)
Schedule::command('zone-coordinators:reconcile')
    ->monthlyOn(1, '04:00')
    ->withoutOverlapping();
