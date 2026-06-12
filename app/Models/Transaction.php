<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Transaction extends Model
{
    use HasUuids;

    protected $fillable = [
        'bank_account_id',
        'destination_bank_account_id', // ✅ NEW
        'reference',
        'description',
        'amount',
        'type',
        'date',
        'is_system',
        'transactionable_type',
        'transactionable_id',
    ];

    protected $casts = [
        'date' => 'datetime',
        'amount' => 'decimal:2',
        'is_system' => 'boolean',
    ];

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    // ✅ NEW: Relationship to the destination account
    public function destinationBankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'destination_bank_account_id');
    }

    public function transactionable(): MorphTo
    {
        return $this->morphTo();
    }

    public function transactionLines(): HasMany
    {
        return $this->hasMany(TransactionLine::class);
    }

    public function isInternalTransfer(): bool
    {
        // A transfer is internal if both accounts share the same top-level parent
        if (!$this->destination_bank_account_id || !$this->bank_account_id) {
            return false;
        }

        $sourceTopParent = $this->bankAccount?->parent ?? $this->bankAccount;
        $destTopParent = $this->destinationBankAccount?->parent ?? $this->destinationBankAccount;

        return $sourceTopParent?->id === $destTopParent?->id;
    }

    protected static function booted(): void
    {
        static::creating(function (Transaction $transaction): void {
            if ((float) $transaction->amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Transaction amount must be greater than zero.',
                ]);
            }

            if ($transaction->type === 'transfer' && $transaction->bank_account_id === $transaction->destination_bank_account_id) {
                throw ValidationException::withMessages([
                    'destination_bank_account_id' => 'Destination account must be different from the source account.',
                ]);
            }

            $transaction->date ??= now();
            $transaction->reference ??= static::generateReference($transaction->type);
        });

        static::created(function (Transaction $transaction) {
            if (!$transaction->is_system) {
                $transaction->postToBank();
            }
        });

        static::updated(function (Transaction $transaction) {
            if ($transaction->is_system || ! $transaction->wasChanged(['bank_account_id', 'destination_bank_account_id', 'type', 'amount'])) {
                return;
            }

            // Reverse the old posting
            $transaction->reverseBankPosting(
                $transaction->getOriginal('bank_account_id'),
                $transaction->getOriginal('destination_bank_account_id'),
                $transaction->getOriginal('type'),
                (float) $transaction->getOriginal('amount')
            );

            // Post the new state
            $transaction->postToBank();
        });

        static::deleted(function (Transaction $transaction) {
            if (!$transaction->is_system) {
                $transaction->reverseBankPosting(
                    $transaction->bank_account_id,
                    $transaction->destination_bank_account_id,
                    $transaction->type,
                    (float) $transaction->amount
                );
            }
        });
    }

    public function isCreditType(?string $type = null): bool
    {
        return in_array($type ?? $this->type, [
            'deposit',
            'loan_repayment',
            'imprest_replenishment_reversal',
            'imprest_expense_void',
        ], true);
    }

    public static function generateReference(?string $type = null): string
    {
        $prefix = match ($type) {
            'deposit' => 'DEP',
            'withdrawal' => 'WD',
            'transfer' => 'TRF',
            'loan_repayment' => 'REP',
            'loan_disbursement' => 'DISB',
            'imprest_funding' => 'IMPF',
            'imprest_replenishment' => 'IMPR',
            'imprest_expense' => 'IMPE',
            'imprest_expense_void' => 'IMPV',
            'education_fee_payment' => 'EDUP',
            'education_fee_payment_void' => 'EDUV',
            'intervention' => 'INTV',
            default => 'TXN',
        };

        do {
            $reference = $prefix.'-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (static::where('reference', $reference)->exists());

        return $reference;
    }

    public function postToBank(): void
    {
        $bankAccount = $this->bank_account_id
            ? BankAccount::query()->whereKey($this->bank_account_id)->lockForUpdate()->first()
            : null;

        if (! $bankAccount) {
            return;
        }

        if (! $this->is_system && ! $bankAccount->canPerformManualBankMovement()) {
            throw ValidationException::withMessages([
                'bank_account_id' => 'Manual bank transactions can only be posted against parent accounts.',
            ]);
        }

        // 1. Handle Source Account
        $this->isCreditType()
            ? $bankAccount->credit((float) $this->amount)
            : $bankAccount->debit((float) $this->amount);

        // 2. ✅ NEW: Handle Destination Account (for transfers)
        if ($this->destination_bank_account_id) {
            $destinationBankAccount = BankAccount::query()
                ->whereKey($this->destination_bank_account_id)
                ->lockForUpdate()
                ->first();

            // If type is transfer, money leaves source (debit) and enters destination (credit)
            // The source is already debited above by the else clause.
            $destinationBankAccount?->credit((float) $this->amount);
        }
    }

    public function reverseBankPosting(?string $bankAccountId, ?string $destBankAccountId, ?string $type, float $amount): void
    {
        $bankAccount = $bankAccountId ? BankAccount::query()->whereKey($bankAccountId)->lockForUpdate()->first() : null;
        $destBankAccount = $destBankAccountId ? BankAccount::query()->whereKey($destBankAccountId)->lockForUpdate()->first() : null;

        if ($bankAccount) {
            $this->isCreditType($type)
                ? $bankAccount->debit($amount)
                : $bankAccount->credit($amount);
        }

        // ✅ NEW: Reverse the destination side if it was a transfer
        if ($destBankAccount) {
            // Since the original posting credited the destination, we debit it to reverse
            $destBankAccount->debit($amount);
        }
    }
}
