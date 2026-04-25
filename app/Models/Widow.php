<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Widow extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'widows';

    protected $fillable = [
        'first_name',
        'last_name',
        'middle_name',
        'nin',
        'reg_no',
        'skills',
        'address',
        'picture_url',
        'is_eligible',
        'is_married',
        'deceased_id',
        'child_sequence',
         'full_name',
    ];

    protected $casts = [
        'is_eligible' => 'boolean',
        'is_married' => 'boolean',
        'married_at' => 'datetime',
        'skills' => 'array',
    ];

    public function prescriptions(): MorphMany
    {
        return $this->morphMany(Prescription::class, 'prescribable');
    }

    public function deceased(): BelongsTo
    {
        return $this->belongsTo(Deceased::class)->nullable();
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function widowLoans(): HasMany
    {
        return $this->hasMany(WidowLoan::class);
    }

    // Check if widow can apply for a new loan
    public function canApplyForLoan(): bool
    {
        $activeLoan = $this->widowLoans()->whereNotIn('status', [
            \App\Enums\WidowLoanStatus::COMPLETED->value,
            \App\Enums\WidowLoanStatus::REJECTED->value,
        ])->exists();

        return !$activeLoan;
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            $model->full_name = trim(implode(' ', array_filter([
                $model->first_name,
                $model->middle_name,
                $model->last_name
            ])));
        });

        static::updating(function ($model) {
            if ($model->isDirty(['first_name', 'middle_name', 'last_name'])) {
                $model->full_name = trim(implode(' ', array_filter([
                    $model->first_name,
                    $model->middle_name,
                    $model->last_name
                ])));
            }
        });
    }
}
