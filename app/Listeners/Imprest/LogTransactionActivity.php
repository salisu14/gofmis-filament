<?php

namespace App\Listeners\Imprest;

use App\Events\Imprest\TransactionApproved;
use App\Events\Imprest\TransactionCreated;
use App\Events\Imprest\TransactionVoided;
use App\Models\ImprestAuditLog;
use Illuminate\Support\Facades\Request;

class LogTransactionActivity
{
    public function handle(TransactionCreated|TransactionApproved|TransactionVoided $event): void
    {
        $action = match (get_class($event)) {
            TransactionCreated::class => 'created',
            TransactionApproved::class => 'approved',
            TransactionVoided::class => 'voided',
        };

        ImprestAuditLog::create([
            'auditable_type' => get_class($event->transaction),
            'auditable_id' => $event->transaction->id,
            'user_id' => auth()->id(),
            'action' => $action,
            'new_values' => $event->transaction->toArray(),
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'created_at' => now(),
        ]);
    }
}
