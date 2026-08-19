<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WidowLoanReliefPeriod extends Model
{
    use HasUuids;

    protected $table = 'widow_loan_relief_periods';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'widow_loan_id',
        'hardship_case_id',
        'starts_at',
        'ends_at',
        'reason',
        'approved_by',
        'approved_at',
        'status',
    ];

    protected $casts = [
        'starts_at' => 'date',
        'ends_at' => 'date',
        'approved_at' => 'datetime',
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(WidowLoan::class, 'widow_loan_id');
    }

    public function hardshipCase(): BelongsTo
    {
        return $this->belongsTo(WidowLoanHardshipCase::class, 'hardship_case_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
