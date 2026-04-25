<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Repayment extends Model
{
    use HasFactory;

    protected $table = 'repayments';

    protected $fillable = [
        'amount',
        'date_paid',
        'payment_method',
        'receipt_number',
        'loan_id',
        'widow_id',
        'user_id'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'date_paid' => 'datetime',
    ];

    // Relationships
    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function widow(): BelongsTo
    {
        return $this->belongsTo(Widow::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Accessor (Optional Helper)
    // If you strictly need to see the balance *after* this specific payment was made
    // (useful for historical receipts), calculate it here.
    public function getBalanceAfterAttribute(): float
    {
        // Get all repayments for this loan up to and including this one
        $totalPaid = $this->loan->repayments()
            ->where('id', '<=', $this->id)
            ->sum('amount');

        return max(0, $this->loan->amount - $totalPaid);
    }
}
