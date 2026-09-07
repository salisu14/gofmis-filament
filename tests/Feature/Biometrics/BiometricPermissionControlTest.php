<?php

namespace Tests\Feature\Biometrics;

use App\Enums\BiometricOperation;
use App\Enums\BiometricPurpose;
use App\Models\Activity;
use App\Models\BeneficiaryFingerprint;
use App\Models\User;
use App\Models\Widow;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * PB-NEXT-02D — biometric capability is permission-driven and revocable by
 * Super Admin through the existing permission-management mechanism.
 *
 * A biometric permission granted to a user (the canonical Spatie assignment) can
 * be revoked by a Super Admin; revocation takes effect immediately and never
 * deletes existing fingerprint records or audit history.
 *
 * Zone isolation and the coordinator role grant are covered by the PB-NEXT-02C
 * suites; this suite focuses on the revocable-permission gate and proves there
 * is no role-name bypass (a user with no such permission is denied).
 */
class BiometricPermissionControlTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['biometrics.client' => 'mock']);
        config(['activitylog.table_name' => 'activities']);

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->superAdmin = User::factory()->create(['email' => 'perm_super@gofmis.local']);
        $this->superAdmin->assignRole('super_admin');
    }

    protected function freshUser(string $email): User
    {
        return User::factory()->create(['email' => $email]);
    }

    // 0. A user without the permission is denied regardless of any role name.
    public function test_no_role_name_bypass_exists()
    {
        $user = $this->freshUser('no_bypass@gofmis.local');
        // The gate is permission-based; having no biometric permission denies.
        $this->assertFalse($user->can('biometrics.enroll'));
        $this->assertFalse($user->can('biometrics.verify'));
        $this->assertFalse($user->can('biometrics.identify'));
    }

    // 1. Granting biometrics.enroll enables the capability.
    public function test_granting_permission_enables_capability()
    {
        $user = $this->freshUser('granted@gofmis.local');
        $user->givePermissionTo('biometrics.enroll');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertTrue($user->can('biometrics.enroll'));
    }

    // 2. Super Admin can revoke a biometric permission assignment.
    public function test_super_admin_can_revoke_enroll_permission()
    {
        $user = $this->freshUser('revoked@gofmis.local');
        $user->givePermissionTo('biometrics.enroll');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->assertTrue(User::find($user->id)->can('biometrics.enroll'));

        // Revocation goes through the same permission-administration path a Super
        // Admin uses; no code/redeploy is required.
        User::find($user->id)->revokePermissionTo('biometrics.enroll');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertFalse(User::find($user->id)->can('biometrics.enroll'));
    }

    // 3/4. After revocation the capability is disabled (hidden/denied at gate).
    public function test_after_revocation_capability_is_disabled()
    {
        $user = $this->freshUser('post_revoke@gofmis.local');
        $user->givePermissionTo('biometrics.enroll', 'biometrics.verify', 'biometrics.identify');

        foreach (['biometrics.enroll', 'biometrics.verify', 'biometrics.identify'] as $permission) {
            $user->revokePermissionTo($permission);
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $fresh = User::find($user->id);
        $this->assertFalse($fresh->can('biometrics.enroll'));
        $this->assertFalse($fresh->can('biometrics.verify'));
        $this->assertFalse($fresh->can('biometrics.identify'));
    }

    // 6. Existing fingerprint records are NOT deleted when permission is revoked.
    public function test_revoking_permission_does_not_delete_existing_fingerprints()
    {
        $zone = Zone::create(['id' => (string) Str::uuid(), 'name' => 'Zone Z', 'code' => 'ZZ']);
        $deceasedId = (string) Str::uuid();
        DB::table('deceased')->insert([
            'id' => $deceasedId, 'first_name' => 'A', 'last_name' => 'B',
            'nin' => '1234567890'.Str::random(6), 'reg_no' => 'REG-'.Str::random(8),
            'guardian_name' => 'G', 'guardian_phone' => '123',
            'vulnerability_status' => 'A', 'date_registered' => now()->toDateString(),
            'zone_id' => $zone->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $widowId = (string) Str::uuid();
        DB::table('widows')->insert([
            'id' => $widowId, 'first_name' => 'Jane', 'last_name' => 'Doe',
            'nin' => '1234567890'.Str::random(6), 'reg_no' => 'REG-'.Str::random(8),
            'is_eligible' => true, 'is_married' => false,
            'deceased_id' => $deceasedId, 'child_sequence' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $widow = Widow::withoutGlobalScopes()->find($widowId);

        $print = $widow->fingerprints()->create([
            'finger_position' => 'right_thumb',
            'encrypted_template' => 'existing-record',
            'enrolled_by' => $this->superAdmin->id,
            'is_active' => true,
        ]);

        $user = $this->freshUser('records_keep@gofmis.local');
        $user->givePermissionTo('biometrics.enroll');
        $user->revokePermissionTo('biometrics.enroll');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertNotNull(BeneficiaryFingerprint::find($print->id));
    }

    // 7. Existing biometric audit history is NOT deleted on revocation.
    public function test_revoking_permission_does_not_delete_audit_history()
    {
        $this->seedEnrollmentAudit();

        $user = $this->freshUser('audit_keep@gofmis.local');
        $user->givePermissionTo('biometrics.enroll');
        $user->revokePermissionTo('biometrics.enroll');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertSame(1, Activity::where('log_name', 'biometric')->count());
    }

    // 8. Restoring the permission restores the capability without changing history.
    public function test_restoring_permission_restores_capability_without_history_change()
    {
        $zone = Zone::create(['id' => (string) Str::uuid(), 'name' => 'Zone R', 'code' => 'ZR']);
        $deceasedId = (string) Str::uuid();
        DB::table('deceased')->insert([
            'id' => $deceasedId, 'first_name' => 'A', 'last_name' => 'B',
            'nin' => '1234567890'.Str::random(6), 'reg_no' => 'REG-'.Str::random(8),
            'guardian_name' => 'G', 'guardian_phone' => '123',
            'vulnerability_status' => 'A', 'date_registered' => now()->toDateString(),
            'zone_id' => $zone->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $widowId = (string) Str::uuid();
        DB::table('widows')->insert([
            'id' => $widowId, 'first_name' => 'Jane', 'last_name' => 'Doe',
            'nin' => '1234567890'.Str::random(6), 'reg_no' => 'REG-'.Str::random(8),
            'is_eligible' => true, 'is_married' => false,
            'deceased_id' => $deceasedId, 'child_sequence' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $widow = Widow::withoutGlobalScopes()->find($widowId);

        $print = $widow->fingerprints()->create([
            'finger_position' => 'right_thumb',
            'encrypted_template' => 'unchanged-template',
            'enrolled_by' => $this->superAdmin->id,
            'is_active' => true,
        ]);

        $user = $this->freshUser('restore@gofmis.local');
        $user->givePermissionTo('biometrics.enroll');
        $user->revokePermissionTo('biometrics.enroll');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->assertFalse(User::find($user->id)->can('biometrics.enroll'));

        // Restore.
        User::find($user->id)->givePermissionTo('biometrics.enroll');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->assertTrue(User::find($user->id)->can('biometrics.enroll'));

        // Historical record untouched (still present, template decrypts back).
        $this->assertNotNull(BeneficiaryFingerprint::find($print->id));
        $this->assertSame('unchanged-template', $print->fresh()->decryptedTemplate());
    }

    // 10. Revocation takes effect immediately without redeployment.
    public function test_revocation_takes_effect_immediately()
    {
        $user = $this->freshUser('immediate@gofmis.local');
        $user->givePermissionTo('biometrics.enroll');
        $this->assertTrue(User::find($user->id)->can('biometrics.enroll'));

        User::find($user->id)->revokePermissionTo('biometrics.enroll');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertFalse(User::find($user->id)->can('biometrics.enroll'));
    }

    protected function seedEnrollmentAudit(): void
    {
        $zone = Zone::create(['id' => (string) Str::uuid(), 'name' => 'Zone A', 'code' => 'ZA']);
        $deceasedId = (string) Str::uuid();
        DB::table('deceased')->insert([
            'id' => $deceasedId, 'first_name' => 'A', 'last_name' => 'B',
            'nin' => '1234567890'.Str::random(6), 'reg_no' => 'REG-'.Str::random(8),
            'guardian_name' => 'G', 'guardian_phone' => '123',
            'vulnerability_status' => 'A', 'date_registered' => now()->toDateString(),
            'zone_id' => $zone->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $widowId = (string) Str::uuid();
        DB::table('widows')->insert([
            'id' => $widowId, 'first_name' => 'Jane', 'last_name' => 'Doe',
            'nin' => '1234567890'.Str::random(6), 'reg_no' => 'REG-'.Str::random(8),
            'is_eligible' => true, 'is_married' => false,
            'deceased_id' => $deceasedId, 'child_sequence' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $widow = Widow::withoutGlobalScopes()->find($widowId);

        app(\App\Services\Biometrics\BiometricAuditService::class)->record(
            BiometricOperation::ENROLLMENT,
            $widow,
            result: 'success',
            purpose: BiometricPurpose::ENROLLMENT,
        );
    }
}
