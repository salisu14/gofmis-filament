<?php

namespace Tests\Feature\Biometrics;

use App\Models\Activity;
use App\Models\BeneficiaryFingerprint;
use App\Models\Orphan;
use App\Models\User;
use App\Models\Widow;
use App\Models\Zone;
use App\Services\Biometrics\BiometricIdentificationService;
use App\Services\Biometrics\BiometricVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * PB-NEXT-02D — Application-level 1:1 verification and 1:N identification.
 *
 * Verifies contractor-level semantics: distinct MATCH / NO_MATCH / ERROR,
 * active-only (revoked excluded) candidates, server-side zone scope, no
 * template / PII leakage, bounded candidate pools, and structured audit.
 */
class BiometricVerificationIdentificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $coordinator;

    protected Zone $zoneA;

    protected Zone $zoneB;

    protected Widow $widowInZoneA;

    protected Widow $widowInZoneB;

    protected Orphan $orphanInZoneA;

    protected function setUp(): void
    {
        parent::setUp();
        config(['biometrics.client' => 'mock']);
        config(['activitylog.table_name' => 'activities']);
        config(['biometrics.governance.lawful_basis_reference' => 'REF-LEGAL-001']);

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create(['email' => 'vi_admin@gofmis.local']);
        $this->admin->assignRole('admin');

        $this->coordinator = User::factory()->create(['email' => 'vi_coord@gofmis.local']);
        $this->coordinator->assignRole('coordinator');

        $this->zoneA = Zone::create(['id' => (string) Str::uuid(), 'name' => 'Zone A', 'code' => 'ZA', 'coordinator_id' => $this->coordinator->id]);
        $this->zoneB = Zone::create(['id' => (string) Str::uuid(), 'name' => 'Zone B', 'code' => 'ZB']);

        $deceasedId = (string) Str::uuid();
        DB::table('deceased')->insert([
            'id' => $deceasedId, 'first_name' => 'A', 'last_name' => 'B',
            'nin' => '1234567890'.Str::random(6), 'reg_no' => 'REG-'.Str::random(8),
            'guardian_name' => 'G', 'guardian_phone' => '123',
            'vulnerability_status' => 'A', 'date_registered' => now()->toDateString(),
            'zone_id' => $this->zoneA->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $widowId = (string) Str::uuid();
        DB::table('widows')->insert([
            'id' => $widowId, 'first_name' => 'Jane', 'last_name' => 'Doe',
            'nin' => '1234567890'.Str::random(6), 'reg_no' => 'REG-'.Str::random(8),
            'is_eligible' => true, 'is_married' => false,
            'deceased_id' => $deceasedId, 'child_sequence' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->widowInZoneA = Widow::withoutGlobalScopes()->find($widowId);

        $orphanId = (string) Str::uuid();
        DB::table('orphans')->insert([
            'id' => $orphanId, 'first_name' => 'Jim', 'last_name' => 'Doe',
            'gender' => 'MALE', 'reg_no' => 'REG-'.Str::random(8),
            'is_eligible' => true, 'deceased_id' => $deceasedId, 'child_sequence' => 2,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->orphanInZoneA = Orphan::withoutGlobalScopes()->find($orphanId);

        $deceasedB = (string) Str::uuid();
        DB::table('deceased')->insert([
            'id' => $deceasedB, 'first_name' => 'C', 'last_name' => 'D',
            'nin' => '1234567890'.Str::random(6), 'reg_no' => 'REG-'.Str::random(8),
            'guardian_name' => 'G', 'guardian_phone' => '123',
            'vulnerability_status' => 'A', 'date_registered' => now()->toDateString(),
            'zone_id' => $this->zoneB->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $widowB = (string) Str::uuid();
        DB::table('widows')->insert([
            'id' => $widowB, 'first_name' => 'Joan', 'last_name' => 'Z',
            'nin' => '1234567890'.Str::random(6), 'reg_no' => 'REG-'.Str::random(8),
            'is_eligible' => true, 'is_married' => false,
            'deceased_id' => $deceasedB, 'child_sequence' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->widowInZoneB = Widow::withoutGlobalScopes()->find($widowB);
    }

    protected function activePrint(Widow|Orphan $beneficiary, string $position = 'right_thumb'): BeneficiaryFingerprint
    {
        return $beneficiary->fingerprints()->create([
            'finger_position' => $position,
            'encrypted_template' => 'ref-template-'.Str::random(8),
            'enrolled_by' => $this->admin->id,
            'is_active' => true,
        ]);
    }

    protected function biometricAudits(): \Illuminate\Support\Collection
    {
        return Activity::where('log_name', 'biometric')->get();
    }

    // ---------------- 1:1 VERIFICATION (domain) ----------------

    // 1. Admin can verify active Widow fingerprint (mock -> MATCH).
    public function test_admin_can_verify_active_widow_fingerprint()
    {
        config(['biometrics.mock.verify_outcome' => null]);
        $print = $this->activePrint($this->widowInZoneA);
        $svc = app(BiometricVerificationService::class);

        $outcome = $svc->verify($this->widowInZoneA, $print, $this->admin);

        $this->assertSame('match', $outcome['status']);
    }

    // 2. Admin can verify active Orphan fingerprint.
    public function test_admin_can_verify_active_orphan_fingerprint()
    {
        config(['biometrics.mock.verify_outcome' => null]);
        $print = $this->activePrint($this->orphanInZoneA);
        $svc = app(BiometricVerificationService::class);

        $this->assertSame('match', $svc->verify($this->orphanInZoneA, $print, $this->admin)['status']);
    }

    // 3. MATCH updates last_verified_at.
    public function test_match_updates_last_verified_at()
    {
        $print = $this->activePrint($this->widowInZoneA);
        $this->assertNull($print->last_verified_at);

        app(BiometricVerificationService::class)->verify($this->widowInZoneA, $print, $this->admin);

        $this->assertNotNull($print->fresh()->last_verified_at);
    }

    // 4. NO_MATCH does not update last_verified_at.
    public function test_no_match_does_not_update_last_verified_at()
    {
        config(['biometrics.mock.verify_outcome' => 'no_match']);
        $print = $this->activePrint($this->widowInZoneA);

        app(BiometricVerificationService::class)->verify($this->widowInZoneA, $print, $this->admin);

        $this->assertNull($print->fresh()->last_verified_at);
    }

    // 5. scanner error does not update last_verified_at.
    public function test_scanner_error_does_not_update_last_verified_at()
    {
        config(['biometrics.mock.verify_outcome' => 'scanner_unavailable']);
        $print = $this->activePrint($this->widowInZoneA);

        $outcome = app(BiometricVerificationService::class)->verify($this->widowInZoneA, $print, $this->admin);

        $this->assertSame('error', $outcome['status']);
        $this->assertNull($print->fresh()->last_verified_at);
    }

    // 6. revoked fingerprint cannot be verified normally.
    public function test_revoked_fingerprint_cannot_be_verified()
    {
        $print = $this->activePrint($this->widowInZoneA);
        $print->update(['is_active' => false, 'revoked_at' => now(), 'revocation_reason' => 'Injury']);

        $outcome = app(BiometricVerificationService::class)->verify($this->widowInZoneA, $print, $this->admin);

        $this->assertSame('error', $outcome['status']);
    }

    // 12. audit created for verify match.
    public function test_verify_match_is_audited()
    {
        $print = $this->activePrint($this->widowInZoneA);
        app(BiometricVerificationService::class)->verify($this->widowInZoneA, $print, $this->admin);

        $this->assertSame(1, $this->biometricAudits()->count());
        $audit = $this->biometricAudits()->first();
        $this->assertSame('verify', $audit->event);
        $this->assertSame('match', $audit->properties['result']);
        $this->assertSame('identity_verification', $audit->properties['purpose']);
    }

    // 13. audit created for verify no-match.
    public function test_verify_no_match_is_audited()
    {
        config(['biometrics.mock.verify_outcome' => 'no_match']);
        $print = $this->activePrint($this->widowInZoneA);
        app(BiometricVerificationService::class)->verify($this->widowInZoneA, $print, $this->admin);

        $this->assertSame('no_match', $this->biometricAudits()->first()->properties['result']);
    }

    // 20. lawful basis reference is recorded.
    public function test_audit_records_lawful_basis_reference()
    {
        $print = $this->activePrint($this->widowInZoneA);
        app(BiometricVerificationService::class)->verify($this->widowInZoneA, $print, $this->admin);

        $this->assertSame('REF-LEGAL-001', $this->biometricAudits()->first()->properties['lawful_basis_reference']);
    }

    // 22. Demo Observer denied.
    public function test_unauthorized_and_demo_observer_denied_for_verification()
    {
        $demo = User::factory()->create(['email' => 'demo@gofmis.local']);
        $demo->assignRole('demo_observer');
        $print = $this->activePrint($this->widowInZoneA);

        $this->assertFalse($demo->can('biometrics.verify'));

        try {
            app(BiometricVerificationService::class)->verify($this->widowInZoneA, $print, $demo);
            $this->fail('Expected AccessDeniedHttpException');
        } catch (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e) {
            $this->assertTrue(true);
        }
    }

    // 23. Coordinator (no verify permission) denied.
    public function test_coordinator_denied_cross_zone_verification()
    {
        $this->assertFalse($this->coordinator->can('biometrics.verify'));

        $printZoneB = $this->widowInZoneB->fingerprints()->create([
            'finger_position' => 'right_thumb',
            'encrypted_template' => 'zone-b-template',
            'enrolled_by' => $this->admin->id,
            'is_active' => true,
        ]);

        try {
            app(BiometricVerificationService::class)->verify($this->widowInZoneB, $printZoneB, $this->coordinator);
            $this->fail('Expected AccessDeniedHttpException');
        } catch (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e) {
            $this->assertTrue(true);
        }
    }

    // ---------------- 1:N IDENTIFICATION (domain) ----------------

    // 7. Admin identification can match an authorized active Widow.
    public function test_admin_identification_matches_active_widow()
    {
        config(['biometrics.mock.identify_outcome' => null]);
        $print = $this->activePrint($this->widowInZoneA);

        $outcome = app(BiometricIdentificationService::class)->identify($this->admin);

        $this->assertSame('match', $outcome['status']);
        $this->assertSame((string) $this->widowInZoneA->id, (string) $outcome['beneficiary']->id);
    }

    // 8. Admin identification can match an authorized active Orphan.
    public function test_admin_identification_matches_active_orphan()
    {
        config(['biometrics.mock.identify_outcome' => null]);
        $print = $this->activePrint($this->orphanInZoneA);

        $outcome = app(BiometricIdentificationService::class)->identify($this->admin);

        $this->assertSame('match', $outcome['status']);
        $this->assertSame((string) $this->orphanInZoneA->id, (string) $outcome['beneficiary']->id);
    }

    // 9. no-match handled correctly.
    public function test_identification_no_match_handled()
    {
        config(['biometrics.mock.identify_outcome' => 'no_match']);
        $this->activePrint($this->widowInZoneA);

        $outcome = app(BiometricIdentificationService::class)->identify($this->admin);

        $this->assertSame('no_match', $outcome['status']);
        $this->assertNull($outcome['beneficiary']);
    }

    // 10. revoked fingerprints excluded from candidate pool.
    public function test_revoked_fingerprints_excluded_from_candidates()
    {
        config(['biometrics.mock.identify_outcome' => null]);
        $revoked = $this->activePrint($this->widowInZoneA);
        $revoked->update(['is_active' => false, 'revoked_at' => now(), 'revocation_reason' => 'Injury']);

        // Only one active none-revoked candidate remains in admin pool.
        $outcome = app(BiometricIdentificationService::class)->identify($this->admin);

        // The mock matches the FIRST candidate; with only the revoked one gone,
        // the pool is empty -> no candidate -> no_match.
        $this->assertSame('no_match', $outcome['status']);
    }

    // 11. only active fingerprint rows sent as candidates.
    public function test_only_active_fingerprints_in_candidates()
    {
        config(['biometrics.mock.identify_outcome' => 'match']);
        $active = $this->activePrint($this->widowInZoneA);
        $inactive = $this->activePrint($this->widowInZoneA, 'left_index');
        $inactive->update(['is_active' => false, 'revoked_at' => now()]);

        $outcome = app(BiometricIdentificationService::class)->identify($this->admin);

        $this->assertSame('match', $outcome['status']);
        $this->assertSame((string) $active->id, (string) $outcome['fingerprint']->id);
    }

    // 14. audit created for identify match.
    public function test_identify_match_is_audited()
    {
        config(['biometrics.mock.identify_outcome' => null]);
        $this->activePrint($this->widowInZoneA);

        app(BiometricIdentificationService::class)->identify($this->admin);

        $audit = $this->biometricAudits()->first();
        $this->assertSame('identify', $audit->event);
        $this->assertSame('match', $audit->properties['result']);
        $this->assertSame('identification', $audit->properties['purpose']);
    }

    // 22 (coordinate scene) - Coordinator cannot identify (no permission).
    public function test_coordinator_cannot_identify()
    {
        $this->assertFalse($this->coordinator->can('biometrics.identify'));

        try {
            app(BiometricIdentificationService::class)->identify($this->coordinator);
            $this->fail('Expected AccessDeniedHttpException');
        } catch (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e) {
            $this->assertTrue(true);
        }
    }

    // 16. no template in audit.
    public function test_audit_never_contains_templates_or_candidate_arrays()
    {
        config(['biometrics.mock.verify_outcome' => 'match']);
        config(['biometrics.mock.identify_outcome' => 'match']);
        $this->activePrint($this->widowInZoneA);

        app(BiometricIdentificationService::class)->identify($this->admin);
        app(BiometricVerificationService::class)->verify($this->widowInZoneA, $this->widowInZoneA->fingerprints()->first(), $this->admin);

        foreach ($this->biometricAudits() as $audit) {
            $json = json_encode($audit->properties->toArray());
            $this->assertStringNotContainsString('ref-template', $json);
            $this->assertArrayNotHasKey('candidates', $audit->properties->toArray());
            $this->assertArrayNotHasKey('reference_template', $audit->properties->toArray());
        }
    }

    // HTTP client contract — identify candidate-count limit.
    public function test_identify_candidate_count_limit_applies_server_side()
    {
        config(['biometrics.identification.max_candidates' => 0]);
        config(['biometrics.mock.identify_outcome' => null]);
        $this->activePrint($this->widowInZoneA);

        $outcome = app(BiometricIdentificationService::class)->identify($this->admin);

        $this->assertSame('error', $outcome['status']);
        $this->assertSame('candidate_limit_exceeded', $outcome['category']);
    }

    // ====================================================================
    //  IDENTIFY BENEFICIARY — END-TO-END ACCEPTANCE (section 37)
    // ====================================================================

    // Widow end-to-end: unknown scan -> MATCH -> resolve Widow A + audit.
    public function test_identify_beneficiary_end_to_end_matches_and_resolves_widow()
    {
        config(['biometrics.mock.identify_outcome' => null]);
        $widowPrint = $this->activePrint($this->widowInZoneA);

        $outcome = app(BiometricIdentificationService::class)->identify($this->admin);

        $this->assertSame('match', $outcome['status']);
        $this->assertInstanceOf(\App\Models\Widow::class, $outcome['beneficiary']);
        $this->assertSame((string) $this->widowInZoneA->id, (string) $outcome['beneficiary']->id);
        $this->assertSame((string) $widowPrint->id, (string) $outcome['fingerprint']->id);

        // Safe authorised summary is available to the UI (no template exposed).
        $this->assertSame('Jane', $outcome['beneficiary']->first_name);

        // Identification MATCH is audited for the identification purpose.
        $audit = $this->biometricAudits()->first();
        $this->assertSame('identify', $audit->event);
        $this->assertSame('match', $audit->properties['result']);
        $this->assertSame('identification', $audit->properties['purpose']);
    }

    // Orphan end-to-end: unknown scan -> MATCH -> resolve Orphan A + audit.
    public function test_identify_beneficiary_end_to_end_matches_and_resolves_orphan()
    {
        config(['biometrics.mock.identify_outcome' => null]);
        $orphanPrint = $this->activePrint($this->orphanInZoneA);

        $outcome = app(BiometricIdentificationService::class)->identify($this->admin);

        $this->assertSame('match', $outcome['status']);
        $this->assertInstanceOf(\App\Models\Orphan::class, $outcome['beneficiary']);
        $this->assertSame((string) $this->orphanInZoneA->id, (string) $outcome['beneficiary']->id);
        $this->assertSame((string) $orphanPrint->id, (string) $outcome['fingerprint']->id);

        $audit = $this->biometricAudits()->first();
        $this->assertSame('identify', $audit->event);
        $this->assertSame('match', $audit->properties['result']);
    }

    // No-match end-to-end: no beneficiary is created or enrolled.
    public function test_identify_beneficiary_no_match_does_not_create_or_enroll()
    {
        config(['biometrics.mock.identify_outcome' => 'no_match']);
        $this->activePrint($this->widowInZoneA);

        $beforeWidowCount = \App\Models\Widow::withoutGlobalScopes()->count();
        $beforeOrphanCount = \App\Models\Orphan::withoutGlobalScopes()->count();
        $beforeFingerprintCount = $this->widowInZoneA->fingerprints()->count();

        $outcome = app(BiometricIdentificationService::class)->identify($this->admin);

        $this->assertSame('no_match', $outcome['status']);
        $this->assertNull($outcome['beneficiary']);
        $this->assertNull($outcome['fingerprint']);
        $this->assertSame($beforeWidowCount, \App\Models\Widow::withoutGlobalScopes()->count());
        $this->assertSame($beforeOrphanCount, \App\Models\Orphan::withoutGlobalScopes()->count());
        $this->assertSame($beforeFingerprintCount, $this->widowInZoneA->fingerprints()->count());

        $audit = $this->biometricAudits()->first();
        $this->assertSame('no_match', $audit->properties['result']);
    }

    // Scanner error end-to-end is NOT reported as "no match".
    public function test_identify_beneficiary_scanner_error_is_not_no_match()
    {
        config(['biometrics.mock.identify_outcome' => 'scanner_unavailable']);
        $this->activePrint($this->widowInZoneA);

        $outcome = app(BiometricIdentificationService::class)->identify($this->admin);

        $this->assertNotSame('no_match', $outcome['status']);
        $this->assertSame('error', $outcome['status']);
        $this->assertNull($outcome['beneficiary']);

        $audit = $this->biometricAudits()->first();
        $this->assertSame('error', $audit->properties['result']);
    }
}
