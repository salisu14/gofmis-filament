<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SecurityRbacAudit extends Command
{
    protected $signature = 'security:rbac-audit {--details : Show detailed lists of findings}';

    protected $description = 'Perform a read-only security and RBAC audit of the system';

    public function handle()
    {
        $this->info('============================================================');
        $this->info('             GOF MIS RBAC & SECURITY AUDIT REPORT           ');
        $this->info('============================================================');

        $hasCritical = false;
        $hasWarnings = false;

        // 1. Number of active Super Admin users
        $activeSuperAdmins = User::role('super_admin')
            ->where('is_active', true)
            ->where('status', \App\Enums\UserStatus::ACTIVE)
            ->get();

        $superAdminCount = $activeSuperAdmins->count();
        $this->line("Active Super Admin Users: <comment>{$superAdminCount}</comment>");

        // 2. Zero-Super-Admin critical finding
        if ($superAdminCount === 0) {
            $this->error('[CRITICAL] CRITICAL FINDING: There are ZERO active Super Admin users in the system!');
            $hasCritical = true;
        } else {
            $this->info('[OK] At least one active Super Admin user exists.');
        }

        // 3. Inconsistent super_admin / super-admin role names
        $inconsistentRoles = Role::whereIn('name', ['super-admin', 'superadmin', 'administrator'])->pluck('name')->toArray();
        if (! empty($inconsistentRoles)) {
            $this->warn('[WARNING] Inconsistent or suspicious role names found: '.implode(', ', $inconsistentRoles));
            $hasWarnings = true;
        } else {
            $this->info('[OK] Role names look consistent (no super-admin/superadmin stubs).');
        }

        // 4. Users assigned invalid/nonexistent roles
        $invalidRoleAssignments = DB::table('model_has_roles')
            ->leftJoin('roles', 'model_has_roles.role_id', '=', 'roles.uuid')
            ->whereNull('roles.uuid')
            ->select('model_has_roles.model_uuid', 'model_has_roles.role_id')
            ->get();

        if ($invalidRoleAssignments->isNotEmpty()) {
            $this->error("[CRITICAL] Found {$invalidRoleAssignments->count()} invalid role assignments (roles that do not exist)!");
            $hasCritical = true;
            if ($this->option('details')) {
                foreach ($invalidRoleAssignments as $assignment) {
                    $this->line("  - User UUID: {$assignment->model_uuid} has nonexistent Role ID: {$assignment->role_id}");
                }
            }
        } else {
            $this->info('[OK] No orphan/invalid role assignments detected.');
        }

        // 5. Protected roles status
        $protectedRoles = ['super_admin', 'admin', 'coordinator'];
        foreach ($protectedRoles as $roleName) {
            $roleExists = Role::where('name', $roleName)->exists();
            if (! $roleExists) {
                $this->warn("[WARNING] Protected system role '{$roleName}' is missing from the database.");
                $hasWarnings = true;
            } else {
                $this->info("[OK] Protected role '{$roleName}' is present.");
            }
        }

        // 6. Disabled elevated users (elevated users who are disabled/suspended)
        $disabledElevatedUsers = User::role(['super_admin', 'admin'])
            ->where(function ($query) {
                $query->where('is_active', false)
                    ->orWhere('status', '!=', \App\Enums\UserStatus::ACTIVE);
            })
            ->get();

        if ($disabledElevatedUsers->isNotEmpty()) {
            $this->line("Disabled/Suspended Elevated Users (Super Admin/Admin): <comment>{$disabledElevatedUsers->count()}</comment>");
            if ($this->option('details')) {
                foreach ($disabledElevatedUsers as $u) {
                    $this->line("  - {$u->name} ({$u->email}) - Status: {$u->status->value}, Active: ".($u->is_active ? 'Yes' : 'No'));
                }
            }
        } else {
            $this->info('[OK] All elevated users are active.');
        }

        // 7. Coordinator accounts without coordinated zone
        $coordinators = User::role('coordinator')->get();
        $coordinatorsWithoutZone = [];
        foreach ($coordinators as $coordinator) {
            $hasZone = Zone::where('coordinator_id', $coordinator->id)->exists();
            if (! $hasZone) {
                $coordinatorsWithoutZone[] = $coordinator;
            }
        }

        if (! empty($coordinatorsWithoutZone)) {
            $this->warn('[WARNING] Found '.count($coordinatorsWithoutZone).' coordinator accounts without an assigned zone.');
            $hasWarnings = true;
            if ($this->option('details')) {
                foreach ($coordinatorsWithoutZone as $c) {
                    $this->line("  - {$c->name} ({$c->email})");
                }
            }
        } else {
            $this->info('[OK] All coordinator accounts are assigned to a zone.');
        }

        // 8. Duplicate coordinator-zone assignment violations
        $duplicateCoordinators = Zone::whereNotNull('coordinator_id')
            ->select('coordinator_id', DB::raw('count(*) as zone_count'))
            ->groupBy('coordinator_id')
            ->having('zone_count', '>', 1)
            ->get();

        if ($duplicateCoordinators->isNotEmpty()) {
            $this->warn('[WARNING] Found duplicate zone coordinator assignments (one user coordinating multiple zones):');
            $hasWarnings = true;
            foreach ($duplicateCoordinators as $dup) {
                $user = User::find($dup->coordinator_id);
                $userName = $user ? $user->name : $dup->coordinator_id;
                $this->line("  - Coordinator: {$userName} is coordinating {$dup->zone_count} zones.");
            }
        } else {
            $this->info('[OK] No coordinator is assigned to multiple zones.');
        }

        // 9. Suspicious direct permissions on elevated users
        $usersWithDirectPermissions = DB::table('model_has_permissions')
            ->select('model_uuid', DB::raw('count(*) as permission_count'))
            ->groupBy('model_uuid')
            ->get();

        if ($usersWithDirectPermissions->isNotEmpty()) {
            $this->warn('[WARNING] Found '.$usersWithDirectPermissions->count().' users with direct permissions assigned:');
            $hasWarnings = true;
            if ($this->option('details')) {
                foreach ($usersWithDirectPermissions as $perm) {
                    $user = User::find($perm->model_uuid);
                    $userName = $user ? "{$user->name} ({$user->email})" : $perm->model_uuid;
                    $this->line("  - User: {$userName} has {$perm->permission_count} direct permissions.");
                }
            }
        } else {
            $this->info('[OK] No direct permissions are assigned directly to users.');
        }

        $this->info('============================================================');
        if ($hasCritical) {
            $this->error('AUDIT STATUS: CRITICAL FINDINGS ENCOUNTERED');
        } elseif ($hasWarnings) {
            $this->warn('AUDIT STATUS: WARNINGS ENCOUNTERED');
        } else {
            $this->info('AUDIT STATUS: ALL CHECKS PASSED');
        }
        $this->info('============================================================');

        return 0;
    }
}
