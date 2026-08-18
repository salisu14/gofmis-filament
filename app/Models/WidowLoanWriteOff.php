<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WidowLoanWriteOff extends Model
{
    use HasUuids;

    protected $table = 'widow_loan_write_offs';

    protected $fillable = [
        'widow_loan_id',
        'original_outstanding_balance',
        'amount_written_off',
        'write_off_reason',
        'write_off_verification_notes',
        'write_off_document_path',
        'authorized_by',
        'authorized_at',
    ];

    protected $casts = [
        'original_outstanding_balance' => 'decimal:2',
        'amount_written_off' => 'decimal:2',
        'authorized_at' => 'datetime',
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(WidowLoan::class, 'widow_loan_id');
    }

    public function authorizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_by');
    }
}
