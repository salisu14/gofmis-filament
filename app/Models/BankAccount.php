<?php

namespace App\Models;

use App\Exceptions\InsufficientBankBalanceException;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankAccount extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;
    protected $table = 'bank_accounts';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'account_name',
        'account_number',
        'opening_balance',  // The initial deposit amount when the account was registered
        'ledger_balance',   // Total actual cash currently sitting in the account
        'reserved_balance', // Funds tied up in "Pending" Approval Flows
        'user_id'
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'ledger_balance' => 'decimal:2',
        'reserved_balance' => 'decimal:2',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'bank_account_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Financial Logic
    |--------------------------------------------------------------------------
    */

    /**
     * Checks if there is enough money AFTER accounting for reserved funds.
     * Available Balance = Ledger Balance - Reserved Balance
     */
    public function hasSufficientFunds(float $amount): bool
    {
        $availableBalance = (float) $this->ledger_balance - (float) ($this->reserved_balance ?? 0);
        return $availableBalance >= $amount;
    }

    /**
     * Call this when an Approval Flow is created/reaches a specific step.
     */
    public function reserve(float $amount): void
    {
        if (!$this->hasSufficientFunds($amount)) {
            throw new InsufficientBankBalanceException('Cannot reserve funds: Available balance is too low.');
        }
        $this->increment('reserved_balance', $amount);
    }

    /**
     * Call this when a flow is REJECTED. Re-releases the "held" funds.
     */
    public function unreserve(float $amount): void
    {
        $this->decrement('reserved_balance', min($this->reserved_balance, $amount));
    }

    /**
     * Call this when a flow is FULLY APPROVED.
     * It moves funds from the "Reserved" state to an actual "Debit".
     */
    public function disburse(float $amount): void
    {
        $this->unreserve($amount);
        $this->debit($amount);
    }

    /**
     * Immediate debit for transactions.
     * Updates the actual ledger balance.
     * @throws InsufficientBankBalanceException
     */
    public function debit(float $amount): void
    {
        if (!$this->hasSufficientFunds($amount)) {
            throw new InsufficientBankBalanceException('Insufficient funds in bank account.');
        }
        $this->decrement('ledger_balance', $amount);
    }

    /**
     * Adds funds to the actual ledger balance.
     */
    public function credit(float $amount): void
    {
        $this->increment('ledger_balance', $amount);
    }
}
