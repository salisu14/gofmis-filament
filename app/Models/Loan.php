<?php

namespace App\Models;

use App\Enums\LoanStatus;
use App\Traits\HasLoanStatusTransitions;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Loan extends Model
{
    use HasLoanStatusTransitions;

    protected $fillable = [
        'widow_id',
        'amount',
        'original_amount',
        'paid_at',
        'collected_at',
        'approved_at',
        'description',
        'reject_reason',
        'business',
        'status'
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'approved_at' => 'datetime',
        'collected_at' => 'datetime',
        'amount' => 'decimal:2',
        'original_amount' => 'decimal:2',
    ];

    // Relationships
    public function widow(): BelongsTo
    {
        return $this->belongsTo(Widow::class);
    }

    public function repayments(): HasMany
    {
        return $this->hasMany(Repayment::class);
    }

    // Accessors / Mutators
    protected function status(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? LoanStatus::from($value) : null,
            set: fn (LoanStatus|string $value) => $value instanceof LoanStatus ? $value->value : $value
        );
    }

    // Helper to calculate remaining balance
    public function getBalanceAttribute(): float
    {
        $paid = $this->repayments()->sum('amount');
        return max(0, $this->amount - $paid);
    }
}
