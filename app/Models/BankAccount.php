<?php

namespace App\Models;

use App\Exceptions\InsufficientBankBalanceException;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class BankAccount extends Model
{
    use HasUuids;

    public const USAGE_GENERAL = 'general';
    public const USAGE_WIDOW_LOAN_DISBURSEMENT = 'widow_loan_disbursement';
    public const USAGE_WIDOW_LOAN_REPAYMENT = 'widow_loan_repayment';
    public const USAGE_INTERVENTION = 'intervention';
    public const USAGE_IMPREST = 'imprest';
    public const USAGE_EDUCATION = 'education';
    public const USAGE_OTHER = 'other';

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
        'user_id',
        'parent_bank_account_id',
        'usage',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'ledger_balance' => 'decimal:2',
        'reserved_balance' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function ($account) {
            if (is_null($account->parent_bank_account_id)) {
                $account->usage = self::USAGE_GENERAL;
            }

            if (! is_null($account->parent_bank_account_id)) {
                $account->opening_balance = 0;
                $account->ledger_balance = 0;
            }

            // Initialize ledger balance with opening balance if it's a new account
            if (is_null($account->ledger_balance)) {
                $account->ledger_balance = $account->opening_balance ?? 0;
            }
        });

        static::saving(function ($account) {
            if (is_null($account->parent_bank_account_id)) {
                $account->usage = self::USAGE_GENERAL;

                return;
            }

            if (($account->usage ?: self::USAGE_GENERAL) === self::USAGE_GENERAL) {
                throw ValidationException::withMessages([
                    'usage' => 'Child accounts must be assigned a dedicated usage.',
                ]);
            }
        });
    }

    public static function usageOptions(): array
    {
        return [
            self::USAGE_GENERAL => 'General / Parent Operating Account',
            self::USAGE_WIDOW_LOAN_DISBURSEMENT => 'Widow Loan Disbursement',
            self::USAGE_WIDOW_LOAN_REPAYMENT => 'Widow Loan Repayment',
            self::USAGE_INTERVENTION => 'Interventions',
            self::USAGE_IMPREST => 'Imprest',
            self::USAGE_EDUCATION => 'Education Fees',
            self::USAGE_OTHER => 'Other Dedicated Use',
        ];
    }

    public function getUsageLabelAttribute(): string
    {
        return self::usageOptions()[$this->usage] ?? str($this->usage)->headline()->toString();
    }

    public function scopeDedicatedTo(Builder $query, string|array $usage): Builder
    {
        return $query
            ->whereNotNull('parent_bank_account_id')
            ->whereIn('usage', (array) $usage);
    }

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
        if ($amount <= 0) {
            return false;
        }

        $availableBalance = (float) $this->ledger_balance - (float) ($this->reserved_balance ?? 0);
        return $availableBalance >= $amount;
    }

    /**
     * Call this when an Approval Flow is created/reaches a specific step.
     */
    public function reserve(float $amount): void
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be greater than zero.');
        }

        if (!$this->hasSufficientFunds($amount)) {
            throw new InsufficientBankBalanceException('Cannot reserve funds: Available balance is too low.');
        }
        $this->reserved_balance = (float) $this->reserved_balance + $amount;
        $this->save();
    }

    /**
     * Call this when a flow is REJECTED. Re-releases the "held" funds.
     */
    public function unreserve(float $amount): void
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be greater than zero.');
        }

        $this->reserved_balance = max(0, (float) $this->reserved_balance - $amount);
        $this->save();
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
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be greater than zero.');
        }

        if (!$this->hasSufficientFunds($amount)) {
            throw new InsufficientBankBalanceException('Insufficient funds in bank account.');
        }
        $this->ledger_balance = (float) $this->ledger_balance - $amount;
        $this->save();
    }

    /**
     * Adds funds to the actual ledger balance.
     */
    public function credit(float $amount): void
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be greater than zero.');
        }

        $this->ledger_balance = (float) $this->ledger_balance + $amount;
        $this->save();
    }

    /*
    |--------------------------------------------------------------------------
    | Parent / Child Relationships
    |--------------------------------------------------------------------------
    */

    public function parent(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'parent_bank_account_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(BankAccount::class, 'parent_bank_account_id');
    }

    public function isSubAccount(): bool
    {
        return !is_null($this->parent_bank_account_id);
    }

    public function isMainAccount(): bool
    {
        return is_null($this->parent_bank_account_id) && $this->children()->exists();
    }

    public function canPerformManualBankMovement(): bool
    {
        return ! $this->isSubAccount();
    }

    public function isDedicatedTo(string|array $usage): bool
    {
        return $this->isSubAccount() && in_array($this->usage, (array) $usage, true);
    }

    public function ensureDedicatedTo(string|array $usage, string $workflowName = 'this workflow'): void
    {
        if ($this->isDedicatedTo($usage)) {
            return;
        }

        throw ValidationException::withMessages([
            'bank_account_id' => "Please select a child bank account dedicated to {$workflowName}.",
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Consolidated Balance Logic
    |--------------------------------------------------------------------------
    */

    /**
     * Get the real-world balance of this account.
     * If it's a Main Account, it sums up its own balance + all children.
     * If it's a Sub-Account, it just returns its own balance.
     */
    public function getConsolidatedBalanceAttribute(): float
    {
        if ($this->isMainAccount()) {
            $childrenBalance = $this->children()->sum('ledger_balance');
            return (float) ($this->ledger_balance + $childrenBalance);
        }

        return (float) $this->ledger_balance;
    }

    /**
     * Get the consolidated available balance (ledger - reserved)
     */
    public function getConsolidatedAvailableBalanceAttribute(): float
    {
        if ($this->isMainAccount()) {
            $childrenLedger = $this->children()->sum('ledger_balance');
            $childrenReserved = $this->children()->sum('reserved_balance');

            $totalLedger = (float) $this->ledger_balance + $childrenLedger;
            $totalReserved = (float) $this->reserved_balance + $childrenReserved;

            return $totalLedger - $totalReserved;
        }

        return (float) $this->ledger_balance - (float) $this->reserved_balance;
    }
}
