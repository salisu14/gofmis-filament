<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ImprestReplenishment extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'fund_id',
        'period_start',
        'period_end',
        'amount',
        'receipts_total',
        'variance',
        'requested_by',
        'approved_by',
        'status',
        'processed_at',
        'notes',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'amount' => 'decimal:2',
        'receipts_total' => 'decimal:2',
        'variance' => 'decimal:2',
        'processed_at' => 'datetime',
    ];

    public function fund(): BelongsTo
    {
        return $this->belongsTo(ImprestFund::class, 'fund_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'submitted');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function isProcessed(): bool
    {
        return $this->status === 'processed' && !is_null($this->processed_at);
    }
}
