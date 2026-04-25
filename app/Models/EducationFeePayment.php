<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EducationFeePayment extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'education_fee_invoice_id',
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
}
