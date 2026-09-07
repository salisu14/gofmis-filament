<?php

namespace Tests\Feature;

use App\Enums\Gender;
use App\Enums\OrphanStatus;
use App\Enums\UserStatus;
use App\Models\Deceased;
use App\Models\Orphan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BeneficiaryCertificateMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        Storage::fake('local');
        Storage::fake('public');
    }

    protected function createOrphan(array $attributes = []): Orphan
    {
        $deceased = Deceased::factory()->create();

        return Orphan::create(array_merge([
            'deceased_id' => $deceased->id,
            'reg_no' => 'ORPH-TEST-'.rand(1000, 9999),
            'first_name' => 'Test',
            'last_name' => 'Orphan',
            'gender' => Gender::MALE,
            'date_of_birth' => '2015-01-01',
            'status' => OrphanStatus::ACTIVE,
            'birth_certificate_path' => 'birth-certificates/legacy-cert.pdf',
        ], $attributes));
    }

    public function test_dry_run_mode_does_not_copy_or_delete_files(): void
    {
        $orphan = $this->createOrphan([
            'birth_certificate_path' => 'birth-certificates/legacy-cert.pdf',
        ]);

        Storage::disk('public')->put('birth-certificates/legacy-cert.pdf', 'dummy-pdf-content');

        $this->artisan('beneficiaries:migrate-certificates-private')
            ->assertExitCode(0);

        Storage::disk('local')->assertMissing('birth-certificates/legacy-cert.pdf');
        Storage::disk('public')->assertExists('birth-certificates/legacy-cert.pdf');
    }

    public function test_apply_mode_migrates_public_certificate_to_private_storage(): void
    {
        $orphan = $this->createOrphan([
            'birth_certificate_path' => 'birth-certificates/legacy-orphan-cert.pdf',
        ]);

        $content = 'valid-orphan-certificate-pdf-binary-data';
        Storage::disk('public')->put('birth-certificates/legacy-orphan-cert.pdf', $content);

        $this->artisan('beneficiaries:migrate-certificates-private', ['--apply' => true])
            ->assertExitCode(0);

        Storage::disk('local')->assertExists('birth-certificates/legacy-orphan-cert.pdf');
        $this->assertSame($content, Storage::disk('local')->get('birth-certificates/legacy-orphan-cert.pdf'));
        Storage::disk('public')->assertExists('birth-certificates/legacy-orphan-cert.pdf');
    }

    public function test_apply_mode_with_cleanup_public_removes_public_file_after_verification(): void
    {
        $deceased = Deceased::factory()->create([
            'death_cert_url' => 'death-certs/legacy-deceased-cert.pdf',
        ]);

        $content = 'valid-deceased-certificate-pdf-data';
        Storage::disk('public')->put('death-certs/legacy-deceased-cert.pdf', $content);

        $this->artisan('beneficiaries:migrate-certificates-private', [
            '--apply' => true,
            '--cleanup-public' => true,
        ])->assertExitCode(0);

        Storage::disk('local')->assertExists('death-certs/legacy-deceased-cert.pdf');
        Storage::disk('public')->assertMissing('death-certs/legacy-deceased-cert.pdf');
    }

    public function test_already_private_certificate_is_skipped_and_reported(): void
    {
        $this->createOrphan([
            'birth_certificate_path' => 'birth-certificates/already-private.pdf',
        ]);

        Storage::disk('local')->put('birth-certificates/already-private.pdf', 'private-content');

        $this->artisan('beneficiaries:migrate-certificates-private', ['--json' => true])
            ->assertExitCode(0);
    }

    public function test_missing_certificate_files_are_reported_without_error(): void
    {
        $this->createOrphan([
            'birth_certificate_path' => 'birth-certificates/missing-file.pdf',
        ]);

        $this->artisan('beneficiaries:migrate-certificates-private', ['--json' => true])
            ->assertExitCode(0);
    }

    public function test_unreferenced_public_files_are_flagged_safely(): void
    {
        Storage::disk('public')->put('birth-certificates/orphaned-unreferenced-file.pdf', 'stray-data');

        $this->artisan('beneficiaries:migrate-certificates-private', ['--json' => true])
            ->assertExitCode(0);

        Storage::disk('public')->assertExists('birth-certificates/orphaned-unreferenced-file.pdf');
    }

    public function test_repeated_apply_execution_is_idempotent(): void
    {
        $orphan = $this->createOrphan([
            'birth_certificate_path' => 'birth-certificates/idempotent-cert.pdf',
        ]);

        $content = 'idempotency-test-content';
        Storage::disk('public')->put('birth-certificates/idempotent-cert.pdf', $content);

        // Run apply once
        $this->artisan('beneficiaries:migrate-certificates-private', ['--apply' => true, '--cleanup-public' => true])
            ->assertExitCode(0);

        Storage::disk('local')->assertExists('birth-certificates/idempotent-cert.pdf');
        Storage::disk('public')->assertMissing('birth-certificates/idempotent-cert.pdf');

        // Run apply a second time
        $this->artisan('beneficiaries:migrate-certificates-private', ['--apply' => true, '--cleanup-public' => true])
            ->assertExitCode(0);

        Storage::disk('local')->assertExists('birth-certificates/idempotent-cert.pdf');
    }

    public function test_hash_conflict_between_public_and_private_file_prevents_deletion(): void
    {
        $orphan = $this->createOrphan([
            'birth_certificate_path' => 'birth-certificates/conflict-cert.pdf',
        ]);

        Storage::disk('local')->put('birth-certificates/conflict-cert.pdf', 'private-version-A');
        Storage::disk('public')->put('birth-certificates/conflict-cert.pdf', 'public-version-B');

        $this->artisan('beneficiaries:migrate-certificates-private', ['--apply' => true, '--cleanup-public' => true, '--json' => true])
            ->assertExitCode(0);

        // Both files remain intact due to conflict
        Storage::disk('local')->assertExists('birth-certificates/conflict-cert.pdf');
        Storage::disk('public')->assertExists('birth-certificates/conflict-cert.pdf');
        $this->assertSame('private-version-A', Storage::disk('local')->get('birth-certificates/conflict-cert.pdf'));
    }

    public function test_path_traversal_attempts_are_blocked_defensively(): void
    {
        $orphan = $this->createOrphan([
            'birth_certificate_path' => '../etc/passwd',
        ]);

        $this->artisan('beneficiaries:migrate-certificates-private', ['--apply' => true, '--json' => true])
            ->expectsOutputToContain('"conflicts": 1')
            ->assertExitCode(0);
    }

    public function test_migrated_private_certificate_remains_accessible_via_controller(): void
    {
        $admin = User::factory()->create(['status' => UserStatus::ACTIVE]);
        $admin->assignRole('super_admin');

        $orphan = $this->createOrphan([
            'birth_certificate_path' => 'birth-certificates/controller-test.pdf',
        ]);

        Storage::disk('public')->put('birth-certificates/controller-test.pdf', 'controller-test-binary');

        // Run migration
        $this->artisan('beneficiaries:migrate-certificates-private', ['--apply' => true, '--cleanup-public' => true])
            ->assertExitCode(0);

        // Verify controller access via private disk
        $response = $this->actingAs($admin)->get(route('orphans.birth-certificate.preview', ['orphan' => $orphan]));

        $response->assertOk();
        $this->assertSame('controller-test-binary', $response->streamedContent());
    }
}
