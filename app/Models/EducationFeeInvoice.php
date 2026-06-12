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

    public const STATUS_PENDING = 'pending';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_VOID = 'void';

    public const FINAL_STATUSES = [
        self::STATUS_PAID,
        self::STATUS_CANCELLED,
        self::STATUS_VOID,
    ];

    protected $fillable = [
        'reference',
        'orphan_education_id',
        'amount',
        'due_date',
        'period',
        'status',
        'void_reason',
        'voided_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'due_date' => 'date',
        'voided_at' => 'datetime',
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
        $paid = $this->relationLoaded('payments')
            ? $this->payments->sum('amount')
            : $this->payments()->sum('amount');

        return max(0, (float) $this->amount - (float) $paid);
    }

    public function getPaidAmountAttribute(): float
    {
        return (float) ($this->relationLoaded('payments')
            ? $this->payments->sum('amount')
            : $this->payments()->sum('amount'));
    }

    public function refreshPaymentStatus(): void
    {
        if ($this->isVoided()) {
            return;
        }

        $paid = $this->paid_amount;
        $amount = (float) $this->amount;

        $newStatus = match (true) {
            $paid <= 0 => self::STATUS_PENDING,
            $paid >= $amount => self::STATUS_PAID,
            default => self::STATUS_PARTIAL,
        };

        // Only save if the status actually changed to avoid infinite loops or unnecessary saves
        if ($this->status !== $newStatus) {
            $this->forceFill(['status' => $newStatus])->saveQuietly();
        }
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isVoided(): bool
    {
        return in_array($this->status, [self::STATUS_CANCELLED, self::STATUS_VOID], true);
    }

    public function isFinalized(): bool
    {
        return in_array($this->status, self::FINAL_STATUSES, true);
    }

    public function hasPayments(): bool
    {
        return $this->payments()->exists();
    }

    protected static function booted(): void
    {
        static::creating(function (EducationFeeInvoice $invoice): void {

            if (empty($invoice->reference)) {
                $invoice->reference = static::generateReference();
            }
            $invoice->status ??= self::STATUS_PENDING;
        });

        static::saving(function (EducationFeeInvoice $invoice): void {
            if (! $invoice->exists) {
                return;
            }

            $originalStatus = $invoice->getOriginal('status');
            $wasFinalized = in_array($originalStatus, self::FINAL_STATUSES, true);
            $hasPayments = $invoice->payments()->exists();

            if (($wasFinalized || $hasPayments) && $invoice->isDirty('amount')) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'amount' => 'Invoice amount cannot be changed once payments have started or the invoice is finalized.',
                ]);
            }
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
