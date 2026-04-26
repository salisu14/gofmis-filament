<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WidowLoanRepayment extends Model
{
    use HasUuids;

    protected $table = 'widow_loan_repayments';

    protected $fillable = [
        'widow_loan_id',
        'amount',
        'paid_at',
        'payment_method',
        'transaction_id',
        'notes',
    ];

    protected static function booted()
    {
        parent::booted();

        static::saved(function ($repayment) {
            $repayment->widowLoan?->refreshBalance();
        });

        static::deleted(function ($repayment) {
            $repayment->widowLoan?->refreshBalance();
        });
    }

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'date',
    ];

    public function widowLoan(): BelongsTo
    {
        return $this->belongsTo(WidowLoan::class, 'widow_loan_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
