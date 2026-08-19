<?php

namespace App\Models;

use App\Enums\WidowLoanRecoveryStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WidowLoanRecoveryCase extends Model
{
    use HasUuids;

    protected $table = 'widow_loan_recovery_cases';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'widow_loan_id',
        'opened_by',
        'opened_at',
        'status',
        'priority',
        'assigned_to',
        'current_action',
        'next_action_at',
        'closed_at',
        'closure_reason',
    ];

    protected $casts = [
        'status' => WidowLoanRecoveryStatus::class,
        'opened_at' => 'datetime',
        'next_action_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(WidowLoan::class, 'widow_loan_id');
    }

    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(WidowLoanRecoveryActivity::class, 'recovery_case_id');
    }

    public function promises(): HasMany
    {
        return $this->hasMany(WidowLoanPromise::class, 'recovery_case_id');
    }
}
