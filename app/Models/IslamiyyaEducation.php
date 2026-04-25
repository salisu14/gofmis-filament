<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IslamiyyaEducation extends Model
{
    use HasUuids;

    protected $fillable = ['islamiyya_name', 'class_form_master', 'school_fee_eligible'];

    protected $casts = [
        'school_fee_eligible' => 'boolean',
    ];

    public function orphans(): HasMany
    {
        return $this->hasMany(Orphan::class);
    }
}
