<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use HasUuids;

    protected $primaryKey = 'uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    public static function findByIdentifier(mixed $identifier): ?static
    {
        if ($identifier instanceof SpatieRole || $identifier instanceof self) {
            return $identifier;
        }

        if (! is_string($identifier)) {
            return null;
        }

        if (Str::isUuid($identifier)) {
            return static::where('uuid', $identifier)->orWhere('name', $identifier)->first();
        }

        return static::where('name', $identifier)->first();
    }

    public static function findByIdentifiers(array $identifiers): \Illuminate\Database\Eloquent\Collection
    {
        $uuids = collect($identifiers)->filter(fn ($v) => is_string($v) && Str::isUuid($v))->values()->all();
        $names = collect($identifiers)->filter(fn ($v) => is_string($v) && ! Str::isUuid($v))->values()->all();

        return static::query()
            ->where(function ($q) use ($uuids, $names) {
                if (! empty($uuids)) {
                    $q->whereIn('uuid', $uuids);
                }
                if (! empty($names)) {
                    if (! empty($uuids)) {
                        $q->orWhereIn('name', $names);
                    } else {
                        $q->whereIn('name', $names);
                    }
                }
            })
            ->get();
    }

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
