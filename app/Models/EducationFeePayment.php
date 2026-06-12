<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EducationFeePayment extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'education_fee_invoice_id',
        'bank_account_id',
        'amount',
        'payment_date',
        'payment_method',
        'reference',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
    ];

    /**
     * Get the invoice this payment is for.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(EducationFeeInvoice::class, 'education_fee_invoice_id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function transaction(): MorphOne
    {
        return $this->morphOne(Transaction::class, 'transactionable');
    }

    protected static function booted(): void
    {
        static::creating(function (EducationFeePayment $payment): void {
            if (empty($payment->reference)) {
                $payment->reference = static::generateReference();
            }
        });

        static::saving(function (EducationFeePayment $payment): void {
            $payment->assertPayable();
        });

        // When a payment is created or updated, refresh the parent invoice status
        static::saved(function (EducationFeePayment $payment) {
            $payment->invoice->refreshPaymentStatus();
        });

        // If a payment is deleted, also refresh the parent invoice status
        static::deleted(function (EducationFeePayment $payment): void {
            $payment->invoice?->refreshPaymentStatus();
        });

        static::restored(function (EducationFeePayment $payment): void {
            $payment->invoice?->refreshPaymentStatus();
        });
    }

    public static function generateReference(): string
    {
        do {
            $reference = 'EDU-PAY-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6));
        } while (static::where('reference', $reference)->exists());

        return $reference;
    }

    private function assertPayable(): void
    {
        $invoice = $this->invoice()->first();

        if (!$invoice) {
            return;
        }

        if ($invoice->status === 'cancelled') {
            throw ValidationException::withMessages([
                'education_fee_invoice_id' => 'Payments cannot be recorded against a cancelled invoice.',
            ]);
        }

        $otherPayments = $invoice->payments()
            ->when($this->exists, fn($query) => $query->whereKeyNot($this->getKey()))
            ->sum('amount');

        if (((float)$otherPayments + (float)$this->amount) > (float)$invoice->amount) {
            throw ValidationException::withMessages([
                'amount' => 'This payment would exceed the outstanding invoice balance.',
            ]);
        }
    }
}
