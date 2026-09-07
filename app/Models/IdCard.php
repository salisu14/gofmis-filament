<?php

// app/Models/IdCard.php

namespace App\Models;

use App\Enums\OrphanStatus;
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
        'revocation_reason',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'expires_at' => 'datetime',
        'printed_at' => 'datetime',
    ];

    public function cardable(): MorphTo
    {
        return $this->morphTo()->withTrashed();
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(IdCardTemplate::class, 'template_id');
    }

    protected static function booted(): void
    {
        static::saving(function (IdCard $card) {
            if ($card->isDirty('status') && $card->status === 'active') {
                if (! $card->beneficiaryIsEligible()) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'card' => 'This ID card cannot be activated because the beneficiary is not currently eligible.',
                    ]);
                }

                if ($card->hasOtherActiveCard()) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'card' => 'This beneficiary already has an active ID card.',
                    ]);
                }
            }
        });
    }

    public function hasOtherActiveCard(): bool
    {
        if (! $this->cardable_type || ! $this->cardable_id) {
            return false;
        }

        return static::query()
            ->where('cardable_type', $this->cardable_type)
            ->where('cardable_id', $this->cardable_id)
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->when($this->exists, fn ($q) => $q->where('id', '!=', $this->id))
            ->exists();
    }

    public function activate(): void
    {
        if (! $this->beneficiaryIsEligible()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'card' => 'This ID card cannot be activated because the beneficiary is not currently eligible.',
            ]);
        }

        if ($this->hasOtherActiveCard()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'card' => 'This beneficiary already has an active ID card.',
            ]);
        }

        $this->update([
            'status' => 'active',
            'issued_at' => $this->issued_at ?? now(),
            'revocation_reason' => null,
        ]);
    }

    public function reactivate(): void
    {
        if ($this->status !== 'revoked') {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'card' => 'Only revoked ID cards can be reactivated.',
            ]);
        }

        $this->activate();
    }

    public function isActive(): bool
    {
        return $this->status === 'active'
            && ($this->expires_at === null || $this->expires_at->isFuture())
            && $this->beneficiaryIsEligible();
    }

    public function markAsPrinted(): void
    {
        if (! $this->beneficiaryIsEligible()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'card' => 'This ID card cannot be activated because the beneficiary is not currently eligible.',
            ]);
        }

        if ($this->hasOtherActiveCard()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'card' => 'This beneficiary already has an active ID card.',
            ]);
        }

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

    public function beneficiaryIsEligible(): bool
    {
        $beneficiary = null;

        if ($this->cardable_type && $this->cardable_id && class_exists($this->cardable_type)) {
            $beneficiary = $this->cardable_type::withoutGlobalScopes()->find($this->cardable_id);
        }

        if (! $beneficiary) {
            return false;
        }

        if ($beneficiary instanceof Orphan) {
            return ($beneficiary->status === OrphanStatus::ACTIVE || $beneficiary->status === OrphanStatus::ACTIVE->value)
                && (bool) $beneficiary->is_eligible;
        }

        if ($beneficiary instanceof Widow) {
            return (bool) $beneficiary->is_eligible;
        }

        return false;
    }
}
