<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        return in_array($type ?? $this->type, ['deposit', 'loan_repayment', 'imprest_replenishment_reversal'], true);
    }

    public function postToBank(): void
    {
        if (! $this->bankAccount) {
            return;
        }

        // 1. Handle Source Account
        $this->isCreditType()
            ? $this->bankAccount->credit((float) $this->amount)
            : $this->bankAccount->debit((float) $this->amount);

        // 2. ✅ NEW: Handle Destination Account (for transfers)
        if ($this->destination_bank_account_id && $this->destinationBankAccount) {
            // If type is transfer, money leaves source (debit) and enters destination (credit)
            // The source is already debited above by the else clause.
            $this->destinationBankAccount->credit((float) $this->amount);
        }
    }

    public function reverseBankPosting(?string $bankAccountId, ?string $destBankAccountId, ?string $type, float $amount): void
    {
        $bankAccount = $bankAccountId ? BankAccount::find($bankAccountId) : null;
        $destBankAccount = $destBankAccountId ? BankAccount::find($destBankAccountId) : null;

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
