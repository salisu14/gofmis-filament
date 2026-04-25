<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrphanEducation extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'orphan_educations';

    protected $fillable = [
        'orphan_id',
        'institution_id',
        'level',
        'class_level',
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
    ];

    public function orphan(): BelongsTo
    {
        return $this->belongsTo(Orphan::class);
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
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
        return (float) $this->invoices->sum('amount') - $this->total_paid;
    }
}
