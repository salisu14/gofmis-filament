<?php

use App\Enums\VulnerabilityStatus;
use App\Filament\Coordinator\Resources\DeceasedResource\Pages\CreateDeceased as CoordinatorCreateDeceased;
use App\Filament\Coordinator\Resources\DeceasedResource\Pages\EditDeceased as CoordinatorEditDeceased;
use App\Filament\Resources\Deceased\Pages\CreateDeceased as AdminCreateDeceased;
use App\Filament\Resources\Deceased\Pages\EditDeceased as AdminEditDeceased;
use App\Models\Deceased;
use App\Models\User;
use App\Models\Zone;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->zone = Zone::create(['name' => 'North Zone']);

    $this->coordinator = User::factory()->create();
    $this->coordinator->assignRole('coordinator');
    $this->coordinator->coordinatedZone()->save($this->zone);

    $this->otherZone = Zone::create(['name' => 'South Zone']);
    $this->otherCoordinator = User::factory()->create();
    $this->otherCoordinator->assignRole('coordinator');
    $this->otherCoordinator->coordinatedZone()->save($this->otherZone);
});

// TEST 1 — Coordinator create with NIN present
it('coordinator can create deceased record with NIN present', function () {
    $this->actingAs($this->coordinator);

    $nin = '12345678901';

    Livewire::test(CoordinatorCreateDeceased::class)
        ->set('data.first_name', 'Ibrahim')
        ->set('data.last_name', 'Musa')
        ->set('data.middle_name', 'Ali')
        ->set('data.has_nin', true)
        ->set('data.nin', $nin)
        ->set('data.vulnerability_status', VulnerabilityStatus::A->value)
        ->set('data.guardian_name', 'Usman Musa')
        ->set('data.guardian_phone', '08030001111')
        ->set('data.number_of_widows_left', 1)
        ->set('data.number_of_orphans_left', 2)
        ->set('data.address', '10 Kano Road')
        ->set('data.date_registered', now()->toDateString())
        ->set('data.date_of_death', now()->toDateString())
        ->call('create')
        ->assertHasNoFormErrors();

    $deceased = Deceased::withoutGlobalScopes()->where('nin', $nin)->first();

    expect($deceased)->not->toBeNull();
    expect($deceased->first_name)->toBe('Ibrahim');
    expect($deceased->last_name)->toBe('Musa');
    expect($deceased->nin)->toBe($nin);
    expect($deceased->has_nin)->toBeTrue();
    expect($deceased->zone_id)->toBe($this->zone->id);
});

// TEST 2 — Coordinator create with no NIN
it('coordinator can create deceased record with no NIN', function () {
    $this->actingAs($this->coordinator);

    Livewire::test(CoordinatorCreateDeceased::class)
        ->set('data.first_name', 'Fatima')
        ->set('data.last_name', 'Garba')
        ->set('data.nin', null)
        ->set('data.vulnerability_status', VulnerabilityStatus::B->value)
        ->set('data.guardian_name', 'Aisha Garba')
        ->set('data.guardian_phone', '08030002222')
        ->set('data.number_of_widows_left', 0)
        ->set('data.number_of_orphans_left', 1)
        ->set('data.address', '15 Zaria Road')
        ->set('data.date_registered', now()->toDateString())
        ->set('data.date_of_death', now()->toDateString())
        ->call('create')
        ->assertHasNoFormErrors();

    $deceased = Deceased::withoutGlobalScopes()->where('first_name', 'Fatima')->where('last_name', 'Garba')->first();

    expect($deceased)->not->toBeNull();
    expect($deceased->nin)->toBeNull();
    expect($deceased->has_nin)->toBeFalse();
    expect($deceased->zone_id)->toBe($this->zone->id);
});

// TEST 3 — DOB persistence
it('coordinator create persists date_of_birth accurately', function () {
    $this->actingAs($this->coordinator);

    $dob = '1982-04-12';

    Livewire::test(CoordinatorCreateDeceased::class)
        ->set('data.first_name', 'Sani')
        ->set('data.last_name', 'Abubakar')
        ->set('data.date_of_birth', $dob)
        ->set('data.vulnerability_status', VulnerabilityStatus::A->value)
        ->set('data.guardian_name', 'Bello Abubakar')
        ->set('data.number_of_widows_left', 1)
        ->set('data.number_of_orphans_left', 3)
        ->set('data.date_registered', now()->toDateString())
        ->set('data.date_of_death', now()->toDateString())
        ->call('create')
        ->assertHasNoFormErrors();

    $deceased = Deceased::withoutGlobalScopes()->where('first_name', 'Sani')->first();

    expect($deceased)->not->toBeNull();
    expect($deceased->date_of_birth->format('Y-m-d'))->toBe($dob);
});

// TEST 4 — DOD persistence
it('coordinator create persists date_of_death accurately', function () {
    $this->actingAs($this->coordinator);

    $dod = '2025-11-20';

    Livewire::test(CoordinatorCreateDeceased::class)
        ->set('data.first_name', 'Kabiru')
        ->set('data.last_name', 'Usman')
        ->set('data.date_of_death', $dod)
        ->set('data.vulnerability_status', VulnerabilityStatus::A->value)
        ->set('data.guardian_name', 'Hamza Usman')
        ->set('data.number_of_widows_left', 1)
        ->set('data.number_of_orphans_left', 0)
        ->set('data.date_registered', '2025-11-25')
        ->call('create')
        ->assertHasNoFormErrors();

    $deceased = Deceased::withoutGlobalScopes()->where('first_name', 'Kabiru')->first();

    expect($deceased)->not->toBeNull();
    expect($deceased->date_of_death->format('Y-m-d'))->toBe($dod);
});

// TEST 5 — both DOB and DOD persistence
it('coordinator create persists both date_of_birth and date_of_death', function () {
    $this->actingAs($this->coordinator);

    $dob = '1976-08-15';
    $dod = '2024-12-01';

    Livewire::test(CoordinatorCreateDeceased::class)
        ->set('data.first_name', 'Mustapha')
        ->set('data.last_name', 'Yakubu')
        ->set('data.date_of_birth', $dob)
        ->set('data.date_of_death', $dod)
        ->set('data.vulnerability_status', VulnerabilityStatus::C->value)
        ->set('data.guardian_name', 'Yakubu Senior')
        ->set('data.number_of_widows_left', 2)
        ->set('data.number_of_orphans_left', 4)
        ->set('data.date_registered', '2024-12-10')
        ->call('create')
        ->assertHasNoFormErrors();

    $deceased = Deceased::withoutGlobalScopes()->where('first_name', 'Mustapha')->first();

    expect($deceased)->not->toBeNull();
    expect($deceased->date_of_birth->format('Y-m-d'))->toBe($dob);
    expect($deceased->date_of_death->format('Y-m-d'))->toBe($dod);
    expect($deceased->age_at_death)->toBe(48);
});

// TEST 6 — zone isolation
it('coordinator creation remains locked to coordinator authorized zone', function () {
    $this->actingAs($this->coordinator);

    // Attempting to set zone_id in form to another zone
    Livewire::test(CoordinatorCreateDeceased::class)
        ->set('data.first_name', 'Tampered')
        ->set('data.last_name', 'ZoneAttempt')
        ->set('data.zone_id', $this->otherZone->id)
        ->set('data.vulnerability_status', VulnerabilityStatus::A->value)
        ->set('data.guardian_name', 'Guardian')
        ->set('data.number_of_widows_left', 0)
        ->set('data.number_of_orphans_left', 0)
        ->set('data.date_registered', now()->toDateString())
        ->set('data.date_of_death', now()->toDateString())
        ->call('create')
        ->assertHasNoFormErrors();

    $deceased = Deceased::withoutGlobalScopes()->where('first_name', 'Tampered')->first();

    expect($deceased)->not->toBeNull();
    // Verify that the record is assigned to the coordinator's own zone, not the tampered zone
    expect($deceased->zone_id)->toBe($this->zone->id);
    expect($deceased->zone_id)->not->toBe($this->otherZone->id);
});

// TEST 7 — Admin regression
it('admin deceased creation still works with dates and NIN', function () {
    $this->actingAs($this->admin);

    $nin = '98765432109';
    $dob = '1990-01-01';
    $dod = '2025-05-05';

    Livewire::test(AdminCreateDeceased::class)
        ->set('data.first_name', 'AdminCreated')
        ->set('data.last_name', 'Deceased')
        ->set('data.has_nin', true)
        ->set('data.nin', $nin)
        ->set('data.zone_id', $this->zone->id)
        ->set('data.date_of_birth', $dob)
        ->set('data.date_of_death', $dod)
        ->set('data.vulnerability_status', VulnerabilityStatus::A->value)
        ->set('data.guardian_name', 'Admin Guardian')
        ->set('data.guardian_phone', '08039998888')
        ->set('data.address', 'Admin Address')
        ->set('data.number_of_widows_left', 1)
        ->set('data.number_of_orphans_left', 1)
        ->set('data.date_registered', now()->toDateString())
        ->call('create')
        ->assertHasNoFormErrors();

    $deceased = Deceased::withoutGlobalScopes()->where('nin', $nin)->first();

    expect($deceased)->not->toBeNull();
    expect($deceased->first_name)->toBe('AdminCreated');
    expect($deceased->has_nin)->toBeTrue();
    expect($deceased->date_of_birth->format('Y-m-d'))->toBe($dob);
    expect($deceased->date_of_death->format('Y-m-d'))->toBe($dod);
});

// TEST 8 / Former 419 path — exercise exact coordinator creation path without error
it('exercises former 419 coordinator creation path and completes without TypeError', function () {
    $this->actingAs($this->coordinator);

    // This exact flow previously failed with TypeError: DeceasedData::__construct(): Argument #5 ($hasNin) not passed
    Livewire::test(CoordinatorCreateDeceased::class)
        ->set('data.first_name', 'Regression')
        ->set('data.last_name', 'Check419')
        ->set('data.middle_name', 'NoTypeError')
        ->set('data.has_nin', true)
        ->set('data.nin', '11122233344')
        ->set('data.vulnerability_status', VulnerabilityStatus::A->value)
        ->set('data.guardian_name', 'Test Guardian')
        ->set('data.guardian_phone', '08000000000')
        ->set('data.number_of_widows_left', 1)
        ->set('data.number_of_orphans_left', 2)
        ->set('data.address', 'Test Address')
        ->set('data.date_registered', '2026-01-01')
        ->set('data.date_of_birth', '1985-06-15')
        ->set('data.date_of_death', '2025-12-31')
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect();

    $record = Deceased::withoutGlobalScopes()
        ->where('first_name', 'Regression')
        ->where('last_name', 'Check419')
        ->first();

    expect($record)->not->toBeNull();
    expect($record->has_nin)->toBeTrue();
    expect($record->date_of_birth->format('Y-m-d'))->toBe('1985-06-15');
    expect($record->date_of_death->format('Y-m-d'))->toBe('2025-12-31');
});

// TEST 9 — Admin create DOB persistence
it('admin creation persists date_of_birth accurately', function () {
    $this->actingAs($this->admin);

    $dob = '1988-03-25';

    Livewire::test(AdminCreateDeceased::class)
        ->set('data.first_name', 'AdminDOB')
        ->set('data.last_name', 'Test')
        ->set('data.date_of_birth', $dob)
        ->set('data.zone_id', $this->zone->id)
        ->set('data.vulnerability_status', VulnerabilityStatus::A->value)
        ->set('data.guardian_name', 'Guardian')
        ->set('data.number_of_widows_left', 0)
        ->set('data.number_of_orphans_left', 0)
        ->set('data.date_registered', now()->toDateString())
        ->set('data.date_of_death', now()->toDateString())
        ->call('create')
        ->assertHasNoFormErrors();

    $record = Deceased::withoutGlobalScopes()->where('first_name', 'AdminDOB')->first();

    expect($record)->not->toBeNull();
    expect($record->date_of_birth->format('Y-m-d'))->toBe($dob);
});

// TEST 10 — Admin create DOD persistence
it('admin creation persists date_of_death accurately', function () {
    $this->actingAs($this->admin);

    $dod = '2024-10-10';

    Livewire::test(AdminCreateDeceased::class)
        ->set('data.first_name', 'AdminDOD')
        ->set('data.last_name', 'Test')
        ->set('data.date_of_death', $dod)
        ->set('data.zone_id', $this->zone->id)
        ->set('data.vulnerability_status', VulnerabilityStatus::A->value)
        ->set('data.guardian_name', 'Guardian')
        ->set('data.number_of_widows_left', 0)
        ->set('data.number_of_orphans_left', 0)
        ->set('data.date_registered', '2024-10-15')
        ->call('create')
        ->assertHasNoFormErrors();

    $record = Deceased::withoutGlobalScopes()->where('first_name', 'AdminDOD')->first();

    expect($record)->not->toBeNull();
    expect($record->date_of_death->format('Y-m-d'))->toBe($dod);
});

// TEST 11 — Admin create both DOB and DOD persistence
it('admin creation persists both date_of_birth and date_of_death', function () {
    $this->actingAs($this->admin);

    $dob = '1970-05-20';
    $dod = '2023-11-11';

    Livewire::test(AdminCreateDeceased::class)
        ->set('data.first_name', 'AdminBothDates')
        ->set('data.last_name', 'Test')
        ->set('data.date_of_birth', $dob)
        ->set('data.date_of_death', $dod)
        ->set('data.zone_id', $this->zone->id)
        ->set('data.vulnerability_status', VulnerabilityStatus::B->value)
        ->set('data.guardian_name', 'Guardian')
        ->set('data.number_of_widows_left', 1)
        ->set('data.number_of_orphans_left', 2)
        ->set('data.date_registered', '2023-11-20')
        ->call('create')
        ->assertHasNoFormErrors();

    $record = Deceased::withoutGlobalScopes()->where('first_name', 'AdminBothDates')->first();

    expect($record)->not->toBeNull();
    expect($record->date_of_birth->format('Y-m-d'))->toBe($dob);
    expect($record->date_of_death->format('Y-m-d'))->toBe($dod);
    expect($record->age_at_death)->toBe(53);
});

// TEST 12 — Date validation: death before birth rejected
it('validates date of death cannot be earlier than date of birth on creation', function () {
    $this->actingAs($this->admin);

    Livewire::test(AdminCreateDeceased::class)
        ->set('data.first_name', 'Invalid')
        ->set('data.last_name', 'Dates')
        ->set('data.zone_id', $this->zone->id)
        ->set('data.vulnerability_status', VulnerabilityStatus::A->value)
        ->set('data.guardian_name', 'Guardian')
        ->set('data.date_registered', now()->toDateString())
        ->set('data.date_of_birth', '2020-01-01')
        ->set('data.date_of_death', '2015-01-01')
        ->set('data.number_of_widows_left', 0)
        ->set('data.number_of_orphans_left', 0)
        ->call('create')
        ->assertHasFormErrors(['date_of_death']);
});

// TEST 13 — Date validation: future death rejected
it('validates date of death cannot be in the future on creation', function () {
    $this->actingAs($this->admin);

    Livewire::test(AdminCreateDeceased::class)
        ->set('data.first_name', 'Future')
        ->set('data.last_name', 'Death')
        ->set('data.zone_id', $this->zone->id)
        ->set('data.vulnerability_status', VulnerabilityStatus::A->value)
        ->set('data.guardian_name', 'Guardian')
        ->set('data.date_registered', now()->toDateString())
        ->set('data.date_of_death', \Illuminate\Support\Carbon::tomorrow()->toDateString())
        ->set('data.number_of_widows_left', 0)
        ->set('data.number_of_orphans_left', 0)
        ->call('create')
        ->assertHasFormErrors(['date_of_death']);
});

// TEST 14 — Date validation: future registration date rejected
it('validates date registered cannot be in the future on creation', function () {
    $this->actingAs($this->admin);

    Livewire::test(AdminCreateDeceased::class)
        ->set('data.first_name', 'Future')
        ->set('data.last_name', 'Registration')
        ->set('data.zone_id', $this->zone->id)
        ->set('data.vulnerability_status', VulnerabilityStatus::A->value)
        ->set('data.guardian_name', 'Guardian')
        ->set('data.date_registered', \Illuminate\Support\Carbon::tomorrow()->toDateString())
        ->set('data.date_of_death', now()->toDateString())
        ->set('data.number_of_widows_left', 0)
        ->set('data.number_of_orphans_left', 0)
        ->call('create')
        ->assertHasFormErrors(['date_registered']);
});

// SUPER ADMIN EDIT TESTS

it('Super Admin edit form exposes Date of Birth and Date of Death', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super_admin');
    $this->actingAs($superAdmin);

    $deceased = Deceased::create([
        'first_name' => 'Super',
        'last_name' => 'AdminDeceased',
        'nin' => '12345678999',
        'has_nin' => true,
        'reg_no' => 'GOF/2026/9999',
        'guardian_name' => 'Guardian',
        'guardian_phone' => '08012345678',
        'vulnerability_status' => VulnerabilityStatus::A->value,
        'date_registered' => '2026-01-01',
        'date_of_birth' => '1985-05-15',
        'date_of_death' => '2025-12-20',
        'zone_id' => $this->zone->id,
        'number_of_orphans_left' => 0,
        'number_of_widows_left' => 0,
    ]);

    Livewire::test(AdminEditDeceased::class, ['record' => $deceased->id])
        ->assertSuccessful()
        ->assertFormFieldExists('date_of_birth')
        ->assertFormFieldExists('date_of_death');
});

it('Super Admin edit form hydrates existing DOB and DOD correctly', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super_admin');
    $this->actingAs($superAdmin);

    $deceased = Deceased::create([
        'first_name' => 'Super',
        'last_name' => 'AdminHydrate',
        'nin' => '12345678998',
        'has_nin' => true,
        'reg_no' => 'GOF/2026/9998',
        'guardian_name' => 'Guardian',
        'guardian_phone' => '08012345678',
        'vulnerability_status' => VulnerabilityStatus::A->value,
        'date_registered' => '2026-01-01',
        'date_of_birth' => '1982-04-10',
        'date_of_death' => '2025-11-15',
        'zone_id' => $this->zone->id,
        'number_of_orphans_left' => 0,
        'number_of_widows_left' => 0,
    ]);

    Livewire::test(AdminEditDeceased::class, ['record' => $deceased->id])
        ->assertSuccessful()
        ->assertSet('data.date_of_birth', fn ($val) => str_starts_with((string) $val, '1982-04-10'))
        ->assertSet('data.date_of_death', fn ($val) => str_starts_with((string) $val, '2025-11-15'));
});

it('Super Admin updating DOB and DOD persists changes correctly', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super_admin');
    $this->actingAs($superAdmin);

    $deceased = Deceased::create([
        'first_name' => 'Super',
        'last_name' => 'AdminPersist',
        'nin' => '12345678997',
        'has_nin' => true,
        'reg_no' => 'GOF/2026/9997',
        'guardian_name' => 'Guardian',
        'guardian_phone' => '08012345678',
        'vulnerability_status' => VulnerabilityStatus::A->value,
        'date_registered' => '2026-01-01',
        'date_of_birth' => '1980-01-01',
        'date_of_death' => '2024-01-01',
        'zone_id' => $this->zone->id,
        'number_of_orphans_left' => 0,
        'number_of_widows_left' => 0,
    ]);

    Livewire::test(AdminEditDeceased::class, ['record' => $deceased->id])
        ->set('data.date_of_birth', '1975-06-20')
        ->set('data.date_of_death', '2025-08-30')
        ->call('save')
        ->assertHasNoFormErrors();

    $deceased->refresh();
    expect($deceased->date_of_birth->format('Y-m-d'))->toBe('1975-06-20');
    expect($deceased->date_of_death->format('Y-m-d'))->toBe('2025-08-30');
});

it('Super Admin saving unrelated changes does not wipe existing DOB and DOD', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super_admin');
    $this->actingAs($superAdmin);

    $deceased = Deceased::create([
        'first_name' => 'Super',
        'last_name' => 'UnrelatedSave',
        'nin' => '12345678996',
        'has_nin' => true,
        'reg_no' => 'GOF/2026/9996',
        'guardian_name' => 'Original Guardian',
        'guardian_phone' => '08012345678',
        'vulnerability_status' => VulnerabilityStatus::A->value,
        'date_registered' => '2026-01-01',
        'date_of_birth' => '1988-09-09',
        'date_of_death' => '2025-10-10',
        'zone_id' => $this->zone->id,
        'number_of_orphans_left' => 0,
        'number_of_widows_left' => 0,
    ]);

    Livewire::test(AdminEditDeceased::class, ['record' => $deceased->id])
        ->set('data.guardian_name', 'Updated Guardian')
        ->call('save')
        ->assertHasNoFormErrors();

    $deceased->refresh();
    expect($deceased->guardian_name)->toBe('Updated Guardian');
    expect($deceased->date_of_birth->format('Y-m-d'))->toBe('1988-09-09');
    expect($deceased->date_of_death->format('Y-m-d'))->toBe('2025-10-10');
});

it('Super Admin edit page enforces date invariants (future DOD and DOB after DOD)', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super_admin');
    $this->actingAs($superAdmin);

    $deceased = Deceased::create([
        'first_name' => 'Super',
        'last_name' => 'Invariants',
        'nin' => '12345678995',
        'has_nin' => true,
        'reg_no' => 'GOF/2026/9995',
        'guardian_name' => 'Guardian',
        'guardian_phone' => '08012345678',
        'vulnerability_status' => VulnerabilityStatus::A->value,
        'date_registered' => '2026-01-01',
        'date_of_birth' => '1990-01-01',
        'date_of_death' => '2025-01-01',
        'zone_id' => $this->zone->id,
        'number_of_orphans_left' => 0,
        'number_of_widows_left' => 0,
    ]);

    // Test DOB after DOD
    Livewire::test(AdminEditDeceased::class, ['record' => $deceased->id])
        ->set('data.date_of_birth', '2026-01-01')
        ->set('data.date_of_death', '2025-01-01')
        ->call('save')
        ->assertHasFormErrors(['date_of_death']);

    // Test future DOD
    Livewire::test(AdminEditDeceased::class, ['record' => $deceased->id])
        ->set('data.date_of_birth', '1990-01-01')
        ->set('data.date_of_death', \Illuminate\Support\Carbon::tomorrow()->toDateString())
        ->call('save')
        ->assertHasFormErrors(['date_of_death']);
});

// COORDINATOR HAS NIN TOGGLE & EDIT TESTS

it('Coordinator create and edit forms expose Has NIN? toggle', function () {
    $this->actingAs($this->coordinator);

    Livewire::test(CoordinatorCreateDeceased::class)
        ->assertSuccessful()
        ->assertFormFieldExists('has_nin')
        ->assertSee('Has NIN?');

    $deceased = Deceased::create([
        'first_name' => 'Coord',
        'last_name' => 'HasNinToggle',
        'nin' => '12345678994',
        'has_nin' => true,
        'reg_no' => 'GOF/2026/9994',
        'guardian_name' => 'Guardian',
        'guardian_phone' => '08012345678',
        'vulnerability_status' => VulnerabilityStatus::A->value,
        'date_registered' => '2026-01-01',
        'date_of_birth' => '1990-01-01',
        'date_of_death' => '2025-01-01',
        'zone_id' => $this->zone->id,
        'number_of_orphans_left' => 0,
        'number_of_widows_left' => 0,
    ]);

    Livewire::test(CoordinatorEditDeceased::class, ['record' => $deceased->id])
        ->assertSuccessful()
        ->assertFormFieldExists('has_nin')
        ->assertSee('Has NIN?');
});

it('Coordinator edit hydrates Has NIN? toggle correctly based on record state', function () {
    $this->actingAs($this->coordinator);

    $withNin = Deceased::create([
        'first_name' => 'With',
        'last_name' => 'NinRecord',
        'nin' => '12345678993',
        'has_nin' => true,
        'reg_no' => 'GOF/2026/9993',
        'guardian_name' => 'Guardian',
        'guardian_phone' => '08012345678',
        'vulnerability_status' => VulnerabilityStatus::A->value,
        'date_registered' => '2026-01-01',
        'date_of_birth' => '1990-01-01',
        'date_of_death' => '2025-01-01',
        'zone_id' => $this->zone->id,
        'number_of_orphans_left' => 0,
        'number_of_widows_left' => 0,
    ]);

    $withoutNin = Deceased::create([
        'first_name' => 'Without',
        'last_name' => 'NinRecord',
        'nin' => null,
        'has_nin' => false,
        'reg_no' => 'GOF/2026/9992',
        'guardian_name' => 'Guardian',
        'guardian_phone' => '08012345678',
        'vulnerability_status' => VulnerabilityStatus::A->value,
        'date_registered' => '2026-01-01',
        'date_of_birth' => '1990-01-01',
        'date_of_death' => '2025-01-01',
        'zone_id' => $this->zone->id,
        'number_of_orphans_left' => 0,
        'number_of_widows_left' => 0,
    ]);

    Livewire::test(CoordinatorEditDeceased::class, ['record' => $withNin->id])
        ->assertSuccessful()
        ->assertSet('data.has_nin', true)
        ->assertSet('data.nin', '12345678993');

    Livewire::test(CoordinatorEditDeceased::class, ['record' => $withoutNin->id])
        ->assertSuccessful()
        ->assertSet('data.has_nin', false)
        ->assertSet('data.nin', null);
});

it('Coordinator editing NIN toggle OFF clears NIN state following canonical Admin semantics', function () {
    $this->actingAs($this->coordinator);

    $deceased = Deceased::create([
        'first_name' => 'Toggle',
        'last_name' => 'OffNin',
        'nin' => '12345678991',
        'has_nin' => true,
        'reg_no' => 'GOF/2026/9991',
        'guardian_name' => 'Guardian',
        'guardian_phone' => '08012345678',
        'vulnerability_status' => VulnerabilityStatus::A->value,
        'date_registered' => '2026-01-01',
        'date_of_birth' => '1990-01-01',
        'date_of_death' => '2025-01-01',
        'zone_id' => $this->zone->id,
        'number_of_orphans_left' => 0,
        'number_of_widows_left' => 0,
    ]);

    Livewire::test(CoordinatorEditDeceased::class, ['record' => $deceased->id])
        ->set('data.has_nin', false)
        ->call('save')
        ->assertHasNoFormErrors();

    $deceased->refresh();
    expect($deceased->has_nin)->toBeFalse();
    expect($deceased->nin)->toBeNull();
});

it('Coordinator edit preserves coordinator zone and does not alter zone assignment', function () {
    $this->actingAs($this->coordinator);

    $deceased = Deceased::create([
        'first_name' => 'Edit',
        'last_name' => 'ZonePreserve',
        'nin' => '12345678990',
        'has_nin' => true,
        'reg_no' => 'GOF/2026/9990',
        'guardian_name' => 'Guardian',
        'guardian_phone' => '08012345678',
        'vulnerability_status' => VulnerabilityStatus::A->value,
        'date_registered' => '2026-01-01',
        'date_of_birth' => '1990-01-01',
        'date_of_death' => '2025-01-01',
        'zone_id' => $this->zone->id,
        'number_of_orphans_left' => 0,
        'number_of_widows_left' => 0,
    ]);

    Livewire::test(CoordinatorEditDeceased::class, ['record' => $deceased->id])
        ->set('data.first_name', 'EditUpdated')
        ->call('save')
        ->assertHasNoFormErrors();

    $deceased->refresh();
    expect($deceased->first_name)->toBe('EditUpdated');
    expect($deceased->zone_id)->toBe($this->zone->id);
});
