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
        // When a manual transaction is created, credit or debit the bank
        static::created(function (Transaction $transaction) {
            if ($transaction->bankAccount) {
                if (in_array($transaction->type, ['deposit', 'loan_repayment'])) {
                    $transaction->bankAccount->credit((float) $transaction->amount);
                } else {
                    $transaction->bankAccount->debit((float) $transaction->amount);
                }
            }
        });

        // If a manual transaction is deleted, reverse it
        static::deleted(function (Transaction $transaction) {
            if ($transaction->bankAccount) {
                if (in_array($transaction->type, ['deposit', 'loan_repayment'])) {
                    $transaction->bankAccount->debit((float) $transaction->amount);
                } else {
                    $transaction->bankAccount->credit((float) $transaction->amount);
                }
            }
        });
    }
}
