<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EducationFeeInvoice extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'orphan_education_id',
        'amount',
        'due_date',
        'period',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'due_date' => 'date',
    ];

    /**
     * Get the enrollment/education record this invoice belongs to.
     */
    public function education(): BelongsTo
    {
        return $this->belongsTo(OrphanEducation::class, 'orphan_education_id');
    }

    /**
     * Get the payments made against this invoice.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(EducationFeePayment::class);
    }

    /**
     * Calculate the remaining balance on this invoice.
     */
    public function getBalanceAttribute(): float
    {
        return (float) $this->amount - (float) $this->payments()->sum('amount');
    }
}
