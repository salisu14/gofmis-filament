<?php

namespace App\Models;

use App\Enums\WidowLoanPromiseStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WidowLoanPromise extends Model
{
    use HasUuids;

    protected $table = 'widow_loan_promises';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'recovery_case_id',
        'widow_loan_id',
        'promised_amount',
        'promised_date',
        'status',
        'fulfilled_at',
        'broken_at',
    ];

    protected $casts = [
        'status' => WidowLoanPromiseStatus::class,
        'promised_amount' => 'decimal:2',
        'promised_date' => 'date',
        'fulfilled_at' => 'datetime',
        'broken_at' => 'datetime',
    ];

    public function recoveryCase(): BelongsTo
    {
        return $this->belongsTo(WidowLoanRecoveryCase::class, 'recovery_case_id');
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(WidowLoan::class, 'widow_loan_id');
    }
}
