<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class EducationFeeInvoice extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'reference',
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
        return (float)$this->amount - (float)$this->payments()->sum('amount');
    }

    public function getPaidAmountAttribute(): float
    {
        return (float)$this->payments()->sum('amount');
    }

    public function refreshPaymentStatus(): void
    {
        if ($this->status === 'cancelled') {
            return;
        }

        $paid = $this->paid_amount;
        $amount = (float)$this->amount;

        $this->forceFill([
            'status' => match (true) {
                $paid <= 0 => 'pending',
                $paid >= $amount => 'paid',
                default => 'partial',
            },
        ])->saveQuietly();
    }

    protected static function booted(): void
    {
        static::creating(function (EducationFeeInvoice $invoice): void {

            if (empty($invoice->reference)) {
                $invoice->reference = static::generateReference();
            }
            $invoice->status ??= 'pending';
        });

        static::saved(function (EducationFeeInvoice $invoice): void {
            if (!$invoice->wasChanged('status')) {
                $invoice->refreshPaymentStatus();
            }
        });
    }

    public static function generateReference(): string
    {
        do {
            $reference = 'EDU-INV-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6));
        } while (static::where('reference', $reference)->exists());

        return $reference;
    }
}
