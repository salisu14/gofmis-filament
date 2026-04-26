<?php

namespace App\Models;

use App\Models\Scopes\Imprest\ImprestTransactionQueryBuilder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ImprestTransaction extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'fund_id',
        'date',
        'deceased_id',
        'name',
        'item_service',
        'quantity',
        'unit_price',
        'total_price',
        'voucher_no',
        'receipt_attached',
        'custodian_id',
        'approved_by',
        'category',
        'payment_method',
        'status',
        'void_reason',
        'approved_at',
        'voided_at',
    ];

    protected $casts = [
        'date' => 'date',
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'receipt_attached' => 'boolean',
        'approved_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function ($transaction) {
            if (empty($transaction->voucher_no)) {
                $transaction->voucher_no = self::generateVoucherNo();
            }
            $transaction->total_price = $transaction->quantity * $transaction->unit_price;
        });

        static::updating(function ($transaction) {
            if ($transaction->isDirty(['quantity', 'unit_price'])) {
                $transaction->total_price = $transaction->quantity * $transaction->unit_price;
            }
        });
    }

    public static function generateVoucherNo(): string
    {
        $prefix = 'VCH-' . now()->format('Ymd');
        $last = self::withTrashed()
            ->where('voucher_no', 'like', $prefix . '%')
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $last ? (int) substr($last->voucher_no, -4) + 1 : 1;
        return $prefix . '-' . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    public function fund(): BelongsTo
    {
        return $this->belongsTo(ImprestFund::class, 'fund_id');
    }

    public function custodian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'custodian_id');
    }

    public function newEloquentBuilder($query): ImprestTransactionQueryBuilder
    {
        return new ImprestTransactionQueryBuilder($query);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeVoided($query)
    {
        return $query->where('status', 'voided');
    }

    public function scopeForDeceased($query, string $deceasedId)
    {
        return $query->where('deceased_id', $deceasedId);
    }

    public function scopeInDateRange($query, $start, $end)
    {
        return $query->whereBetween('date', [$start, $end]);
    }

    public function isApproved(): bool
    {
        return $this->status === 'active' && !is_null($this->approved_at);
    }

    public function isVoidable(): bool
    {
        return in_array($this->status, ['active', 'pending']);
    }

    public function getTotalPriceAttribute($value): float
    {
        return (float) ($value ?? $this->quantity * $this->unit_price);
    }
}
