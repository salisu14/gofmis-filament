<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InterventionRequest extends Model
{
    use HasUuids;

    protected $fillable = [
        'orphan_id',
        'intervention_type_id',
        'status',
        'rejection_reason',
        'requested_level',
        'requested_amount',
        'notes',
        'supporting_documents',
        'verification_status',
        'verification_date',
        'verification_notes',
        'verification_documents',
        'verified_by',
        'verified_at',
        'reviewed_by',
        'reviewed_at',
        'approved_by',
        'approved_at',
        'request_date',
    ];

    protected $casts = [
        'requested_amount' => 'decimal:2',
        'supporting_documents' => 'array',
        'verification_documents' => 'array',
        'verified_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
        'request_date' => 'date',
    ];

    public function orphan(): BelongsTo
    {
        return $this->belongsTo(Orphan::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(InterventionType::class, 'intervention_type_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InterventionRequestItem::class);
    }

    // A request can generate one or more interventions (partial fulfillment)
    public function interventions(): HasMany
    {
        return $this->hasMany(Intervention::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeEducation(Builder $query): Builder
    {
        return $query->whereHas('type', fn (Builder $query) => $query
            ->whereRaw('LOWER(name) LIKE ?', ['%education%']));
    }

    public function isEducationRequest(): bool
    {
        return str_contains(strtolower((string) $this->type?->name), 'education');
    }

    public function canStartReview(): bool
    {
        return $this->status === 'pending';
    }

    public function canApproveRequest(): bool
    {
        if (! in_array($this->status, ['pending', 'under_review'], true)) {
            return false;
        }

        return ! $this->isEducationRequest() || $this->verification_status === 'verified';
    }

    public function canRejectRequest(): bool
    {
        return in_array($this->status, ['pending', 'under_review'], true);
    }

    public function startReview(?string $reviewedBy = null): void
    {
        if (! $this->canStartReview()) {
            throw new \RuntimeException('Only pending intervention requests can be moved to review.');
        }

        $this->update([
            'status' => 'under_review',
            'reviewed_by' => $reviewedBy ?? auth()->id(),
            'reviewed_at' => now(),
        ]);
    }

    public function markVerified(?string $verifiedBy = null, ?string $notes = null): void
    {
        $this->update([
            'status' => $this->status === 'pending' ? 'under_review' : $this->status,
            'verification_status' => 'verified',
            'verification_notes' => $notes ?? $this->verification_notes,
            'verified_by' => $verifiedBy ?? auth()->id(),
            'verified_at' => now(),
            'reviewed_by' => $this->reviewed_by ?? ($verifiedBy ?? auth()->id()),
            'reviewed_at' => $this->reviewed_at ?? now(),
        ]);
    }

    public function approveRequest(?string $approvedBy = null): void
    {
        if (! $this->canApproveRequest()) {
            throw new \RuntimeException('Education requests must be verified before approval.');
        }

        $this->update([
            'status' => 'approved',
            'approved_by' => $approvedBy ?? auth()->id(),
            'approved_at' => now(),
            'reviewed_by' => $this->reviewed_by ?? ($approvedBy ?? auth()->id()),
            'reviewed_at' => $this->reviewed_at ?? now(),
        ]);
    }

    public function rejectRequest(?string $reason = null, ?string $rejectedBy = null): void
    {
        if (! $this->canRejectRequest()) {
            throw new \RuntimeException('Only pending or under-review intervention requests can be rejected.');
        }

        $this->update([
            'status' => 'rejected',
            'verification_status' => $this->isEducationRequest() ? 'failed' : $this->verification_status,
            'rejection_reason' => $reason,
            'reviewed_by' => $rejectedBy ?? auth()->id(),
            'reviewed_at' => now(),
        ]);
    }

    protected static function booted(): void
    {
        static::updating(function ($request) {
            // Log who approved/rejected
            if ($request->isDirty('status') && in_array($request->status, ['approved', 'rejected'])) {
                \App\Models\Activity::create([
                    'user_id' => auth()->id(),
                    'action' => "education_request_{$request->status}",
                    'model_type' => self::class,
                    'model_id' => $request->id,
                    'details' => [
                        'old_status' => $request->getOriginal('status'),
                        'new_status' => $request->status,
                        'verification_notes' => $request->verification_notes,
                    ],
                ]);
            }
        });
    }
}
