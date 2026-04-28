<?php
// app/Models/IdCard.php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class IdCard extends Model
{
    use HasUuids;

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'cardable_type',
        'cardable_id',
        'template_id',
        'card_number',
        'qr_code_path',
        'pdf_path',
        'issued_at',
        'expires_at',
        'printed_at',
        'status',
        'revocation_reason'
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'expires_at' => 'datetime',
        'printed_at' => 'datetime',
    ];

    public function cardable(): MorphTo
    {
        return $this->morphTo();
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(IdCardTemplate::class, 'template_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active'
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function markAsPrinted(): void
    {
        $this->update([
            'printed_at' => now(),
            'status' => 'active',
        ]);
    }

    public function revoke(string $reason): void
    {
        $this->update([
            'status' => 'revoked',
            'revocation_reason' => $reason,
        ]);
    }
}
