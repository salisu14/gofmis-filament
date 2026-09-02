<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class OrphanEducation extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'orphan_educations';

    protected $fillable = [
        'reference',
        'orphan_id',
        'previous_enrollment_id',
        'institution_id',
        'orphan_class_id',
        'class_level',
        'academic_session',
        'progression_decision',
        'progression_reason',
        'recorded_by_id',
        'school_fee',
        'fee_frequency',
        'is_fee_supported',
        'support_amount',
        'is_current',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'is_current' => 'boolean',
        'is_fee_supported' => 'boolean',
        'school_fee' => 'decimal:2',
        'support_amount' => 'decimal:2',
        'started_at' => 'date',
        'ended_at' => 'date',
        'progression_decision' => \App\Enums\AcademicProgressionDecision::class,
    ];

    public function orphan(): BelongsTo
    {
        return $this->belongsTo(Orphan::class);
    }

    public function previousEnrollment(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_enrollment_id');
    }

    public function successorEnrollment(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(self::class, 'previous_enrollment_id');
    }

    public function orphanClass(): BelongsTo
    {
        return $this->belongsTo(OrphanClass::class, 'orphan_class_id');
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(EducationFeeInvoice::class, 'orphan_education_id');
    }

    /**
     * DYNAMIC ATTRIBUTE: Total amount paid across all invoices
     */
    public function getTotalPaidAttribute(): float
    {
        // Efficiently aggregate payments through related invoices
        return (float) $this->invoices()
            ->withSum('payments', 'amount')
            ->get()
            ->sum('payments_sum_amount');
    }

    /**
     * DYNAMIC ATTRIBUTE: Current outstanding balance
     */
    public function getBalanceAttribute(): float
    {
        $activeInvoicesAmount = (float) $this->invoices
            ->reject(fn ($invoice) => $invoice->isVoided())
            ->sum('amount');

        return $activeInvoicesAmount - $this->total_paid;
    }

    public function getLevelAttribute(): string
    {
        return $this->orphanClass?->name
            ?? $this->class_level
            ?? 'N/A';
    }

    protected static function booted(): void
    {
        static::creating(function (OrphanEducation $education): void {
            // Use empty() instead of ??= to catch both null and ""
            if (empty($education->reference)) {
                $education->reference = static::generateReference();
            }
        });
    }

    //    protected static function booted(): void
    //    {
    //        static::creating(function (OrphanEducation $education): void {
    //            $education->reference ??= static::generateReference();
    //        });
    //    }

    public static function generateReference(): string
    {
        do {
            $reference = 'EDU-ENR-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (static::where('reference', $reference)->exists());

        return $reference;
    }
}
