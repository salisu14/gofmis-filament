<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    use HasUuids;

    protected $primaryKey = 'uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected static function booted(): void
    {
        static::created(function (Permission $permission) {
            \App\Services\SecurityAuditService::log('PERMISSION_CREATED', "Permission created: {$permission->name}", auth()->user(), $permission);
        });

        static::deleted(function (Permission $permission) {
            \App\Services\SecurityAuditService::log('PERMISSION_DELETED', "Permission deleted: {$permission->name}", auth()->user(), $permission);
        });
    }
}
