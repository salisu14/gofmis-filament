<?php

namespace App\Models;

use App\Enums\WidowLoanRecoveryActivityType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WidowLoanRecoveryActivity extends Model
{
    use HasUuids;

    protected $table = 'widow_loan_recovery_activities';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'recovery_case_id',
        'widow_loan_id',
        'activity_type',
        'notes',
        'contact_method',
        'promise_amount',
        'promise_date',
        'performed_by',
        'performed_at',
        'next_follow_up_at',
    ];

    protected $casts = [
        'activity_type' => WidowLoanRecoveryActivityType::class,
        'promise_amount' => 'decimal:2',
        'promise_date' => 'date',
        'performed_at' => 'datetime',
        'next_follow_up_at' => 'datetime',
    ];

    public function recoveryCase(): BelongsTo
    {
        return $this->belongsTo(WidowLoanRecoveryCase::class, 'recovery_case_id');
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(WidowLoan::class, 'widow_loan_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
