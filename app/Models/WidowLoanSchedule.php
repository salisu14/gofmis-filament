<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WidowLoanSchedule extends Model
{
    use HasUuids;

    protected $table = 'widow_loan_schedules';

    protected $fillable = [
        'widow_loan_id',
        'installment_number',
        'amount_due',
        'due_date',
        'is_paid',
        'paid_at',
        'status',
        'schedule_version',
        'superseded_at',
        'superseded_by',
    ];

    protected $casts = [
        'amount_due' => 'decimal:2',
        'due_date' => 'date',
        'paid_at' => 'date',
        'is_paid' => 'boolean',
        'status' => \App\Enums\WidowLoanScheduleStatus::class,
        'schedule_version' => 'integer',
        'superseded_at' => 'datetime',
    ];

    public function widowLoan(): BelongsTo
    {
        return $this->belongsTo(WidowLoan::class);
    }

    public function restructure(): BelongsTo
    {
        return $this->belongsTo(WidowLoanRestructure::class, 'superseded_by');
    }

    protected static function booted(): void
    {
        parent::booted();

        static::updating(function ($schedule) {
            // Financial facts (amount_due / installment_number) of an already
            // paid instalment are immutable after posting. The lock is
            // UNCONDITIONAL: the previous console/unit-test carve-out evaluated
            // to false in CLI, cron, queue and test contexts, and would have let
            // scheduled maintenance directly mutate paid rows. Legitimate
            // lifecycle writes (syncScheduleStatus) only touch is_paid/paid_at/
            // status, which remain permitted.
            if ($schedule->getOriginal('is_paid') && $schedule->isDirty(['amount_due', 'installment_number'])) {
                throw new \RuntimeException('Cannot modify the amount or installment number of a paid schedule row.');
            }
        });

        static::deleting(function ($schedule) {
            if ($schedule->getOriginal('is_paid')) {
                throw new \RuntimeException('Cannot delete a paid schedule row.');
            }
        });
    }
}
