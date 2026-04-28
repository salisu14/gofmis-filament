<?php
// app/Models/IdCardTemplate.php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IdCardTemplate extends Model
{
    use HasUuids;

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'type',
        'layout_config',
        'background_image_path',
        'is_active'
    ];

    protected $casts = [
        'layout_config' => 'array',
        'is_active' => 'boolean',
    ];

    public function idCards(): HasMany
    {
        return $this->hasMany(IdCard::class, 'template_id');
    }
}
