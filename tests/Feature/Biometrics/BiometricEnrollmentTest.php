<?php

namespace Tests\Feature\Biometrics;

use App\Models\BeneficiaryFingerprint;
use App\Models\Orphan;
use App\Models\User;
use App\Models\Widow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BiometricEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['biometrics.client' => 'mock']);
        config(['activitylog.table_name' => 'activities']);
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->seed(\Database\Seeders\ZonesTableSeeder::class);

        // Ensure we have a user
        $user = \App\Models\User::factory()->create([
            'email' => 'admin_test@gofmis.local',
        ]);
        $user->assignRole('admin');
        $this->actingAs($user);

        $zoneId = \App\Models\Zone::first()->id ?? \Illuminate\Support\Str::uuid()->toString();

        $deceasedId = (string) \Illuminate\Support\Str::uuid();
        \Illuminate\Support\Facades\DB::table('deceased')->insert([
            'id' => $deceasedId,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'nin' => '12345678901'.rand(10, 99),
            'reg_no' => 'REG-'.rand(1000, 9999).rand(1, 9),
            'guardian_name' => 'Guardian',
            'guardian_phone' => '1234567890',
            'vulnerability_status' => 'A',
            'date_registered' => now()->toDateString(),
            'zone_id' => $zoneId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $widowId = (string) \Illuminate\Support\Str::uuid();
        \Illuminate\Support\Facades\DB::table('widows')->insert([
            'id' => $widowId,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'nin' => '12345678902'.rand(10, 99),
            'reg_no' => 'REG-'.rand(1000, 9999).rand(1, 9),
            'is_eligible' => true,
            'is_married' => false,
            'deceased_id' => $deceasedId,
            'child_sequence' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (strpos(get_class($this), 'BiometricEnrollmentTest') !== false) {
            $orphanId = (string) \Illuminate\Support\Str::uuid();
            \Illuminate\Support\Facades\DB::table('orphans')->insert([
                'id' => $orphanId,
                'first_name' => 'Jimmy',
                'last_name' => 'Doe',
                'gender' => 'MALE',
                'reg_no' => 'REG-'.rand(1000, 9999).rand(1, 9),
                'is_eligible' => true,
                'deceased_id' => $deceasedId,
                'child_sequence' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Ensure we have a widow and orphan by calling dev DB seeder if possible
        // Actually, just using DB builder is safer because of missing factories

    }

    public function test_widow_and_orphan_can_have_fingerprint_enrollment_metadata()
    {
        $widow = Widow::first();
        if (! $widow) {
            dd('WIDOWS:', \Illuminate\Support\Facades\DB::table('widows')->get(), 'DECEASED:', \Illuminate\Support\Facades\DB::table('deceased')->get());
        }
        $orphan = Orphan::first();
        $user = auth()->user();

        $widowPrint = $widow->fingerprints()->create([
            'finger_position' => 'right_thumb',
            'encrypted_template' => 'mock_template_widow',
            'enrolled_by' => $user->id,
        ]);

        $orphanPrint = $orphan->fingerprints()->create([
            'finger_position' => 'left_index',
            'encrypted_template' => 'mock_template_orphan',
            'enrolled_by' => $user->id,
        ]);

        $this->assertEquals(1, $widow->fingerprints()->count());
        $this->assertEquals(1, $orphan->fingerprints()->count());
        $this->assertEquals('mock_template_widow', $widowPrint->encrypted_template);
        $this->assertEquals('mock_template_orphan', $orphanPrint->encrypted_template);
    }

    public function test_multiple_finger_positions_can_be_enrolled()
    {
        $widow = Widow::first();
        if (! $widow) {
            dd('WIDOWS:', \Illuminate\Support\Facades\DB::table('widows')->get(), 'DECEASED:', \Illuminate\Support\Facades\DB::table('deceased')->get());
        }
        $user = auth()->user();

        $widow->fingerprints()->create([
            'finger_position' => 'right_thumb',
            'encrypted_template' => 'template1',
            'enrolled_by' => $user->id,
        ]);

        $widow->fingerprints()->create([
            'finger_position' => 'right_index',
            'encrypted_template' => 'template2',
            'enrolled_by' => $user->id,
        ]);

        $this->assertEquals(2, $widow->fingerprints()->count());
    }

    public function test_same_active_finger_position_cannot_be_duplicated_for_same_beneficiary()
    {
        $widow = Widow::first();
        if (! $widow) {
            dd('WIDOWS:', \Illuminate\Support\Facades\DB::table('widows')->get(), 'DECEASED:', \Illuminate\Support\Facades\DB::table('deceased')->get());
        }
        $user = auth()->user();

        $widow->fingerprints()->create([
            'finger_position' => 'right_thumb',
            'encrypted_template' => 'template1',
            'enrolled_by' => $user->id,
            'is_active' => true,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Duplicate active finger position for beneficiary.');
        $widow->fingerprints()->create([
            'finger_position' => 'right_thumb',
            'encrypted_template' => 'template2',
            'enrolled_by' => $user->id,
            'is_active' => true,
        ]);
    }

    public function test_templates_are_encrypted_at_rest()
    {
        $widow = Widow::first();
        if (! $widow) {
            dd('WIDOWS:', \Illuminate\Support\Facades\DB::table('widows')->get(), 'DECEASED:', \Illuminate\Support\Facades\DB::table('deceased')->get());
        }
        $user = auth()->user();

        $print = $widow->fingerprints()->create([
            'finger_position' => 'right_thumb',
            'encrypted_template' => 'my_secret_template_data',
            'enrolled_by' => $user->id,
        ]);

        // Check raw DB value
        $raw = DB::table('beneficiary_fingerprints')->where('id', $print->id)->first();

        $this->assertNotEquals('my_secret_template_data', $raw->encrypted_template);
        $this->assertStringStartsWith('biometric:v', $raw->encrypted_template);
        $this->assertEquals('my_secret_template_data', app(\App\Services\Biometrics\BiometricTemplateCipher::class)->decrypt($raw->encrypted_template));
    }

    public function test_template_is_not_exposed_through_model_serialization()
    {
        $widow = Widow::first();
        if (! $widow) {
            dd('WIDOWS:', \Illuminate\Support\Facades\DB::table('widows')->get(), 'DECEASED:', \Illuminate\Support\Facades\DB::table('deceased')->get());
        }
        $user = auth()->user();

        $print = $widow->fingerprints()->create([
            'finger_position' => 'right_thumb',
            'encrypted_template' => 'my_secret_template_data',
            'enrolled_by' => $user->id,
        ]);

        $array = $print->toArray();
        $this->assertArrayNotHasKey('encrypted_template', $array);

        $json = $print->toJson();
        $this->assertStringNotContainsString('my_secret_template_data', $json);
        $this->assertStringNotContainsString('encrypted_template', $json);
    }

    public function test_audit_event_is_generated_without_template_contents()
    {
        $widow = Widow::first();
        if (! $widow) {
            dd('WIDOWS:', \Illuminate\Support\Facades\DB::table('widows')->get(), 'DECEASED:', \Illuminate\Support\Facades\DB::table('deceased')->get());
        }
        $user = auth()->user();

        $print = $widow->fingerprints()->create([
            'finger_position' => 'right_thumb',
            'encrypted_template' => 'super_secret_finger',
            'enrolled_by' => $user->id,
        ]);

        $audit = \Illuminate\Support\Facades\DB::table('activities')->where('subject_type', BeneficiaryFingerprint::class)->where('subject_id', $print->id)->first();

        $this->assertNotNull($audit);

        $properties = json_decode($audit->properties, true);
        // dd('AUDIT:', $audit);
        $properties = json_decode($audit->attribute_changes ?? $audit->properties, true);
        $this->assertArrayHasKey('attributes', $properties);
        $this->assertArrayNotHasKey('encrypted_template', $properties['attributes']);
        $this->assertEquals('right_thumb', $properties['attributes']['finger_position']);
    }

    public function test_authorized_user_can_revoke_enrollment()
    {
        $widow = Widow::first();
        if (! $widow) {
            dd('WIDOWS:', \Illuminate\Support\Facades\DB::table('widows')->get(), 'DECEASED:', \Illuminate\Support\Facades\DB::table('deceased')->get());
        }
        $user = auth()->user();

        $print = $widow->fingerprints()->create([
            'finger_position' => 'right_thumb',
            'encrypted_template' => 'template1',
            'enrolled_by' => $user->id,
            'is_active' => true,
        ]);

        $print->update([
            'is_active' => false,
            'revoked_at' => now(),
            'revocation_reason' => 'Finger injured',
        ]);

        $this->assertFalse($print->is_active);
        $this->assertNotNull($print->revoked_at);
        $this->assertEquals('Finger injured', $print->revocation_reason);

        // Can now enroll the same finger again since the old one is revoked
        $newPrint = $widow->fingerprints()->create([
            'finger_position' => 'right_thumb',
            'encrypted_template' => 'template2',
            'enrolled_by' => $user->id,
            'is_active' => true,
        ]);

        $this->assertTrue($newPrint->is_active);
    }

    public function test_database_level_partial_unique_index_prevents_active_fingerprint_concurrency_race()
    {
        $widow = Widow::first();
        $user = auth()->user();

        // 1. Insert first active print via raw DB to simulate race condition
        DB::table('beneficiary_fingerprints')->insert([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'beneficiary_type' => get_class($widow),
            'beneficiary_id' => $widow->id,
            'finger_position' => 'left_thumb',
            'encrypted_template' => 'encrypted_raw_1',
            'is_active' => true,
            'enrolled_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Insert second active print via raw DB (bypassing model hook) -> MUST trigger DB Unique Constraint Violation
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        DB::table('beneficiary_fingerprints')->insert([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'beneficiary_type' => get_class($widow),
            'beneficiary_id' => $widow->id,
            'finger_position' => 'left_thumb',
            'encrypted_template' => 'encrypted_raw_2',
            'is_active' => true,
            'enrolled_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_database_level_partial_unique_index_allows_multiple_revoked_records()
    {
        $widow = Widow::first();

        // Raw DB insert of 3 revoked records for the same beneficiary & position
        for ($i = 1; $i <= 3; $i++) {
            DB::table('beneficiary_fingerprints')->insert([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'beneficiary_type' => get_class($widow),
                'beneficiary_id' => $widow->id,
                'finger_position' => 'right_middle',
                'encrypted_template' => "encrypted_revoked_{$i}",
                'is_active' => false,
                'revoked_at' => now(),
                'revocation_reason' => "Revocation {$i}",
                'enrolled_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $revokedCount = DB::table('beneficiary_fingerprints')
            ->where('beneficiary_id', $widow->id)
            ->where('finger_position', 'right_middle')
            ->where('is_active', false)
            ->count();

        $this->assertEquals(3, $revokedCount);
    }
}
