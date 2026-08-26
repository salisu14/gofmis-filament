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
            // If the schedule is already paid, we generally don't allow modifying it 
            // unless it's a programmatic change to update its status or similar.
            // But we must at least protect financial fields if it was originally paid.
            if ($schedule->getOriginal('is_paid') && (!app()->runningInConsole() || app()->runningUnitTests())) {
                // We'll allow changes to is_paid/paid_at/status for reversals if done through a service,
                // but for general manual edits we throw if they try to change amounts or dates.
                if ($schedule->isDirty(['amount_due', 'installment_number'])) {
                    throw new \RuntimeException("Cannot modify the amount or installment number of a paid schedule row.");
                }
            }
        });

        static::deleting(function ($schedule) {
            if ($schedule->getOriginal('is_paid') && (!app()->runningInConsole() || app()->runningUnitTests())) {
                throw new \RuntimeException("Cannot delete a paid schedule row.");
            }
        });
    }
}
