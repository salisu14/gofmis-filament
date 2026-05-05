<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PermissionsTableSeeder extends Seeder
{
    public function run()
    {
        $now = now();
        $guard = 'web';

        // Define permission patterns: [entity, [actions]]
        $permissionPatterns = [
            ['permission', ['create', 'edit', 'show', 'delete', 'access']],
            ['role', ['create', 'edit', 'show', 'delete', 'access']],
            ['user', ['create', 'edit', 'show', 'delete', 'access']],
            ['deceased', ['create', 'edit', 'show', 'delete', 'access']],
            ['widow', ['create', 'edit', 'show', 'delete', 'access']],
            ['orphan', ['create', 'edit', 'show', 'delete', 'access']],
            ['item', ['create', 'edit', 'delete', 'show', 'access', 'request']],
            ['state', ['create', 'edit', 'delete', 'show', 'access']],
            ['city', ['create', 'edit', 'show', 'delete', 'access']],
            ['town', ['create', 'edit', 'show', 'delete', 'access']],
            ['zone', ['create', 'edit', 'delete', 'show', 'access']],
            ['category', ['create', 'edit', 'show', 'delete', 'access']],
            ['repayment', ['access', 'create', 'show', 'edit', 'delete']],
            ['message', ['access', 'create', 'show', 'edit', 'delete']],
            ['loan', ['create', 'edit', 'show', 'delete', 'approve', 'access', 'reject', 'repayment',]],
            ['bank', ['access', 'create', 'show', 'edit', 'delete']],
        ];

        // Special/standalone permissions
        $standalonePermissions = [
            'user_management_access',
            'mark_orphan_married',
            'mark_orphan_unmarried',
            'admin_dashboard_access',
            'notification_access',
            'disburse_widow_loans',
            'collect_widow_loans',
        ];

        $permissions = [];

        // Generate CRUD permissions from patterns
        foreach ($permissionPatterns as [$entity, $actions]) {
            foreach ($actions as $action) {
                $permissions[] = [
                    'uuid'       => Str::uuid(),
                    'name'       => "{$entity}_{$action}",
                    'guard_name' => $guard,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        // Add standalone permissions
        foreach ($standalonePermissions as $name) {
            $permissions[] = [
                'uuid'       => Str::uuid(),
                'name'       => $name,
                'guard_name' => $guard,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        Permission::insert($permissions);
    }
}
