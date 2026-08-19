<?php

namespace App\Models;

use App\Enums\WidowLoanHardshipStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class WidowLoanHardshipCase extends Model
{
    use HasUuids;

    protected $table = 'widow_loan_hardship_cases';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'widow_loan_id',
        'widow_id',
        'reported_by',
        'reason_category',
        'reason_details',
        'verification_notes',
        'supporting_document_path',
        'status',
        'recommended_action',
        'verified_by',
        'verified_at',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
    ];

    protected $casts = [
        'status' => WidowLoanHardshipStatus::class,
        'verified_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(WidowLoan::class, 'widow_loan_id');
    }

    public function widow(): BelongsTo
    {
        return $this->belongsTo(Widow::class, 'widow_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function reliefPeriods(): HasMany
    {
        return $this->hasMany(WidowLoanReliefPeriod::class, 'hardship_case_id');
    }

    public function restructures(): HasMany
    {
        return $this->hasMany(WidowLoanRestructure::class, 'hardship_case_id');
    }

    public function writeOffRecommendation(): HasOne
    {
        return $this->hasOne(WidowLoanWriteOffRecommendation::class, 'hardship_case_id');
    }
}
