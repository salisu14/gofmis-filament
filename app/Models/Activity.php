<?php
// app/Models/Activity.php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\Activitylog\Contracts\Activity as ActivityContract;

class Activity extends Model implements ActivityContract
{
    use HasUuids;

    protected $table = 'activities';
    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'log_name',
        'description',
        'subject_id',
        'subject_type',
        'causer_id',
        'causer_type',
        'properties',
        'event',
        'batch_uuid',
    ];

    protected $casts = [
        'properties' => 'collection',
    ];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function causer(): MorphTo
    {
        return $this->morphTo();
    }

    public function getExtraProperty(string $propertyName): mixed
    {
        return $this->properties?->get($propertyName);
    }

    public function changes(): \Illuminate\Support\Collection
    {
        if (!$this->properties instanceof \Illuminate\Support\Collection) {
            return collect();
        }

        return $this->properties->get('attributes', collect());
    }

    public function getProperty(string $propertyName, mixed $defaultValue = null): mixed
    {
        // TODO: Implement getProperty() method.
    }
}
