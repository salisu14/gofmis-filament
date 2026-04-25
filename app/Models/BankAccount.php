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
    protected $table = 'bank_accounts'; // Keeping your table name

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'account_name',
        'account_number',
        'amount', // Treated as initial deposit
        'balance', // Treated as current live balance
        'user_id'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance' => 'decimal:2',
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        // Assuming you might want to track bank transaction logs later
        return $this->hasMany(Transaction::class, 'bank_account_id');
    }

    // Helpers
    public function hasSufficientFunds(float $amount): bool
    {
        return $this->balance >= $amount;
    }

    /**
     * @throws InsufficientBankBalanceException
     */
    public function debit(float $amount): void
    {
        if (!$this->hasSufficientFunds($amount)) {
            throw new InsufficientBankBalanceException('Insufficient funds in bank account.');
        }
        $this->decrement('balance', $amount);
    }

    public function credit(float $amount): void
    {
        $this->increment('balance', $amount);
    }
}
