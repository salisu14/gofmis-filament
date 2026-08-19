<?php

namespace App\Models;

use App\Enums\WidowLoanWriteOffRecommendationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WidowLoanWriteOffRecommendation extends Model
{
    use HasUuids;

    protected $table = 'widow_loan_write_off_recommendations';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'widow_loan_id',
        'hardship_case_id',
        'recovery_case_id',
        'recommended_amount',
        'reason',
        'recommended_by',
        'recommended_at',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
    ];

    protected $casts = [
        'status' => WidowLoanWriteOffRecommendationStatus::class,
        'recommended_amount' => 'decimal:2',
        'recommended_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(WidowLoan::class, 'widow_loan_id');
    }

    public function hardshipCase(): BelongsTo
    {
        return $this->belongsTo(WidowLoanHardshipCase::class, 'hardship_case_id');
    }

    public function recoveryCase(): BelongsTo
    {
        return $this->belongsTo(WidowLoanRecoveryCase::class, 'recovery_case_id');
    }

    public function recommender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recommended_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
