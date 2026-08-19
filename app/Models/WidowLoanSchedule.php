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
}
