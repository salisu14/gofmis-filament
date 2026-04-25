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
    ];

    protected $casts = [
        'amount_due' => 'decimal:2',
        'due_date' => 'date',
        'is_paid' => 'boolean',
    ];

    public function widowLoan(): BelongsTo
    {
        return $this->belongsTo(WidowLoan::class);
    }
}
