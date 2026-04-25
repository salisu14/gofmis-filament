<?php

namespace App\Models;

use App\Enums\WidowLoanStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WidowLoan extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'widow_loans';

    protected $fillable = [
        'widow_id',
        'principal_amount',
        'duration_months',
        'total_payable',
        'total_paid',
        'outstanding_balance',
        'status',
        'disbursed_at',
        'approval_flow_id',
        'purpose',
        'fully_repaid',
        'loan_agreement_url',
        'reject_reason',
    ];

    protected $casts = [
        'principal_amount' => 'decimal:2',
        'total_payable' => 'decimal:2',
        'total_paid' => 'decimal:2',
        'outstanding_balance' => 'decimal:2',
        'disbursed_at' => 'datetime',
        'fully_repaid' => 'boolean',
        'status' => WidowLoanStatus::class,
    ];

    public function widow(): BelongsTo
    {
        return $this->belongsTo(Widow::class);
    }

    public function repayments(): HasMany
    {
        return $this->hasMany(WidowLoanRepayment::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(WidowLoanSchedule::class);
    }

    public function transactions(): MorphMany
    {
        return $this->morphMany(Transaction::class, 'transactionable');
    }

    // Helper methods
    public function isCompleted(): bool
    {
        return $this->status === WidowLoanStatus::COMPLETED;
    }

    public function isFullyRepaid(): bool
    {
        return $this->fully_repaid;
    }
}
