<?php

use App\Enums\Gender;
use App\Enums\IllnessCategory;
use App\Enums\OrphanStatus;
use App\Filament\Coordinator\Resources\HealthcareRequestResource\Pages\CreateHealthcareRequest;
use App\Filament\Coordinator\Resources\HealthcareRequestResource\Pages\EditHealthcareRequest;
use App\Filament\Coordinator\Resources\HealthcareRequestResource\Pages\ViewHealthcareRequest;
use App\Models\Deceased;
use App\Models\Illness;
use App\Models\Orphan;
use App\Models\Prescription;
use App\Models\User;
use App\Models\Widow;
use App\Models\Zone;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('coordinator'));

    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

    $this->coordinator = User::factory()->create();
    $this->coordinator->assignRole('coordinator');

    $this->otherCoordinator = User::factory()->create();
    $this->otherCoordinator->assignRole('coordinator');

    $this->zone = Zone::create(['name' => 'Zone North', 'coordinator_id' => $this->coordinator->id]);
    $this->otherZone = Zone::create(['name' => 'Zone South', 'coordinator_id' => $this->otherCoordinator->id]);

    $this->deceased = Deceased::factory()->create(['zone_id' => $this->zone->id]);
    $this->otherDeceased = Deceased::factory()->create(['zone_id' => $this->otherZone->id]);

    $this->orphan = Orphan::create([
        'first_name' => 'Ali',
        'last_name' => 'Bello',
        'deceased_id' => $this->deceased->id,
        'reg_no' => 'ORP-ZN-001',
        'child_sequence' => 1,
        'gender' => Gender::MALE,
        'birth_date' => now()->subYears(10)->toDateString(),
        'address' => '123 Zone St',
        'status' => OrphanStatus::ACTIVE,
        'is_eligible' => true,
    ]);

    $this->otherOrphan = Orphan::create([
        'first_name' => 'Kano',
        'last_name' => 'Child',
        'deceased_id' => $this->otherDeceased->id,
        'reg_no' => 'ORP-ZS-001',
        'child_sequence' => 1,
        'gender' => Gender::MALE,
        'birth_date' => now()->subYears(8)->toDateString(),
        'address' => '456 Far St',
        'status' => OrphanStatus::ACTIVE,
        'is_eligible' => true,
    ]);

    $this->widow = Widow::create([
        'deceased_id' => $this->deceased->id,
        'reg_no' => 'WID-ZN-001',
        'first_name' => 'Amina',
        'last_name' => 'Bello',
        'nin' => '12345678901',
        'phone' => '08012345678',
        'address' => '123 Zone St',
        'child_sequence' => 1,
        'is_eligible' => true,
        'is_married' => false,
    ]);

    $this->otherWidow = Widow::create([
        'deceased_id' => $this->otherDeceased->id,
        'reg_no' => 'WID-ZS-001',
        'first_name' => 'Halima',
        'last_name' => 'South',
        'nin' => '98765432109',
        'phone' => '08098765432',
        'address' => '456 Far St',
        'child_sequence' => 1,
        'is_eligible' => true,
        'is_married' => false,
    ]);

    $this->illness = Illness::create([
        'name' => 'Typhoid Fever',
        'category' => IllnessCategory::Infectious,
    ]);

    $this->actingAs($this->coordinator);
});

test('1. create page renders without error', function () {
    Livewire::test(CreateHealthcareRequest::class)
        ->assertSuccessful();
});

test('2. valid Orphan request saves', function () {
    Livewire::test(CreateHealthcareRequest::class)
        ->fillForm([
            'prescribable_type' => Orphan::class,
            'prescribable_id' => $this->orphan->id,
            'doctor_name' => 'Dr. Sani',
            'illness_id' => $this->illness->id,
            'prescription_date' => now()->toDateString(),
            'lab_test_cost' => 1500,
            'drug_cost' => 3500,
            'note' => 'Full recovery course',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('prescriptions', [
        'doctor_name' => 'Dr. Sani',
        'prescribable_type' => Orphan::class,
        'prescribable_id' => $this->orphan->id,
        'illness_id' => $this->illness->id,
    ]);
});

test('3. valid Widow request saves', function () {
    Livewire::test(CreateHealthcareRequest::class)
        ->fillForm([
            'prescribable_type' => Widow::class,
            'prescribable_id' => $this->widow->id,
            'doctor_name' => 'Dr. Zainab',
            'illness_id' => $this->illness->id,
            'prescription_date' => now()->toDateString(),
            'lab_test_cost' => 2000,
            'drug_cost' => 5000,
            'note' => 'Widow healthcare support',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('prescriptions', [
        'doctor_name' => 'Dr. Zainab',
        'prescribable_type' => Widow::class,
        'prescribable_id' => $this->widow->id,
        'illness_id' => $this->illness->id,
    ]);
});

test('4. patient type persists correctly', function () {
    Livewire::test(CreateHealthcareRequest::class)
        ->fillForm([
            'prescribable_type' => Widow::class,
            'prescribable_id' => $this->widow->id,
            'doctor_name' => 'Dr. TypeTest',
            'illness_id' => $this->illness->id,
            'prescription_date' => now()->toDateString(),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $prescription = Prescription::where('doctor_name', 'Dr. TypeTest')->first();
    expect($prescription->prescribable_type)->toBe(Widow::class);
});

test('5. patient ID persists correctly', function () {
    Livewire::test(CreateHealthcareRequest::class)
        ->fillForm([
            'prescribable_type' => Orphan::class,
            'prescribable_id' => $this->orphan->id,
            'doctor_name' => 'Dr. IdTest',
            'illness_id' => $this->illness->id,
            'prescription_date' => now()->toDateString(),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $prescription = Prescription::where('doctor_name', 'Dr. IdTest')->first();
    expect($prescription->prescribable_id)->toBe($this->orphan->id);
});

test('6. illness_id persists correctly', function () {
    Livewire::test(CreateHealthcareRequest::class)
        ->fillForm([
            'prescribable_type' => Orphan::class,
            'prescribable_id' => $this->orphan->id,
            'doctor_name' => 'Dr. IllnessTest',
            'illness_id' => $this->illness->id,
            'prescription_date' => now()->toDateString(),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $prescription = Prescription::where('doctor_name', 'Dr. IllnessTest')->first();
    expect($prescription->illness_id)->toBe($this->illness->id);
});

test('7. switching patient type clears stale patient selection', function () {
    Livewire::test(CreateHealthcareRequest::class)
        ->set('data.prescribable_type', Orphan::class)
        ->set('data.prescribable_id', $this->orphan->id)
        ->set('data.prescribable_type', Widow::class)
        ->assertSet('data.prescribable_id', null);
});

test('8. missing required patient type gives validation, not 500', function () {
    Livewire::test(CreateHealthcareRequest::class)
        ->fillForm([
            'prescribable_type' => null,
            'prescribable_id' => $this->orphan->id,
            'doctor_name' => 'Dr. MissingType',
            'illness_id' => $this->illness->id,
            'prescription_date' => now()->toDateString(),
        ])
        ->call('create')
        ->assertHasFormErrors(['prescribable_type' => 'required']);
});

test('9. coordinator cannot create request for another zone orphan', function () {
    Livewire::test(CreateHealthcareRequest::class)
        ->fillForm([
            'prescribable_type' => Orphan::class,
            'prescribable_id' => $this->otherOrphan->id,
            'doctor_name' => 'Dr. CrossZoneOrphan',
            'illness_id' => $this->illness->id,
            'prescription_date' => now()->toDateString(),
        ])
        ->call('create')
        ->assertHasFormErrors(['prescribable_id']);

    $this->assertDatabaseMissing('prescriptions', [
        'doctor_name' => 'Dr. CrossZoneOrphan',
    ]);
});

test('10. coordinator cannot create request for another zone widow', function () {
    Livewire::test(CreateHealthcareRequest::class)
        ->fillForm([
            'prescribable_type' => Widow::class,
            'prescribable_id' => $this->otherWidow->id,
            'doctor_name' => 'Dr. CrossZoneWidow',
            'illness_id' => $this->illness->id,
            'prescription_date' => now()->toDateString(),
        ])
        ->call('create')
        ->assertHasFormErrors(['prescribable_id']);

    $this->assertDatabaseMissing('prescriptions', [
        'doctor_name' => 'Dr. CrossZoneWidow',
    ]);
});

test('11. forged inaccessible beneficiary ID is rejected with validation error', function () {
    $forgedUuid = (string) \Illuminate\Support\Str::uuid();

    Livewire::test(CreateHealthcareRequest::class)
        ->fillForm([
            'prescribable_type' => Orphan::class,
            'prescribable_id' => $forgedUuid,
            'doctor_name' => 'Dr. ForgedId',
            'illness_id' => $this->illness->id,
            'prescription_date' => now()->toDateString(),
        ])
        ->call('create')
        ->assertHasFormErrors(['prescribable_id']);
});

test('12. existing records still edit and view successfully', function () {
    $prescription = Prescription::create([
        'doctor_name' => 'Dr. ViewTest',
        'illness_id' => $this->illness->id,
        'prescribable_type' => Orphan::class,
        'prescribable_id' => $this->orphan->id,
        'user_id' => $this->coordinator->id,
        'prescription_date' => now()->toDateString(),
        'lab_test_cost' => 1000,
        'drug_cost' => 2000,
    ]);

    Livewire::test(ViewHealthcareRequest::class, ['record' => $prescription->getRouteKey()])
        ->assertSuccessful();

    Livewire::test(EditHealthcareRequest::class, ['record' => $prescription->getRouteKey()])
        ->assertSuccessful()
        ->fillForm([
            'doctor_name' => 'Dr. UpdatedViewTest',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($prescription->fresh()->doctor_name)->toBe('Dr. UpdatedViewTest');
});
