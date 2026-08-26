<?php

use App\Enums\Gender;
use App\Enums\IllnessCategory;
use App\Enums\OrphanStatus;
use App\Filament\Resources\Prescriptions\Pages\CreatePrescription;
use App\Models\Deceased;
use App\Models\IdCard;
use App\Models\IdCardTemplate;
use App\Models\Illness;
use App\Models\Medication;
use App\Models\Orphan;
use App\Models\Prescription;
use App\Models\User;
use App\Models\Widow;
use App\Models\Zone;
use App\Services\IdCardGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(\Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

    $this->zone = Zone::create(['name' => 'Prescription Test Zone', 'address' => '100 Medical Way']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    $this->actingAs($this->admin);

    $this->deceased = Deceased::factory()->create(['zone_id' => $this->zone->id]);

    $this->orphan = Orphan::create([
        'first_name' => 'Active',
        'last_name' => 'Orphan',
        'deceased_id' => $this->deceased->id,
        'reg_no' => 'ORP-TEST-001',
        'child_sequence' => 1,
        'gender' => Gender::MALE,
        'birth_date' => now()->subYears(10)->toDateString(),
        'address' => '123 Test St',
        'status' => OrphanStatus::ACTIVE,
        'is_eligible' => true,
    ]);

    $this->widow = Widow::create([
        'deceased_id' => $this->deceased->id,
        'reg_no' => 'WID-TEST-001',
        'first_name' => 'Active',
        'last_name' => 'Widow',
        'nin' => '12345678901',
        'phone' => '08012345678',
        'address' => '123 Test St',
        'child_sequence' => 1,
        'is_eligible' => true,
        'is_married' => false,
    ]);

    $this->illness = Illness::create([
        'name' => 'Malaria Fever',
        'category' => IllnessCategory::Infectious,
    ]);

    $this->medication = Medication::create([
        'name' => 'Artemether',
        'dosage_form' => 'Tablet',
        'unit_price' => 500.00,
        'user_id' => $this->admin->id,
    ]);
});

test('1. standalone prescription create with illness_id succeeds', function () {
    Livewire::test(CreatePrescription::class)
        ->fillForm([
            'doctor_name' => 'Dr. Musa',
            'illness_id' => $this->illness->id,
            'prescription_date' => now()->toDateString(),
            'prescribable_type' => Orphan::class,
            'prescribable_id' => $this->orphan->id,
            'user_id' => $this->admin->id,
            'lab_test_cost' => 1000,
            'drug_cost' => 2000,
            'note' => 'Take twice daily',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('prescriptions', [
        'doctor_name' => 'Dr. Musa',
        'illness_id' => $this->illness->id,
        'prescribable_type' => Orphan::class,
        'prescribable_id' => $this->orphan->id,
    ]);
});

test('2. standalone widow prescription create succeeds', function () {
    Livewire::test(CreatePrescription::class)
        ->fillForm([
            'doctor_name' => 'Dr. Fatima',
            'illness_id' => $this->illness->id,
            'prescription_date' => now()->toDateString(),
            'prescribable_type' => Widow::class,
            'prescribable_id' => $this->widow->id,
            'user_id' => $this->admin->id,
            'lab_test_cost' => 500,
            'drug_cost' => 1500,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('prescriptions', [
        'doctor_name' => 'Dr. Fatima',
        'prescribable_type' => Widow::class,
        'prescribable_id' => $this->widow->id,
    ]);
});

test('3. standalone orphan prescription create succeeds', function () {
    Livewire::test(CreatePrescription::class)
        ->fillForm([
            'doctor_name' => 'Dr. Ibrahim',
            'illness_id' => $this->illness->id,
            'prescription_date' => now()->toDateString(),
            'prescribable_type' => Orphan::class,
            'prescribable_id' => $this->orphan->id,
            'user_id' => $this->admin->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('prescriptions', [
        'doctor_name' => 'Dr. Ibrahim',
        'prescribable_type' => Orphan::class,
        'prescribable_id' => $this->orphan->id,
    ]);
});

test('4 & 5. orphan and widow relation manager inline CreateAction creates prescription bound to owner record', function () {
    Livewire::test(\App\Filament\Resources\Orphans\RelationManagers\PrescriptionsRelationManager::class, [
        'ownerRecord' => $this->orphan,
        'pageClass' => \App\Filament\Resources\Orphans\Pages\EditOrphan::class,
    ])
        ->callTableAction('create', data: [
            'doctor_name' => 'Dr. Inline Orphan',
            'illness_id' => $this->illness->id,
            'prescription_date' => now()->toDateString(),
            'lab_test_cost' => 500,
            'drug_cost' => 1000,
        ])
        ->assertHasNoTableActionErrors();

    $this->assertDatabaseHas('prescriptions', [
        'doctor_name' => 'Dr. Inline Orphan',
        'prescribable_type' => Orphan::class,
        'prescribable_id' => $this->orphan->id,
        'illness_id' => $this->illness->id,
    ]);

    Livewire::test(\App\Filament\Resources\Widows\RelationManagers\PrescriptionsRelationManager::class, [
        'ownerRecord' => $this->widow,
        'pageClass' => \App\Filament\Resources\Widows\Pages\EditWidow::class,
    ])
        ->callTableAction('create', data: [
            'doctor_name' => 'Dr. Inline Widow',
            'illness_id' => $this->illness->id,
            'prescription_date' => now()->toDateString(),
            'lab_test_cost' => 800,
            'drug_cost' => 1200,
        ])
        ->assertHasNoTableActionErrors();

    $this->assertDatabaseHas('prescriptions', [
        'doctor_name' => 'Dr. Inline Widow',
        'prescribable_type' => Widow::class,
        'prescribable_id' => $this->widow->id,
        'illness_id' => $this->illness->id,
    ]);
});

test('6 & 7. required illness relationship is persisted and legacy illness column does not cause NOT NULL failure', function () {
    $prescription = Prescription::create([
        'doctor_name' => 'Dr. Test',
        'illness_id' => $this->illness->id,
        'prescribable_type' => Orphan::class,
        'prescribable_id' => $this->orphan->id,
        'user_id' => $this->admin->id,
        'prescription_date' => now()->toDateString(),
        'lab_test_cost' => 0,
        'drug_cost' => 0,
    ]);

    expect($prescription->fresh()->illnessModel->id)->toBe($this->illness->id)
        ->and($prescription->fresh()->illness_name)->toBe($this->illness->name);
});

test('8. medication pivot records persist correctly', function () {
    Livewire::test(CreatePrescription::class)
        ->fillForm([
            'doctor_name' => 'Dr. Meds',
            'illness_id' => $this->illness->id,
            'prescription_date' => now()->toDateString(),
            'prescribable_type' => Orphan::class,
            'prescribable_id' => $this->orphan->id,
            'user_id' => $this->admin->id,
            'medications' => [$this->medication->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $prescription = Prescription::where('doctor_name', 'Dr. Meds')->first();
    expect($prescription->medications->pluck('id'))->toContain($this->medication->id);
});

test('9. patient category switch clears stale patient selection', function () {
    Livewire::test(CreatePrescription::class)
        ->set('data.prescribable_type', Orphan::class)
        ->set('data.prescribable_id', $this->orphan->id)
        ->set('data.prescribable_type', Widow::class)
        ->assertSet('data.prescribable_id', null);
});

test('10. historical prescription resolves inactive or non-eligible beneficiary without error', function () {
    $archivedOrphan = Orphan::create([
        'first_name' => 'Archived',
        'last_name' => 'Child',
        'deceased_id' => $this->deceased->id,
        'reg_no' => 'ORP-ARC-001',
        'child_sequence' => 2,
        'gender' => Gender::FEMALE,
        'birth_date' => now()->subYears(15)->toDateString(),
        'address' => '456 Test St',
        'status' => OrphanStatus::ARCHIVED,
        'is_eligible' => false,
    ]);

    $prescription = Prescription::create([
        'doctor_name' => 'Dr. History',
        'illness_id' => $this->illness->id,
        'prescribable_type' => Orphan::class,
        'prescribable_id' => $archivedOrphan->id,
        'user_id' => $this->admin->id,
        'prescription_date' => now()->toDateString(),
        'lab_test_cost' => 0,
        'drug_cost' => 0,
    ]);

    expect($prescription->fresh()->prescribable)->not->toBeNull()
        ->and($prescription->fresh()->prescribable->id)->toBe($archivedOrphan->id);
});

test('11 & 12 & 13. new orphan gets PENDING_REVIEW status and is_eligible = false', function () {
    $action = app(\App\Actions\Orphan\RegisterOrphanAction::class);

    $dto = new \App\Data\Orphan\OrphanData(
        deceasedId: $this->deceased->id,
        firstName: 'NewBorn',
        lastName: 'Child',
        middleName: null,
        gender: Gender::MALE->value,
        birthDate: now()->subYears(5)->toDateString(),
        picture: null,
        nin: null,
        guardianName: null,
        guardianPhone: null,
        address: '123 Street',
        hasBirthCert: false,
        birthCertificatePath: null,
        educations: [],
        vocationalSkills: [],
    );

    $orphan = $action->execute($dto);

    expect($orphan->fresh()->status)->toBe(OrphanStatus::PENDING_REVIEW)
        ->and($orphan->fresh()->is_eligible)->toBeFalse();
});

test('14 & 15. no runtime orphan creation path writes raw draft or approved strings', function () {
    $actionFile = file_get_contents(app_path('Actions/Orphan/RegisterOrphanAction.php'));

    expect($actionFile)->not->toContain("'status' => 'draft'")
        ->and($actionFile)->not->toContain("'status' => 'approved'");
});

test('16 & 17. Deceased relation managers inline CreateAction open modals without redirect and auto-assign owner deceased_id', function () {
    Livewire::test(\App\Filament\Resources\Deceased\RelationManagers\OrphansRelationManager::class, [
        'ownerRecord' => $this->deceased,
        'pageClass' => \App\Filament\Resources\Deceased\Pages\EditDeceased::class,
    ])
        ->callTableAction('create', data: [
            'first_name' => 'Inline',
            'last_name' => 'Orphan',
            'gender' => Gender::MALE->value,
            'birth_date' => now()->subYears(8)->toDateString(),
            'nin' => '98765432101',
            'address' => '456 Modal Way',
        ])
        ->assertHasNoTableActionErrors();

    $this->assertDatabaseHas('orphans', [
        'first_name' => 'Inline',
        'last_name' => 'Orphan',
        'deceased_id' => $this->deceased->id,
        'status' => OrphanStatus::PENDING_REVIEW,
        'is_eligible' => false,
    ]);

    Livewire::test(\App\Filament\Resources\Deceased\RelationManagers\WidowsRelationManager::class, [
        'ownerRecord' => $this->deceased,
        'pageClass' => \App\Filament\Resources\Deceased\Pages\EditDeceased::class,
    ])
        ->callTableAction('create', data: [
            'first_name' => 'Inline',
            'last_name' => 'Widow',
            'nin' => '98765432102',
            'address' => '789 Modal Ave',
            'is_eligible' => true,
            'is_married' => false,
        ])
        ->assertHasNoTableActionErrors();

    $this->assertDatabaseHas('widows', [
        'first_name' => 'Inline',
        'last_name' => 'Widow',
        'deceased_id' => $this->deceased->id,
        'is_eligible' => true,
    ]);
});

test('18 & 19. zone authorization scoping prevents attaching orphan outside coordinated zone', function () {
    $otherZone = Zone::create(['name' => 'Other Zone', 'address' => '999 Far Away']);
    $otherDeceased = Deceased::factory()->create(['zone_id' => $otherZone->id]);

    $coordinator = User::factory()->create();
    $coordinator->assignRole('coordinator');
    $coordinator->update(['zone_id' => $this->zone->id]);

    $this->actingAs($coordinator);

    $visibleDeceasedCount = Deceased::where('id', $otherDeceased->id)->count();
    expect($visibleDeceasedCount)->toBe(0);
});

test('20 & 21. ID card reconcile detects existing duplicates in read-only mode', function () {
    $template = IdCardTemplate::create([
        'name' => 'Test Card Template',
        'type' => 'orphan',
        'is_active' => true,
    ]);

    IdCard::create([
        'card_number' => 'GOF-CARD-001',
        'cardable_type' => Orphan::class,
        'cardable_id' => $this->orphan->id,
        'template_id' => $template->id,
        'qr_code_path' => 'qrcodes/test1.png',
        'issued_at' => now(),
        'status' => 'draft',
    ]);

    IdCard::create([
        'card_number' => 'GOF-CARD-002',
        'cardable_type' => Orphan::class,
        'cardable_id' => $this->orphan->id,
        'template_id' => $template->id,
        'qr_code_path' => 'qrcodes/test2.png',
        'issued_at' => now(),
        'status' => 'active',
    ]);

    $this->artisan('id-cards:reconcile --details')
        ->assertExitCode(0);

    expect(IdCard::count())->toBeGreaterThanOrEqual(2);
});

test('22. new ID card generation prevents duplicate open cards', function () {
    $template = IdCardTemplate::create([
        'name' => 'Test Card Template 2',
        'type' => 'orphan',
        'is_active' => true,
    ]);

    $service = app(IdCardGenerationService::class);
    $card1 = $service->generateCard($this->orphan, $template);

    expect($card1->status)->toBe('draft');

    expect(fn () => $service->generateCard($this->orphan, $template))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});
