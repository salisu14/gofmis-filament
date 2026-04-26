<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ImprestReconciliation extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'fund_id',
        'reconciliation_date',
        'cash_on_hand',
        'receipts_total',
        'expected_balance',
        'actual_variance',
        'auditor_id',
        'custodian_id',
        'custodian_acknowledged',
        'notes',
        'variance_explanation',
        'status',
    ];

    protected $casts = [
        'reconciliation_date' => 'date',
        'cash_on_hand' => 'decimal:2',
        'receipts_total' => 'decimal:2',
        'expected_balance' => 'decimal:2',
        'actual_variance' => 'decimal:2',
        'custodian_acknowledged' => 'boolean',
    ];

    public function fund(): BelongsTo
    {
        return $this->belongsTo(ImprestFund::class, 'fund_id');
    }

    public function auditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auditor_id');
    }

    public function custodian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'custodian_id');
    }

    public function scopeFlagged($query)
    {
        return $query->where('status', 'flagged');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function isBalanced(): bool
    {
        return abs($this->actual_variance) < 0.01;
    }

    public function varianceSeverity(): string
    {
        $authorized = $this->fund?->authorized_amount ?? 1;
        $percentage = abs($this->actual_variance) / $authorized * 100;

        return match (true) {
            $percentage < 0.5 => 'negligible',
            $percentage < 2 => 'minor',
            $percentage < 5 => 'moderate',
            default => 'critical',
        };
    }
}
