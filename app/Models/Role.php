<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use HasUuids;

    protected $primaryKey = 'uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected static function booted(): void
    {
        static::created(function (Role $role) {
            \App\Services\SecurityAuditService::log('ROLE_CREATED', "Role created: {$role->name}", auth()->user(), $role);
        });

        static::updated(function (Role $role) {
            \App\Services\SecurityAuditService::log('ROLE_UPDATED', "Role updated: {$role->name}", auth()->user(), $role);
        });

        static::deleted(function (Role $role) {
            \App\Services\SecurityAuditService::log('ROLE_DELETED', "Role deleted: {$role->name}", auth()->user(), $role);
        });
    }

    public function syncPermissions(...$permissions): static
    {
        $oldPermissions = $this->permissions()->pluck('name')->toArray();
        $result = parent::syncPermissions(...$permissions);
        $newPermissions = $this->permissions()->pluck('name')->toArray();

        if (array_diff($oldPermissions, $newPermissions) || array_diff($newPermissions, $oldPermissions)) {
            \App\Services\SecurityAuditService::log(
                'ROLE_PERMISSIONS_CHANGED',
                "Permissions changed for role {$this->name}",
                auth()->user(),
                $this,
                ['old_permissions' => $oldPermissions, 'new_permissions' => $newPermissions]
            );
        }

        return $result;
    }
}
