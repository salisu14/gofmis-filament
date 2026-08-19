<?php

namespace App\Models;

use App\Enums\LoanRepaymentFrequency;
use App\Enums\WidowLoanRestructureStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WidowLoanRestructure extends Model
{
    use HasUuids;

    protected $table = 'widow_loan_restructures';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'widow_loan_id',
        'hardship_case_id',
        'old_outstanding_balance',
        'old_duration_remaining',
        'new_duration',
        'new_repayment_frequency',
        'new_installment_amount',
        'effective_date',
        'reason',
        'status',
        'requested_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'status' => WidowLoanRestructureStatus::class,
        'new_repayment_frequency' => LoanRepaymentFrequency::class,
        'effective_date' => 'date',
        'approved_at' => 'datetime',
        'old_outstanding_balance' => 'decimal:2',
        'new_installment_amount' => 'decimal:2',
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(WidowLoan::class, 'widow_loan_id');
    }

    public function hardshipCase(): BelongsTo
    {
        return $this->belongsTo(WidowLoanHardshipCase::class, 'hardship_case_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function supersededSchedules(): HasMany
    {
        return $this->hasMany(WidowLoanSchedule::class, 'superseded_by');
    }
}
