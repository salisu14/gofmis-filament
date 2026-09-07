<?php

namespace Tests\Feature\Biometrics;

use App\Models\BeneficiaryFingerprint;
use App\Models\Orphan;
use App\Models\User;
use App\Models\Widow;
use App\Services\Biometrics\BiometricTemplateCipher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BiometricTemplateEncryptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['biometrics.client' => 'mock']);
        config(['activitylog.table_name' => 'activities']);
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->seed(\Database\Seeders\ZonesTableSeeder::class);

        $user = User::factory()->create(['email' => 'cipher_test@gofmis.local']);
        $user->assignRole('admin');
        $this->actingAs($user);

        $zoneId = \App\Models\Zone::first()->id ?? \Illuminate\Support\Str::uuid()->toString();
        $deceasedId = (string) \Illuminate\Support\Str::uuid();
        DB::table('deceased')->insert([
            'id' => $deceasedId,
            'first_name' => 'John', 'last_name' => 'Doe',
            'nin' => '12345678901'.rand(10, 99),
            'reg_no' => 'REG-'.rand(1000, 9999).rand(1, 9),
            'guardian_name' => 'Guardian', 'guardian_phone' => '1234567890',
            'vulnerability_status' => 'A', 'date_registered' => now()->toDateString(),
            'zone_id' => $zoneId, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $widowId = (string) \Illuminate\Support\Str::uuid();
        DB::table('widows')->insert([
            'id' => $widowId,
            'first_name' => 'Jane', 'last_name' => 'Doe',
            'nin' => '12345678902'.rand(10, 99),
            'reg_no' => 'REG-'.rand(1000, 9999).rand(1, 9),
            'is_eligible' => true, 'is_married' => false,
            'deceased_id' => $deceasedId, 'child_sequence' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->widow = Widow::first();

        $orphanId = (string) \Illuminate\Support\Str::uuid();
        DB::table('orphans')->insert([
            'id' => $orphanId,
            'first_name' => 'Jimmy', 'last_name' => 'Doe',
            'gender' => 'MALE', 'reg_no' => 'REG-'.rand(1000, 9999).rand(1, 9),
            'is_eligible' => true, 'deceased_id' => $deceasedId, 'child_sequence' => 2,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->orphan = Orphan::first();

        $this->cipher = app(BiometricTemplateCipher::class);
    }

    public function test_new_template_is_encrypted_with_biometric_envelope_at_rest()
    {
        $print = $this->widow->fingerprints()->create([
            'finger_position' => 'right_thumb',
            'encrypted_template' => 'plain_right_thumb',
            'enrolled_by' => auth()->id(),
        ]);

        $raw = DB::table('beneficiary_fingerprints')->where('id', $print->id)->value('encrypted_template');

        $this->assertStringStartsWith('biometric:v', $raw);
        $this->assertNotEquals('plain_right_thumb', $raw);
        $this->assertSame(1, (int) DB::table('beneficiary_fingerprints')->where('id', $print->id)->value('key_version'));
    }

    public function test_dedicated_key_can_decrypt_new_value()
    {
        $this->widow->fingerprints()->create([
            'finger_position' => 'right_thumb',
            'encrypted_template' => 'cipher_boundary_test',
            'enrolled_by' => auth()->id(),
        ]);

        $raw = DB::table('beneficiary_fingerprints')->where('beneficiary_id', $this->widow->id)->value('encrypted_template');

        $this->assertEquals('cipher_boundary_test', $this->cipher->decrypt($raw));
    }

    public function test_app_key_alone_is_not_the_encryption_boundary_for_new_templates()
    {
        $this->widow->fingerprints()->create([
            'finger_position' => 'right_thumb',
            'encrypted_template' => 'not_wrapped_by_app_key_only',
            'enrolled_by' => auth()->id(),
        ]);

        $raw = DB::table('beneficiary_fingerprints')->where('beneficiary_id', $this->widow->id)->value('encrypted_template');

        $this->assertStringStartsWith('biometric:v', $raw);

        // The stored value is NOT a bare Laravel APP_KEY payload.
        $this->assertFalse(str_starts_with($raw, 'eyJ'));

        // And it cannot be read by the APP_KEY encrypter directly.
        $this->expectException(\Illuminate\Contracts\Encryption\DecryptException::class);
        Crypt::decryptString($raw);
    }

    public function test_model_serialization_does_not_expose_template()
    {
        $print = $this->widow->fingerprints()->create([
            'finger_position' => 'left_index',
            'encrypted_template' => 'hidden_serialization_secret',
            'enrolled_by' => auth()->id(),
        ]);

        $array = $print->toArray();
        $this->assertArrayNotHasKey('encrypted_template', $array);
        $this->assertArrayNotHasKey('decrypted_template', $array);

        $json = $print->toJson();
        $this->assertStringNotContainsString('hidden_serialization_secret', $json);
        $this->assertStringNotContainsString('encrypted_template', $json);
    }

    public function test_activity_log_does_not_contain_template()
    {
        $print = $this->widow->fingerprints()->create([
            'finger_position' => 'right_ring',
            'encrypted_template' => 'audit_must_not_see_this',
            'enrolled_by' => auth()->id(),
        ]);

        $audit = DB::table('activities')
            ->where('subject_type', BeneficiaryFingerprint::class)
            ->where('subject_id', $print->id)
            ->first();

        $this->assertNotNull($audit);
        $json = $audit->properties ?? $audit->attribute_changes ?? '';
        $this->assertStringNotContainsString('audit_must_not_see_this', $json);
        $this->assertStringNotContainsString('encrypted_template', $json);
    }

    public function test_invalid_or_missing_biometric_key_fails_safely_for_new_writes()
    {
        config(['biometrics.encryption.key' => null]);

        $cipher = app(BiometricTemplateCipher::class);
        $this->assertFalse($cipher->isKeyAvailable());

        $this->expectException(\RuntimeException::class);
        $cipher->encrypt('should_fail');
    }

    public function test_invalid_key_does_not_silently_fall_back_to_app_key()
    {
        config(['biometrics.encryption.key' => 'not-a-valid-base64-key!!']);

        $cipher = app(BiometricTemplateCipher::class);
        $this->assertFalse($cipher->isKeyAvailable());
        $this->expectException(\RuntimeException::class);
        $cipher->encrypt('must_not_fall_back');
    }

    public function test_legacy_app_key_ciphertext_can_be_migrated()
    {
        // Simulate a legacy row produced by Laravel's encrypted cast (APP_KEY).
        $legacyCiphertext = Crypt::encryptString('legacy_template_data');

        $id = (string) \Illuminate\Support\Str::uuid();
        DB::table('beneficiary_fingerprints')->insert([
            'id' => $id,
            'beneficiary_type' => get_class($this->widow),
            'beneficiary_id' => $this->widow->id,
            'finger_position' => 'right_thumb',
            'encrypted_template' => $legacyCiphertext,
            'enrolled_by' => auth()->id(),
            'is_active' => true,
            'enrolled_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $print = BeneficiaryFingerprint::find($id);

        // Stored value should be a legacy APP_KEY payload, not yet the biometric envelope.
        $this->assertStringStartsWith('eyJ', $print->getRawOriginal('encrypted_template'));

        // Readable via the cipher's legacy path.
        $this->assertEquals('legacy_template_data', $this->cipher->decrypt($legacyCiphertext));

        // Migrate via the command/service.
        $this->assertTrue($print->reencryptTemplate());

        $raw = DB::table('beneficiary_fingerprints')->where('id', $id)->value('encrypted_template');
        $this->assertStringStartsWith('biometric:v', $raw);
        $this->assertEquals('legacy_template_data', $this->cipher->decrypt($raw));
        $this->assertSame(1, (int) DB::table('beneficiary_fingerprints')->where('id', $id)->value('key_version'));
    }

    public function test_reencryption_is_idempotent()
    {
        $legacyCiphertext = Crypt::encryptString('idempotent_template');

        $id = (string) \Illuminate\Support\Str::uuid();
        DB::table('beneficiary_fingerprints')->insert([
            'id' => $id,
            'beneficiary_type' => get_class($this->widow),
            'beneficiary_id' => $this->widow->id,
            'finger_position' => 'right_thumb',
            'encrypted_template' => $legacyCiphertext,
            'enrolled_by' => auth()->id(),
            'is_active' => true,
            'enrolled_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $print = BeneficiaryFingerprint::find($id);

        $this->assertTrue($print->reencryptTemplate());

        $ciphertextAfterFirst = DB::table('beneficiary_fingerprints')->where('id', $id)->value('encrypted_template');

        // Second run reports nothing to do and leaves value unchanged.
        $this->assertFalse(BeneficiaryFingerprint::find($id)->reencryptTemplate());
        $this->assertSame($ciphertextAfterFirst, DB::table('beneficiary_fingerprints')->where('id', $id)->value('encrypted_template'));
    }

    public function test_failed_legacy_decryption_does_not_destroy_existing_ciphertext()
    {
        // A value that is NOT a legacy APP_KEY payload and NOT a current envelope.
        $garbage = 'not-a-real-ciphertext';

        $id = (string) \Illuminate\Support\Str::uuid();
        DB::table('beneficiary_fingerprints')->insert([
            'id' => $id,
            'beneficiary_type' => get_class($this->widow),
            'beneficiary_id' => $this->widow->id,
            'finger_position' => 'right_thumb',
            'encrypted_template' => $garbage,
            'enrolled_by' => auth()->id(),
            'is_active' => true,
            'enrolled_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $print = BeneficiaryFingerprint::find($id);

        // Should fail safely (return false, no exception), preserving the value.
        $this->assertFalse($print->reencryptTemplate());
        $this->assertSame($garbage, DB::table('beneficiary_fingerprints')->where('id', $id)->value('encrypted_template'));
    }

    public function test_multiple_metadata_fields_survive_reencryption()
    {
        $legacyCiphertext = Crypt::encryptString('metadata_rich_template');

        $id = (string) \Illuminate\Support\Str::uuid();
        DB::table('beneficiary_fingerprints')->insert([
            'id' => $id,
            'beneficiary_type' => get_class($this->widow),
            'beneficiary_id' => $this->widow->id,
            'finger_position' => 'right_thumb',
            'encrypted_template' => $legacyCiphertext,
            'template_format' => 'raw',
            'template_version' => 'v2',
            'quality_score' => 88,
            'source' => 'hardware',
            'device_manufacturer' => 'SecuGen',
            'device_model' => 'Hamster Plus',
            'device_serial' => 'SN-123',
            'sdk_version' => '1.4',
            'enrolled_by' => auth()->id(),
            'is_active' => true,
            'enrolled_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $print = BeneficiaryFingerprint::find($id);
        $this->assertTrue($print->reencryptTemplate());
        $print->refresh();

        $this->assertSame('raw', $print->template_format);
        $this->assertSame('v2', $print->template_version);
        $this->assertSame(88, (int) $print->quality_score);
        $this->assertSame('hardware', $print->source);
        $this->assertSame('SecuGen', $print->device_manufacturer);
        $this->assertSame('Hamster Plus', $print->device_model);
        $this->assertSame('SN-123', $print->device_serial);
        $this->assertSame('1.4', $print->sdk_version);
        $this->assertTrue($print->is_active);
        $this->assertTrue($print->usesCurrentEnvelope());
    }

    public function test_revoked_historical_records_remain_intact()
    {
        // A revoked legacy record (raw legacy ciphertext) plus a new re-enrollment.
        $legacyCiphertext = Crypt::encryptString('revoked_template');

        $id1 = (string) \Illuminate\Support\Str::uuid();
        DB::table('beneficiary_fingerprints')->insert([
            'id' => $id1,
            'beneficiary_type' => get_class($this->widow),
            'beneficiary_id' => $this->widow->id,
            'finger_position' => 'right_thumb',
            'encrypted_template' => $legacyCiphertext,
            'enrolled_by' => auth()->id(),
            'is_active' => false,
            'revoked_at' => now(),
            'revocation_reason' => 'Finger injured',
            'enrolled_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $print1 = BeneficiaryFingerprint::find($id1);

        $print2 = $this->widow->fingerprints()->create([
            'finger_position' => 'right_thumb',
            'encrypted_template' => 'current_enrollment',
            'enrolled_by' => auth()->id(),
            'is_active' => true,
        ]);

        $this->assertTrue($print1->reencryptTemplate());
        $print2->refresh();

        // Both records retained, historical one still revoked.
        $this->assertEquals(2, $this->widow->fingerprints()->count());
        $this->assertFalse($print1->is_active);
        $this->assertNotNull($print1->revoked_at);
        $this->assertEquals('Finger injured', $print1->revocation_reason);
        $this->assertTrue($print2->is_active);
        $this->assertEquals('current_enrollment', $print2->decryptedTemplate());
    }

    public function test_eligibility_state_change_does_not_destroy_fingerprint_identity()
    {
        // A widow enrolled with biometrics is marked ineligible (e.g. remarried)
        // but the biometric identity record must survive.
        $print = $this->widow->fingerprints()->create([
            'finger_position' => 'right_thumb',
            'encrypted_template' => 'identity_survives',
            'enrolled_by' => auth()->id(),
            'is_active' => true,
        ]);

        $this->widow->update(['is_eligible' => false, 'is_married' => true]);

        $print->refresh();
        $this->assertNotNull($print);
        $this->assertTrue($print->is_active);
        $this->assertEquals('identity_survives', $print->decryptedTemplate());
        $this->assertEquals(1, $this->widow->fingerprints()->where('is_active', true)->count());
    }

    public function test_orphan_can_carry_encrypted_fingerprint_metadata()
    {
        $print = $this->orphan->fingerprints()->create([
            'finger_position' => 'left_thumb',
            'encrypted_template' => 'orphan_fingerprint',
            'enrolled_by' => auth()->id(),
        ]);

        $raw = DB::table('beneficiary_fingerprints')->where('id', $print->id)->value('encrypted_template');
        $this->assertStringStartsWith('biometric:v', $raw);
        $this->assertEquals('orphan_fingerprint', $print->decryptedTemplate());
    }
}
