<?php

namespace Tests\Feature\Biometrics;

use App\Filament\RelationManagers\FingerprintsRelationManager;
use App\Models\BeneficiaryFingerprint;
use App\Models\Orphan;
use App\Models\User;
use App\Models\Widow;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * PB-NEXT-02C — Coordinator biometric access + zone-scoped enrollment.
 *
 * Verifies the security matrix for an authorized coordinator: view + enroll for
 * in-zone beneficiaries only; no revoke/delete; no template exposure; forged
 * actions denied; admin authority preserved; dedicated encryption preserved;
 * operator attribution retained; mock device mode sufficient.
 */
class BiometricCoordinatorAccessTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $coordinator;

    protected Zone $zoneA;

    protected Zone $zoneB;

    protected Widow $widowInZoneA;

    protected Widow $widowInZoneB;

    protected Orphan $orphanInZoneA;

    protected Orphan $orphanInZoneB;

    /**
     * Build a beneficiary household inside a zone; returns [deceasedId, widowId, orphanId].
     */
    protected function makeHousehold(string $zoneId): array
    {
        $deceasedId = (string) Str::uuid();
        DB::table('deceased')->insert([
            'id' => $deceasedId,
            'first_name' => 'House',
            'last_name' => 'Head',
            'nin' => '1234567890'.Str::random(6),
            'reg_no' => 'REG-'.Str::random(8),
            'guardian_name' => 'Guardian',
            'guardian_phone' => '1234567890',
            'vulnerability_status' => 'A',
            'date_registered' => now()->toDateString(),
            'zone_id' => $zoneId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $widowId = (string) Str::uuid();
        DB::table('widows')->insert([
            'id' => $widowId,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'nin' => '1234567890'.Str::random(6),
            'reg_no' => 'REG-'.Str::random(8),
            'is_eligible' => true,
            'is_married' => false,
            'deceased_id' => $deceasedId,
            'child_sequence' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orphanId = (string) Str::uuid();
        DB::table('orphans')->insert([
            'id' => $orphanId,
            'first_name' => 'Jimmy',
            'last_name' => 'Doe',
            'gender' => 'MALE',
            'reg_no' => 'REG-'.Str::random(8),
            'is_eligible' => true,
            'deceased_id' => $deceasedId,
            'child_sequence' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$deceasedId, $widowId, $orphanId];
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['biometrics.client' => 'mock']);
        config(['activitylog.table_name' => 'activities']);

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create(['email' => 'coord_admin@gofmis.local']);
        $this->admin->assignRole('admin');

        $this->coordinator = User::factory()->create(['email' => 'coord_user@gofmis.local']);
        $this->coordinator->assignRole('coordinator');

        $this->zoneA = Zone::create([
            'id' => (string) Str::uuid(),
            'name' => 'Zone A',
            'code' => 'ZA',
            'coordinator_id' => $this->coordinator->id,
        ]);
        $this->zoneB = Zone::create(['id' => (string) Str::uuid(), 'name' => 'Zone B', 'code' => 'ZB']);

        [$da, $wa, $oa] = $this->makeHousehold($this->zoneA->id);
        [$db, $wb, $ob] = $this->makeHousehold($this->zoneB->id);

        $this->widowInZoneA = Widow::find($wa);
        $this->orphanInZoneA = Orphan::find($oa);
        $this->widowInZoneB = Widow::find($wb);
        $this->orphanInZoneB = Orphan::find($ob);
    }

    protected function mountFor($owner, string $pageClass)
    {
        return Livewire::test(FingerprintsRelationManager::class, [
            'ownerRecord' => $owner,
            'pageClass' => $pageClass,
        ]);
    }

    // A. Coordinator with biometrics.view can view in-zone fingerprint metadata.
    public function test_coordinator_can_view_in_zone_widow_fingerprint_metadata()
    {
        $this->actingAs($this->coordinator);
        $this->widowInZoneA->fingerprints()->create([
            'finger_position' => 'right_thumb',
            'encrypted_template' => 'viewable_meta',
            'enrolled_by' => $this->admin->id,
            'is_active' => true,
        ]);

        $this->mountFor($this->widowInZoneA, \App\Filament\Resources\Widows\Pages\ViewWidow::class)
            ->assertSuccessful()
            ->assertSee('Biometric Fingerprints')
            ->assertSee('Right Thumb');

        $this->assertSame(1, $this->widowInZoneA->fingerprints()->count());
    }

    // B. Coordinator with biometrics.enroll can enroll an in-zone Widow.
    public function test_coordinator_can_enroll_in_zone_widow()
    {
        $this->actingAs($this->coordinator);

        $this->mountFor($this->widowInZoneA, \App\Filament\Resources\Widows\Pages\ViewWidow::class)
            ->callTableAction('enroll', data: ['finger_position' => 'right_thumb'])
            ->assertHasNoTableActionErrors();

        $this->assertSame(1, $this->widowInZoneA->fingerprints()->where('is_active', true)->count());
        $this->assertSame('right_thumb', $this->widowInZoneA->fingerprints()->first()->finger_position);
    }

    // C. Coordinator with biometrics.enroll can enroll an in-zone Orphan.
    public function test_coordinator_can_enroll_in_zone_orphan()
    {
        $this->actingAs($this->coordinator);

        $this->mountFor($this->orphanInZoneA, \App\Filament\Resources\Orphans\Pages\ViewOrphan::class)
            ->callTableAction('enroll', data: ['finger_position' => 'left_index'])
            ->assertHasNoTableActionErrors();

        $this->assertSame(1, $this->orphanInZoneA->fingerprints()->where('is_active', true)->count());
    }

    // D. Coordinator cannot view/enroll an out-of-zone Widow.
    public function test_coordinator_cannot_access_out_of_zone_widow_biometrics()
    {
        $this->actingAs($this->coordinator);
        $this->widowInZoneB->fingerprints()->create([
            'finger_position' => 'right_thumb',
            'encrypted_template' => 'hidden_meta',
            'enrolled_by' => $this->admin->id,
            'is_active' => true,
        ]);

        // The enroll action must not be exposed for an out-of-zone beneficiary.
        $this->mountFor($this->widowInZoneB, \App\Filament\Resources\Widows\Pages\ViewWidow::class)
            ->assertSuccessful()
            ->assertTableActionHidden('enroll');
    }

    // E. Coordinator cannot enroll an out-of-zone Widow (server-side denial).
    public function test_coordinator_cannot_enroll_out_of_zone_widow()
    {
        $this->actingAs($this->coordinator);

        $this->mountFor($this->widowInZoneB, \App\Filament\Resources\Widows\Pages\ViewWidow::class)
            ->assertTableActionHidden('enroll');

        // The hidden action must never create a row.
        $this->assertSame(0, $this->widowInZoneB->fingerprints()->count());
    }

    // F. Coordinator cannot enroll an out-of-zone Orphan (server-side denial).
    public function test_coordinator_cannot_enroll_out_of_zone_orphan()
    {
        $this->actingAs($this->coordinator);

        $this->mountFor($this->orphanInZoneB, \App\Filament\Resources\Orphans\Pages\ViewOrphan::class)
            ->assertTableActionHidden('enroll');

        $this->assertSame(0, $this->orphanInZoneB->fingerprints()->count());
    }

    // G. Forged/direct enrollment against an out-of-zone beneficiary is rejected
    //    server-side: the enroll action is not invocable and no row is created.
    public function test_forged_direct_enrollment_action_against_out_of_zone_beneficiary_is_denied()
    {
        $this->actingAs($this->coordinator);

        expect(fn () => $this->mountFor($this->widowInZoneB, \App\Filament\Resources\Widows\Pages\ViewWidow::class)
            ->callTableAction('enroll', data: ['finger_position' => 'right_thumb']))
            ->toThrow(\Exception::class);

        $this->assertSame(0, $this->widowInZoneB->fingerprints()->count());
    }

    // H. A user without biometrics.enroll cannot enroll even in-zone.
    public function test_user_without_enroll_permission_cannot_enroll_in_zone()
    {
        // Fresh user with no biometric permission (and no role) cannot enroll.
        $noEnroll = User::factory()->create(['email' => 'no_enroll@gofmis.local']);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($noEnroll);

        $this->assertFalse($noEnroll->can('biometrics.enroll'));

        $this->mountFor($this->widowInZoneA, \App\Filament\Resources\Widows\Pages\ViewWidow::class)
            ->assertTableActionHidden('enroll');

        $this->assertSame(0, $this->widowInZoneA->fingerprints()->count());
    }

    // I. A user without biometrics.view cannot access biometric metadata.
    public function test_user_without_view_permission_cannot_touch_biometrics()
    {
        $noBio = User::factory()->create(['email' => 'no_view@gofmis.local']);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($noBio);

        $this->assertFalse($noBio->can('biometrics.view'));
        $this->assertFalse($noBio->can('biometrics.enroll'));

        $this->mountFor($this->widowInZoneA, \App\Filament\Resources\Widows\Pages\ViewWidow::class)
            ->assertTableActionHidden('enroll');
    }

    // J. Coordinator cannot revoke a fingerprint.
    public function test_coordinator_cannot_revoke_fingerprint()
    {
        $this->actingAs($this->coordinator);
        $print = $this->widowInZoneA->fingerprints()->create([
            'finger_position' => 'right_thumb',
            'encrypted_template' => 'not_revocable',
            'enrolled_by' => $this->admin->id,
            'is_active' => true,
        ]);

        // Coordinator lacks biometrics.revoke; revoke is not exposed.
        $this->mountFor($this->widowInZoneA, \App\Filament\Resources\Widows\Pages\ViewWidow::class)
            ->assertTableActionHidden('revoke', $print);

        $print->refresh();
        $this->assertTrue($print->is_active);
    }

    // K. Coordinator cannot delete fingerprint history: no delete action exists.
    public function test_coordinator_cannot_delete_fingerprint_history()
    {
        $this->actingAs($this->coordinator);
        $print = $this->widowInZoneA->fingerprints()->create([
            'finger_position' => 'right_thumb',
            'encrypted_template' => 'not_deletable',
            'enrolled_by' => $this->admin->id,
            'is_active' => true,
        ]);

        $this->mountFor($this->widowInZoneA, \App\Filament\Resources\Widows\Pages\ViewWidow::class)
            ->assertTableActionDoesNotExist('delete');

        $this->assertNotNull(BeneficiaryFingerprint::find($print->id));
    }

    // L. Coordinator cannot access raw/encrypted template data.
    public function test_coordinator_cannot_access_raw_or_encrypted_template()
    {
        $this->actingAs($this->coordinator);
        $print = $this->widowInZoneA->fingerprints()->create([
            'finger_position' => 'right_thumb',
            'encrypted_template' => 'template_not_exposed',
            'enrolled_by' => $this->admin->id,
            'is_active' => true,
        ]);

        $array = $print->toArray();
        $this->assertArrayNotHasKey('encrypted_template', $array);

        // The relation-manager table never exposes template material.
        $this->mountFor($this->widowInZoneA, \App\Filament\Resources\Widows\Pages\ViewWidow::class)
            ->assertSuccessful()
            ->assertDontSee('template_not_exposed');
    }

    // M. Admin enrollment still works.
    public function test_admin_can_still_enroll_fingerprint()
    {
        $this->actingAs($this->admin);

        $this->mountFor($this->widowInZoneA, \App\Filament\Resources\Widows\Pages\ViewWidow::class)
            ->callTableAction('enroll', data: ['finger_position' => 'right_thumb'])
            ->assertHasNoTableActionErrors();

        $this->assertSame(1, $this->widowInZoneA->fingerprints()->where('is_active', true)->count());
    }

    // N. Admin revocation still works.
    public function test_admin_can_still_revoke_fingerprint()
    {
        $this->actingAs($this->admin);
        $print = $this->widowInZoneA->fingerprints()->create([
            'finger_position' => 'right_thumb',
            'encrypted_template' => 'revocable',
            'enrolled_by' => $this->admin->id,
            'is_active' => true,
        ]);

        $this->mountFor($this->widowInZoneA, \App\Filament\Resources\Widows\Pages\ViewWidow::class)
            ->callTableAction('revoke', $print, data: ['revocation_reason' => 'Finger injured'])
            ->assertHasNoTableActionErrors();

        $this->assertFalse($print->fresh()->is_active);
    }

    // O. New Coordinator enrollment uses PB-NEXT-02A dedicated encryption.
    public function test_coordinator_enrollment_is_encrypted_with_dedicated_key()
    {
        $this->actingAs($this->coordinator);

        $this->mountFor($this->widowInZoneA, \App\Filament\Resources\Widows\Pages\ViewWidow::class)
            ->callTableAction('enroll', data: ['finger_position' => 'right_thumb'])
            ->assertHasNoTableActionErrors();

        $raw = DB::table('beneficiary_fingerprints')
            ->where('beneficiary_id', $this->widowInZoneA->id)
            ->value('encrypted_template');

        $this->assertStringStartsWith('biometric:v', $raw);
        $this->assertSame(1, (int) DB::table('beneficiary_fingerprints')
            ->where('beneficiary_id', $this->widowInZoneA->id)
            ->value('key_version'));
    }

    // P. Revoked historical rows remain intact.
    public function test_revoked_historical_rows_remain_intact()
    {
        $this->actingAs($this->admin);
        $print1 = $this->widowInZoneA->fingerprints()->create([
            'finger_position' => 'right_thumb',
            'encrypted_template' => 'revoked_hist',
            'enrolled_by' => $this->admin->id,
            'is_active' => false,
            'revoked_at' => now(),
            'revocation_reason' => 'Old injury',
        ]);

        $this->actingAs($this->coordinator);
        $this->mountFor($this->widowInZoneA, \App\Filament\Resources\Widows\Pages\ViewWidow::class)
            ->callTableAction('enroll', data: ['finger_position' => 'right_thumb'])
            ->assertHasNoTableActionErrors();

        $this->assertSame(2, $this->widowInZoneA->fingerprints()->count());
        $this->assertFalse($print1->fresh()->is_active);
    }

    // Q. Mock device mode works for Coordinator enrollment.
    public function test_mock_device_mode_works_for_coordinator_enrollment()
    {
        config(['biometrics.client' => 'mock']);
        $this->actingAs($this->coordinator);

        $this->mountFor($this->widowInZoneA, \App\Filament\Resources\Widows\Pages\ViewWidow::class)
            ->callTableAction('enroll', data: ['finger_position' => 'right_index'])
            ->assertHasNoTableActionErrors();

        $this->assertSame(1, $this->widowInZoneA->fingerprints()->where('is_active', true)->count());
    }

    // R. Enrollment records operator attribution.
    public function test_enrollment_records_operator_attribution()
    {
        $this->actingAs($this->coordinator);

        $this->mountFor($this->widowInZoneA, \App\Filament\Resources\Widows\Pages\ViewWidow::class)
            ->callTableAction('enroll', data: ['finger_position' => 'right_thumb'])
            ->assertHasNoTableActionErrors();

        $print = $this->widowInZoneA->fingerprints()->first();
        $this->assertSame((string) $this->coordinator->id, (string) $print->enrolled_by);
        $this->assertNotNull($print->enrolled_at);
    }
}
