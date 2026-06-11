<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ImprestFund extends Model
{
    use HasFactory, HasUuids, SoftDeletes;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'custodian_id',
        'bank_account_id',
        'location',
        'authorized_amount',
        'current_balance',
        'last_reconciled_at',
        'status',
        'notes',
    ];

    protected $casts = [
        'authorized_amount' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'last_reconciled_at' => 'datetime',
    ];

    public function custodian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'custodian_id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(ImprestTransaction::class, 'fund_id');
    }

    public function replenishments(): HasMany
    {
        return $this->hasMany(ImprestReplenishment::class, 'fund_id');
    }

    public function reconciliations(): HasMany
    {
        return $this->hasMany(ImprestReconciliation::class, 'fund_id');
    }

    public function activeTransactions(): HasMany
    {
        return $this->transactions()->where('status', 'active');
    }

    public function pendingTransactions(): HasMany
    {
        return $this->transactions()->where('status', 'pending');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function isLowBalance(): bool
    {
        $threshold = $this->authorized_amount * 0.20;
        return $this->current_balance < $threshold;
    }
}
