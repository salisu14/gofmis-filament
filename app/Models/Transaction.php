<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\TransactionLine;

class Transaction extends Model
{
    use HasUuids;

    protected $fillable = [
        'bank_account_id',
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

    // In app/Models/Transaction.php

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function transactionable(): MorphTo
    {
        return $this->morphTo();
    }

    public function transactionLines(): HasMany
    {
        return $this->hasMany(TransactionLine::class);
    }

    protected static function booted(): void
    {
        static::created(function (Transaction $transaction) {
            if (! $transaction->is_system) {
                $transaction->postToBank();
            }
        });

        static::updated(function (Transaction $transaction) {
            if ($transaction->is_system || ! $transaction->wasChanged(['bank_account_id', 'type', 'amount'])) {
                return;
            }

            $transaction->reverseBankPosting(
                $transaction->getOriginal('bank_account_id'),
                $transaction->getOriginal('type'),
                (float) $transaction->getOriginal('amount')
            );

            $transaction->postToBank();
        });

        static::deleted(function (Transaction $transaction) {
            if (! $transaction->is_system) {
                $transaction->reverseBankPosting($transaction->bank_account_id, $transaction->type, (float) $transaction->amount);
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

        $this->isCreditType()
            ? $this->bankAccount->credit((float) $this->amount)
            : $this->bankAccount->debit((float) $this->amount);
    }

    public function reverseBankPosting(?string $bankAccountId, ?string $type, float $amount): void
    {
        $bankAccount = $bankAccountId ? BankAccount::find($bankAccountId) : null;

        if (! $bankAccount) {
            return;
        }

        $this->isCreditType($type)
            ? $bankAccount->debit($amount)
            : $bankAccount->credit($amount);
    }
}
