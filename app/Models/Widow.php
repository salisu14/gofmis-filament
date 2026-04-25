<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Widow extends Model
{
    use HasUuids;

    protected $fillable = [
        'first_name', 'last_name', 'nin', 'reg_no', 'skills',
        'address', 'picture_url', 'deceased_id'
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

    protected static function boot()
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
