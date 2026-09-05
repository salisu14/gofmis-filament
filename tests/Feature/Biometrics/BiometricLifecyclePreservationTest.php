<?php

namespace Tests\Feature\Biometrics;

use App\Models\BeneficiaryFingerprint;
use App\Models\Orphan;
use App\Models\User;
use App\Models\Widow;
use App\Models\Zone;
use App\Services\Biometrics\BiometricIdentificationService;
use App\Services\Biometrics\BiometricVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * PB-NEXT-02F — Biometric Lifecycle Preservation Closure
 */
class BiometricLifecyclePreservationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Zone $zone;

    protected Widow $widow;

    protected Orphan $orphan;

    protected function setUp(): void
    {
        parent::setUp();
        config(['biometrics.client' => 'mock']);
        config(['activitylog.table_name' => 'activities']);
        config(['biometrics.governance.lawful_basis_reference' => 'REF-LEGAL-001']);

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create(['email' => 'lifecycle_admin@gofmis.local']);
        $this->admin->assignRole('admin');

        $this->zone = Zone::create(['id' => (string) Str::uuid(), 'name' => 'Zone Lifecycle', 'code' => 'ZL']);

        $deceasedId = (string) Str::uuid();
        DB::table('deceased')->insert([
            'id' => $deceasedId, 'first_name' => 'John', 'last_name' => 'Doe',
            'nin' => '12345678901', 'reg_no' => 'REG-123',
            'guardian_name' => 'G', 'guardian_phone' => '123',
            'vulnerability_status' => 'A', 'date_registered' => now()->toDateString(),
            'zone_id' => $this->zone->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $widowId = (string) Str::uuid();
        DB::table('widows')->insert([
            'id' => $widowId, 'first_name' => 'Jane', 'last_name' => 'Doe',
            'nin' => '12345678902', 'reg_no' => 'REG-W123',
            'is_eligible' => true, 'is_married' => false,
            'deceased_id' => $deceasedId, 'child_sequence' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->widow = Widow::withoutGlobalScopes()->find($widowId);

        $orphanId = (string) Str::uuid();
        DB::table('orphans')->insert([
            'id' => $orphanId, 'first_name' => 'Jimmy', 'last_name' => 'Doe',
            'gender' => 'MALE', 'reg_no' => 'REG-O123',
            'is_eligible' => true, 'deceased_id' => $deceasedId, 'child_sequence' => 2,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->orphan = Orphan::withoutGlobalScopes()->find($orphanId);
    }

    protected function activePrint(Widow|Orphan $beneficiary, string $position = 'right_thumb'): BeneficiaryFingerprint
    {
        return $beneficiary->fingerprints()->create([
            'finger_position' => $position,
            'encrypted_template' => 'mock-template-'.Str::random(8),
            'enrolled_by' => $this->admin->id,
            'is_active' => true,
        ]);
    }

    // =========================================================================
    // 1. Existing Tests (Expanded) + 2. Widow History Preservation
    // =========================================================================

    public function test_widow_remarriage_preserves_fingerprint_active_status()
    {
        $print = $this->activePrint($this->widow);

        $this->assertTrue($this->widow->is_eligible);
        $this->assertTrue($print->is_active);

        $printId = $print->id;
        $encrypted = $print->encrypted_template;
        $enrolledAt = $print->created_at;
        $enrolledBy = $print->enrolled_by;

        $this->widow->markAsMarried('Remarried', now());

        $this->assertFalse($this->widow->is_eligible);
        $this->assertTrue($this->widow->is_married);

        $print->refresh();
        $this->assertSame($printId, $print->id);
        $this->assertSame($encrypted, $print->encrypted_template);
        $this->assertEquals($enrolledAt, $print->created_at);
        $this->assertSame($enrolledBy, $print->enrolled_by);
        $this->assertTrue($print->is_active);
        $this->assertNull($print->revoked_at);
        $this->assertNull($print->revocation_reason);
    }

    public function test_widow_divorce_reactivation_preserves_fingerprint()
    {
        $print = $this->activePrint($this->widow);
        $printId = $print->id;
        $encrypted = $print->encrypted_template;
        $enrolledAt = $print->created_at;
        $enrolledBy = $print->enrolled_by;

        $this->widow->markAsMarried('Remarried', now());

        $print->refresh();
        $this->assertTrue($print->is_active);

        $this->widow->reactivateAfterDivorce('Divorced', now());

        $this->assertFalse($this->widow->is_married);

        $print->refresh();
        $this->assertSame($printId, $print->id);
        $this->assertSame($encrypted, $print->encrypted_template);
        $this->assertEquals($enrolledAt, $print->created_at);
        $this->assertSame($enrolledBy, $print->enrolled_by);
        $this->assertTrue($print->is_active);
        $this->assertNull($print->revoked_at);
        $this->assertSame(1, $this->widow->fingerprints()->count());
    }

    // =========================================================================
    // 3. Explicitly Revoked Widow
    // =========================================================================

    public function test_explicitly_revoked_widow_stays_revoked_after_lifecycle_changes()
    {
        $print = $this->activePrint($this->widow);

        // Revoke
        $print->update([
            'is_active' => false,
            'revoked_at' => now(),
            'revocation_reason' => 'Lost finger',
            'revoked_by' => $this->admin->id,
        ]);

        // Remarry
        $this->widow->markAsMarried('Remarried', now());

        // Divorce
        $this->widow->reactivateAfterDivorce('Divorced', now());

        $print->refresh();
        $this->assertFalse($print->is_active);
        $this->assertNotNull($print->revoked_at);
        $this->assertSame('Lost finger', $print->revocation_reason);

        // Cannot verify
        $verifySvc = app(BiometricVerificationService::class);
        $outcome = $verifySvc->verify($this->widow, $print, $this->admin);
        $this->assertSame('error', $outcome['status']);
        $this->assertSame('revoked_or_inactive', $outcome['category']);

        // Cannot identify
        $identifySvc = app(BiometricIdentificationService::class);
        config(['biometrics.mock.identify_match_id' => $print->id]);
        $outcomeId = $identifySvc->identify($this->admin);
        $this->assertSame('no_match', $outcomeId['status']);
    }

    // =========================================================================
    // 4. Orphan History Preservation
    // =========================================================================

    public function test_orphan_archiving_preserves_fingerprint_active_status()
    {
        $print = $this->activePrint($this->orphan);
        $printId = $print->id;
        $encrypted = $print->encrypted_template;
        $enrolledAt = $print->created_at;
        $enrolledBy = $print->enrolled_by;

        $this->orphan->archiveForIneligibility('Aged out');

        $this->assertFalse($this->orphan->is_eligible);

        $print->refresh();
        $this->assertSame($printId, $print->id);
        $this->assertSame($encrypted, $print->encrypted_template);
        $this->assertEquals($enrolledAt, $print->created_at);
        $this->assertSame($enrolledBy, $print->enrolled_by);
        $this->assertTrue($print->is_active);
        $this->assertNull($print->revoked_at);
    }

    public function test_orphan_reactivation_preserves_fingerprint()
    {
        $print = $this->activePrint($this->orphan);
        $this->orphan->archiveForIneligibility('Aged out');

        // Reactivate
        $this->orphan->update(['is_eligible' => true, 'archived_at' => null, 'archive_reason' => null]);

        $print->refresh();
        $this->assertTrue($print->is_active);
        $this->assertSame(1, $this->orphan->fingerprints()->count());
    }

    // =========================================================================
    // 5. Explicitly Revoked Orphan
    // =========================================================================

    public function test_explicitly_revoked_orphan_stays_revoked()
    {
        $print = $this->activePrint($this->orphan);

        $print->update([
            'is_active' => false,
            'revoked_at' => now(),
            'revocation_reason' => 'Error',
            'revoked_by' => $this->admin->id,
        ]);

        $this->orphan->archiveForIneligibility('Aged out');
        $this->orphan->update(['is_eligible' => true, 'archived_at' => null, 'archive_reason' => null]);

        $print->refresh();
        $this->assertFalse($print->is_active);
        $this->assertNotNull($print->revoked_at);

        $verifySvc = app(BiometricVerificationService::class);
        $outcome = $verifySvc->verify($this->orphan, $print, $this->admin);
        $this->assertSame('error', $outcome['status']);

        $identifySvc = app(BiometricIdentificationService::class);
        config(['biometrics.mock.identify_match_id' => $print->id]);
        $outcomeId = $identifySvc->identify($this->admin);
        $this->assertSame('no_match', $outcomeId['status']);
    }

    // =========================================================================
    // 6 & 8. MATCH != ELIGIBILITY (Identify)
    // =========================================================================

    public function test_remarried_archived_widow_can_still_be_identified_without_changing_lifecycle()
    {
        $print = $this->activePrint($this->widow);
        $this->widow->markAsMarried('Remarried', now());

        $this->assertFalse($this->widow->is_eligible);
        $this->assertTrue($this->widow->is_married);

        $svc = app(BiometricIdentificationService::class);
        config(['biometrics.mock.identify_match_id' => $print->id]);
        $outcome = $svc->identify($this->admin);

        $this->assertSame('match', $outcome['status']);
        $this->assertSame($print->id, $outcome['fingerprint']->id);
        $this->assertSame($this->widow->id, $outcome['beneficiary']->id);

        // Prove it did not change lifecycle
        $this->widow->refresh();
        $this->assertFalse($this->widow->is_eligible);
        $this->assertTrue($this->widow->is_married);
    }

    public function test_archived_orphan_can_still_be_identified_without_changing_lifecycle()
    {
        $print = $this->activePrint($this->orphan);
        $this->orphan->archiveForIneligibility('Aged out');

        $this->assertFalse($this->orphan->is_eligible);

        $svc = app(BiometricIdentificationService::class);
        config(['biometrics.mock.identify_match_id' => $print->id]);
        $outcome = $svc->identify($this->admin);

        $this->assertSame('match', $outcome['status']);
        $this->assertSame($print->id, $outcome['fingerprint']->id);
        $this->assertSame($this->orphan->id, $outcome['beneficiary']->id);

        // Prove it did not change lifecycle
        $this->orphan->refresh();
        $this->assertFalse($this->orphan->is_eligible);
    }

    // =========================================================================
    // 7. VERIFY IDENTITY LIFECYCLE COVERAGE
    // =========================================================================

    public function test_verify_identity_does_not_change_lifecycle_on_match_or_no_match()
    {
        $print = $this->activePrint($this->widow);
        $this->widow->markAsMarried('Remarried', now());

        $svc = app(BiometricVerificationService::class);

        // MATCH
        config(['biometrics.mock.verify_outcome' => 'match']);
        $outcomeMatch = $svc->verify($this->widow, $print, $this->admin);
        $this->assertSame('match', $outcomeMatch['status']);

        $this->widow->refresh();
        $this->assertTrue($this->widow->is_married); // Unchanged

        $print->refresh();
        $this->assertNotNull($print->last_verified_at);
        $lastVerified = $print->last_verified_at;

        // NO MATCH
        config(['biometrics.mock.verify_outcome' => 'no_match']);
        $outcomeNoMatch = $svc->verify($this->widow, $print, $this->admin);
        $this->assertSame('no_match', $outcomeNoMatch['status']);

        $this->widow->refresh();
        $this->assertTrue($this->widow->is_married); // Unchanged

        $print->refresh();
        $this->assertEquals($lastVerified, $print->last_verified_at); // Not updated

        // ERROR
        config(['biometrics.mock.verify_outcome' => 'scanner_unavailable']);
        $outcomeError = $svc->verify($this->widow, $print, $this->admin);
        $this->assertSame('error', $outcomeError['status']);

        $this->widow->refresh();
        $this->assertTrue($this->widow->is_married); // Unchanged
    }

    // =========================================================================
    // 9. NEW ENROLLMENT LIFECYCLE REVIEW
    // =========================================================================

    public function test_can_enroll_fingerprint_for_remarried_widow()
    {
        $this->actingAs($this->admin);

        $this->widow->markAsMarried('Remarried', now());
        $this->assertFalse($this->widow->is_eligible);

        // Even if ineligible, identity enrollment is allowed.
        // The business rule allows prints to exist independent of eligibility.
        \Livewire\Livewire::test(\App\Filament\RelationManagers\FingerprintsRelationManager::class, [
            'ownerRecord' => $this->widow,
            'pageClass' => \App\Filament\Resources\Widows\Pages\ViewWidow::class,
        ])
            ->callTableAction('enroll', data: [
                'finger_position' => 'left_thumb',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertEquals(1, $this->widow->fingerprints()->where('is_active', true)->count());
        $this->assertEquals('left_thumb', $this->widow->fingerprints()->first()->finger_position);
    }

    // =========================================================================
    // 10. AUDIT-HISTORY PRESERVATION
    // =========================================================================

    public function test_lifecycle_transitions_do_not_delete_biometric_audit_records()
    {
        $print = $this->activePrint($this->widow);

        // Create dummy audit record
        activity('biometric')
            ->performedOn($this->widow)
            ->causedBy($this->admin)
            ->withProperties(['operation' => 'enrollment'])
            ->log('fingerprint enrolled');

        $auditCountBefore = DB::table('activities')->where('log_name', 'biometric')->count();
        $this->assertGreaterThan(0, $auditCountBefore);

        $this->widow->markAsMarried('Remarried', now());
        $this->widow->reactivateAfterDivorce('Divorced', now());

        $auditCountAfter = DB::table('activities')->where('log_name', 'biometric')->count();
        $this->assertEquals($auditCountBefore, $auditCountAfter);
    }
}
