<?php

use App\Models\Deceased;
use App\Models\Orphan;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    Storage::fake('local');
    Storage::fake('public');

    $this->superAdmin = User::factory()->create([
        'status' => \App\Enums\UserStatus::ACTIVE,
    ]);
    $this->superAdmin->assignRole('super_admin');

    $this->coordinatorA = User::factory()->create([
        'status' => \App\Enums\UserStatus::ACTIVE,
    ]);
    $this->coordinatorA->assignRole('coordinator');

    $this->zoneA = Zone::create([
        'name' => 'Zone Alpha',
        'address' => '100 Alpha St',
        'coordinator_id' => $this->coordinatorA->id,
    ]);
    $this->coordinatorA->unsetRelation('coordinatedZone');

    $this->zoneB = Zone::create([
        'name' => 'Zone Beta',
        'address' => '200 Beta St',
    ]);

    $this->deceasedA = Deceased::factory()->create([
        'zone_id' => $this->zoneA->id,
        'full_name' => 'Alpha Deceased',
        'has_death_cert' => true,
        'death_cert_url' => 'death-certs/cert_alpha.pdf',
    ]);

    $this->deceasedB = Deceased::factory()->create([
        'zone_id' => $this->zoneB->id,
        'full_name' => 'Beta Deceased',
        'has_death_cert' => true,
        'death_cert_url' => 'death-certs/cert_beta.pdf',
    ]);

    $this->orphanA = Orphan::create([
        'deceased_id' => $this->deceasedA->id,
        'reg_no' => 'ORP-Z-A',
        'first_name' => 'OrphanA',
        'last_name' => 'Alpha',
        'gender' => \App\Enums\Gender::MALE,
        'date_of_birth' => '2015-01-01',
        'child_sequence' => 1,
        'status' => \App\Enums\OrphanStatus::ACTIVE,
        'is_eligible' => true,
        'has_birth_cert' => true,
        'birth_certificate_path' => 'birth-certificates/birth_alpha.pdf',
    ]);

    $this->orphanB = Orphan::create([
        'deceased_id' => $this->deceasedB->id,
        'reg_no' => 'ORP-Z-B',
        'first_name' => 'OrphanB',
        'last_name' => 'Beta',
        'gender' => \App\Enums\Gender::FEMALE,
        'date_of_birth' => '2016-01-01',
        'child_sequence' => 1,
        'status' => \App\Enums\OrphanStatus::ACTIVE,
        'is_eligible' => true,
        'has_birth_cert' => true,
        'birth_certificate_path' => 'birth-certificates/birth_beta.pdf',
    ]);

    // Store test files in private local disk
    Storage::disk('local')->put('death-certs/cert_alpha.pdf', 'PDF-DEATH-CERT-ALPHA-CONTENT');
    Storage::disk('local')->put('death-certs/cert_beta.pdf', 'PDF-DEATH-CERT-BETA-CONTENT');
    Storage::disk('local')->put('birth-certificates/birth_alpha.pdf', 'PDF-BIRTH-CERT-ALPHA-CONTENT');
    Storage::disk('local')->put('birth-certificates/birth_beta.pdf', 'PDF-BIRTH-CERT-BETA-CONTENT');
});

test('authorized user can preview existing death certificate with inline disposition', function () {
    $response = $this->actingAs($this->superAdmin)
        ->get(route('deceased.death-certificate.preview', ['deceased' => $this->deceasedA]));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
    expect($response->headers->get('Content-Disposition'))->toContain('inline');
});

test('authorized user can download existing death certificate with attachment disposition', function () {
    $response = $this->actingAs($this->superAdmin)
        ->get(route('deceased.death-certificate.download', ['deceased' => $this->deceasedA]));

    $response->assertOk();
    expect($response->headers->get('Content-Disposition'))->toContain('attachment');
});

test('absent death certificate file on disk returns safe 404', function () {
    $deceasedNoFile = Deceased::factory()->create([
        'zone_id' => $this->zoneA->id,
        'has_death_cert' => true,
        'death_cert_url' => 'death-certs/missing.pdf',
    ]);

    $response = $this->actingAs($this->superAdmin)
        ->get(route('deceased.death-certificate.preview', ['deceased' => $deceasedNoFile]));

    $response->assertNotFound();
});

test('unauthorized user is denied access to death certificate', function () {
    $unauthorizedUser = User::factory()->create(['status' => \App\Enums\UserStatus::ACTIVE]);

    $response = $this->actingAs($unauthorizedUser)
        ->get(route('deceased.death-certificate.preview', ['deceased' => $this->deceasedA]));

    expect(in_array($response->status(), [403, 404]))->toBeTrue();
});

test('authorized user can preview existing birth certificate with inline disposition', function () {
    $response = $this->actingAs($this->superAdmin)
        ->get(route('orphans.birth-certificate.preview', ['orphan' => $this->orphanA]));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
    expect($response->headers->get('Content-Disposition'))->toContain('inline');
});

test('authorized user can download existing birth certificate with attachment disposition', function () {
    $response = $this->actingAs($this->superAdmin)
        ->get(route('orphans.birth-certificate.download', ['orphan' => $this->orphanA]));

    $response->assertOk();
    expect($response->headers->get('Content-Disposition'))->toContain('attachment');
});

test('birth certificate stored on public storage disk fallback is accessible', function () {
    $deceasedPublic = Deceased::factory()->create(['zone_id' => $this->zoneA->id]);
    $orphanPublic = Orphan::create([
        'deceased_id' => $deceasedPublic->id,
        'reg_no' => 'ORP-PUBLIC-DISK',
        'first_name' => 'Public',
        'last_name' => 'Orphan',
        'gender' => \App\Enums\Gender::MALE,
        'date_of_birth' => '2015-01-01',
        'child_sequence' => 1,
        'status' => \App\Enums\OrphanStatus::ACTIVE,
        'is_eligible' => true,
        'has_birth_cert' => true,
        'birth_certificate_path' => 'birth-certificates/public_cert.pdf',
    ]);

    Storage::disk('public')->put('birth-certificates/public_cert.pdf', 'PUBLIC-DISK-CONTENT');

    $response = $this->actingAs($this->superAdmin)
        ->get(route('orphans.birth-certificate.preview', ['orphan' => $orphanPublic]));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
});

test('absent birth certificate file on disk returns safe 404', function () {
    $deceasedC = Deceased::factory()->create(['zone_id' => $this->zoneA->id]);
    $orphanNoFile = Orphan::create([
        'deceased_id' => $deceasedC->id,
        'reg_no' => 'ORP-NO-FILE',
        'first_name' => 'NoFile',
        'last_name' => 'Orphan',
        'gender' => \App\Enums\Gender::MALE,
        'date_of_birth' => '2015-01-01',
        'child_sequence' => 1,
        'status' => \App\Enums\OrphanStatus::ACTIVE,
        'is_eligible' => true,
        'has_birth_cert' => true,
        'birth_certificate_path' => 'birth-certificates/missing.pdf',
    ]);

    $response = $this->actingAs($this->superAdmin)
        ->get(route('orphans.birth-certificate.preview', ['orphan' => $orphanNoFile]));

    $response->assertNotFound();
});

test('unauthorized user is denied access to birth certificate', function () {
    $unauthorizedUser = User::factory()->create(['status' => \App\Enums\UserStatus::ACTIVE]);

    $response = $this->actingAs($unauthorizedUser)
        ->get(route('orphans.birth-certificate.preview', ['orphan' => $this->orphanA]));

    expect(in_array($response->status(), [403, 404]))->toBeTrue();
});

test('coordinator can access death cert preview in assigned zone but is denied for out of zone record', function () {
    // In-zone
    $responseInZone = $this->actingAs($this->coordinatorA)
        ->get(route('deceased.death-certificate.preview', ['deceased' => $this->deceasedA]));
    $responseInZone->assertOk();

    // Out-of-zone
    $responseOutZone = $this->actingAs($this->coordinatorA)
        ->get(route('deceased.death-certificate.preview', ['deceased' => $this->deceasedB]));
    expect(in_array($responseOutZone->status(), [403, 404]))->toBeTrue();
});

test('coordinator can access birth cert preview in assigned zone but is denied for out of zone record', function () {
    // In-zone
    $responseInZone = $this->actingAs($this->coordinatorA)
        ->get(route('orphans.birth-certificate.preview', ['orphan' => $this->orphanA]));
    $responseInZone->assertOk();

    // Out-of-zone
    $responseOutZone = $this->actingAs($this->coordinatorA)
        ->get(route('orphans.birth-certificate.preview', ['orphan' => $this->orphanB]));
    expect(in_array($responseOutZone->status(), [403, 404]))->toBeTrue();
});

test('demo observer is denied downloading sensitive certificate data', function () {
    $demoObserver = User::factory()->create(['status' => \App\Enums\UserStatus::ACTIVE]);
    $demoObserver->assignRole('demo_observer');

    $response = $this->actingAs($demoObserver)
        ->get(route('deceased.death-certificate.download', ['deceased' => $this->deceasedA]));

    expect(in_array($response->status(), [403, 404]))->toBeTrue();
});

test('unauthenticated user is redirected to login', function () {
    $response = $this->get(route('deceased.death-certificate.preview', ['deceased' => $this->deceasedA]));
    $response->assertRedirect('/admin/login');
});

test('Admin ViewOrphan page renders preview and download header actions when birth certificate exists on disk', function () {
    Livewire::actingAs($this->superAdmin)
        ->test(\App\Filament\Resources\Orphans\Pages\ViewOrphan::class, ['record' => $this->orphanA->id])
        ->assertActionExists('previewBirthCert')
        ->assertActionExists('downloadBirthCert');
});

test('Coordinator ViewOrphan page renders preview and download header actions when birth certificate exists on disk', function () {
    Livewire::actingAs($this->coordinatorA)
        ->test(\App\Filament\Coordinator\Resources\OrphanResource\Pages\ViewOrphan::class, ['record' => $this->orphanA->id])
        ->assertActionExists('previewBirthCert')
        ->assertActionExists('downloadBirthCert');
});

test('downloading orphan birth certificate with slash-containing registration number generates sanitized filename', function () {
    $orphanSlash = Orphan::create([
        'deceased_id' => $this->deceasedA->id,
        'reg_no' => 'GOF/2026/0037/01',
        'first_name' => 'Slash',
        'last_name' => 'Orphan',
        'gender' => \App\Enums\Gender::MALE,
        'date_of_birth' => '2015-01-01',
        'child_sequence' => 2,
        'status' => \App\Enums\OrphanStatus::ACTIVE,
        'is_eligible' => true,
        'has_birth_cert' => true,
        'birth_certificate_path' => 'birth-certificates/slash_cert.pdf',
    ]);

    Storage::disk('local')->put('birth-certificates/slash_cert.pdf', 'PDF-SLASH-CONTENT');

    $response = $this->actingAs($this->superAdmin)
        ->get(route('orphans.birth-certificate.download', ['orphan' => $orphanSlash]));

    $response->assertOk();
    $disposition = $response->headers->get('Content-Disposition');
    expect($disposition)->toContain('attachment');
    expect($disposition)->toContain('filename=Birth_Certificate_GOF-2026-0037-01.pdf');
    expect($disposition)->not->toContain('/');
    expect($disposition)->not->toContain('\\');
});

test('downloading deceased death certificate with slash-containing registration number generates sanitized filename', function () {
    $deceasedSlash = Deceased::factory()->create([
        'zone_id' => $this->zoneA->id,
        'reg_no' => 'GOF/2026/0012/DEC',
        'has_death_cert' => true,
        'death_cert_url' => 'death-certs/slash_dec.pdf',
    ]);

    Storage::disk('local')->put('death-certs/slash_dec.pdf', 'PDF-SLASH-DEC-CONTENT');

    $response = $this->actingAs($this->superAdmin)
        ->get(route('deceased.death-certificate.download', ['deceased' => $deceasedSlash]));

    $response->assertOk();
    $disposition = $response->headers->get('Content-Disposition');
    expect($disposition)->toContain('attachment');
    expect($disposition)->toContain('filename=Death_Certificate_GOF-2026-0012-DEC.pdf');
    expect($disposition)->not->toContain('/');
    expect($disposition)->not->toContain('\\');
});

test('filename sanitization replaces backslashes defensively', function () {
    $deceasedBackslash = Deceased::factory()->create([
        'zone_id' => $this->zoneA->id,
        'reg_no' => 'GOF\\2026\\0099\\DEC',
        'has_death_cert' => true,
        'death_cert_url' => 'death-certs/backslash_dec.pdf',
    ]);

    Storage::disk('local')->put('death-certs/backslash_dec.pdf', 'PDF-BACKSLASH-DEC-CONTENT');

    $response = $this->actingAs($this->superAdmin)
        ->get(route('deceased.death-certificate.download', ['deceased' => $deceasedBackslash]));

    $response->assertOk();
    $disposition = $response->headers->get('Content-Disposition');
    expect($disposition)->not->toContain('/');
    expect($disposition)->not->toContain('\\');
});

test('previewing certificate with slash-containing registration number remains inline with sanitized filename', function () {
    $orphanSlash = Orphan::create([
        'deceased_id' => $this->deceasedA->id,
        'reg_no' => 'GOF/2026/0037/01',
        'first_name' => 'SlashInline',
        'last_name' => 'Orphan',
        'gender' => \App\Enums\Gender::MALE,
        'date_of_birth' => '2015-01-01',
        'child_sequence' => 3,
        'status' => \App\Enums\OrphanStatus::ACTIVE,
        'is_eligible' => true,
        'has_birth_cert' => true,
        'birth_certificate_path' => 'birth-certificates/slash_inline.pdf',
    ]);

    Storage::disk('local')->put('birth-certificates/slash_inline.pdf', 'PDF-SLASH-INLINE-CONTENT');

    $response = $this->actingAs($this->superAdmin)
        ->get(route('orphans.birth-certificate.preview', ['orphan' => $orphanSlash]));

    $response->assertOk();
    $disposition = $response->headers->get('Content-Disposition');
    expect($disposition)->toContain('inline');
    expect($disposition)->toContain('filename="Birth_Certificate_GOF-2026-0037-01.pdf"');
});

test('download filename sanitization leaves database reg_no and stored file path unchanged', function () {
    $orphan = Orphan::create([
        'deceased_id' => $this->deceasedA->id,
        'reg_no' => 'GOF/2026/0037/01',
        'first_name' => 'Unchanged',
        'last_name' => 'Orphan',
        'gender' => \App\Enums\Gender::MALE,
        'date_of_birth' => '2015-01-01',
        'child_sequence' => 4,
        'status' => \App\Enums\OrphanStatus::ACTIVE,
        'is_eligible' => true,
        'has_birth_cert' => true,
        'birth_certificate_path' => 'birth-certificates/unchanged_cert.pdf',
    ]);

    Storage::disk('local')->put('birth-certificates/unchanged_cert.pdf', 'PDF-UNCHANGED-CONTENT');

    $this->actingAs($this->superAdmin)
        ->get(route('orphans.birth-certificate.download', ['orphan' => $orphan]));

    $orphan->refresh();
    expect($orphan->reg_no)->toBe('GOF/2026/0037/01');
    expect($orphan->birth_certificate_path)->toBe('birth-certificates/unchanged_cert.pdf');
    expect(Storage::disk('local')->exists('birth-certificates/unchanged_cert.pdf'))->toBeTrue();
});
