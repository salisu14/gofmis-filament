<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WidowLoanCounterFunding extends Model
{
    use HasUuids;

    protected $table = 'widow_loan_counter_fundings';

    protected $fillable = [
        'widow_loan_id',
        'amount',
        'recorded_by',
        'transaction_date',
        'balance_before',
        'balance_after',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'transaction_date' => 'date',
    ];

    public function widowLoan(): BelongsTo
    {
        return $this->belongsTo(WidowLoan::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
