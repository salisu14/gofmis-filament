<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OutOfPocketExpenditure extends Model
{
    use HasUuids;

    protected $table = 'out_of_pocket_expenditures';

    protected $fillable = [
        'reference',
        'expenditure_date',
        'incurred_by_user_id',
        'payee_name',
        'category',
        'description',
        'amount',
        'payment_method',
        'reimbursement_required',
        'reimbursement_status',
        'reimbursement_bank_account_id',
        'reimbursement_transaction_id',
        'approval_status',
        'submitted_by_id',
        'approved_by_id',
        'approved_at',
        'rejected_by_id',
        'rejected_at',
        'rejection_reason',
        'reimbursed_by_id',
        'reimbursed_at',
        'receipt_path',
        'notes',
    ];

    protected $casts = [
        'expenditure_date' => 'date',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'reimbursed_at' => 'datetime',
        'amount' => 'decimal:2',
        'reimbursement_required' => 'boolean',
    ];

    public function incurredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'incurred_by_user_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by_id');
    }

    public function reimbursedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reimbursed_by_id');
    }

    public function reimbursementBankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'reimbursement_bank_account_id');
    }

    public function reimbursementTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'reimbursement_transaction_id');
    }

    public static function generateReference(): string
    {
        do {
            $ref = 'OOP-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (static::where('reference', $ref)->exists());

        return $ref;
    }

    protected static function booted(): void
    {
        static::creating(function (OutOfPocketExpenditure $model): void {
            if ((float) $model->amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Expenditure amount must be greater than zero.',
                ]);
            }

            $model->reference ??= static::generateReference();
            $model->expenditure_date ??= now()->toDateString();
            $model->approval_status ??= 'draft';

            if (! $model->reimbursement_required) {
                $model->reimbursement_status = 'not_required';
            } else {
                $model->reimbursement_status ??= 'pending';
            }
        });

        static::updating(function (OutOfPocketExpenditure $model): void {
            if ((float) $model->amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Expenditure amount must be greater than zero.',
                ]);
            }
        });
    }

    public function isDraft(): bool
    {
        return $this->approval_status === 'draft';
    }

    public function isSubmitted(): bool
    {
        return $this->approval_status === 'submitted';
    }

    public function isApproved(): bool
    {
        return $this->approval_status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->approval_status === 'rejected';
    }

    public function isReimbursed(): bool
    {
        return $this->reimbursement_status === 'reimbursed';
    }

    public function isPendingReimbursement(): bool
    {
        return $this->isApproved()
            && $this->reimbursement_required
            && $this->reimbursement_status === 'pending';
    }
}
