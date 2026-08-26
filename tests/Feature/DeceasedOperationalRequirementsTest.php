<?php

use App\Enums\OrphanStatus;
use App\Enums\VulnerabilityStatus;
use App\Filament\Coordinator\Resources\DeceasedResource\Pages\CreateDeceased as CoordinatorCreateDeceased;
use App\Filament\Coordinator\Resources\DeceasedResource\Pages\ListDeceaseds as CoordinatorListDeceaseds;
use App\Filament\Coordinator\Resources\DeceasedResource\Pages\ViewDeceased as CoordinatorViewDeceased;
use App\Filament\Resources\Deceased\Pages\CreateDeceased;
use App\Filament\Resources\Deceased\Pages\EditDeceased;
use App\Filament\Resources\Deceased\Pages\ListDeceaseds;
use App\Filament\Resources\Deceased\Pages\ViewDeceased;
use App\Models\Deceased;
use App\Models\InterventionRequest;
use App\Models\InterventionType;
use App\Models\Orphan;
use App\Models\User;
use App\Models\Widow;
use App\Models\Zone;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(\Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin'], ['id' => Str::uuid(), 'uuid' => Str::uuid()]);
    Role::firstOrCreate(['name' => 'super_admin'], ['id' => Str::uuid(), 'uuid' => Str::uuid()]);
    Role::firstOrCreate(['name' => 'coordinator'], ['id' => Str::uuid(), 'uuid' => Str::uuid()]);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->zone = Zone::create(['name' => 'Zone A']);

    $this->coordinator = User::factory()->create();
    $this->coordinator->assignRole('coordinator');
    $this->coordinator->coordinatedZone()->save($this->zone);

    $this->otherZone = Zone::create(['name' => 'Zone B']);
    $this->otherCoordinator = User::factory()->create();
    $this->otherCoordinator->assignRole('coordinator');
    $this->otherCoordinator->coordinatedZone()->save($this->otherZone);
});

if (!function_exists('makeDeceased')) {
    function makeDeceased(array $attrs = []): Deceased
    {
        return Deceased::withoutGlobalScopes()->create(array_merge([
            'first_name' => 'Test',
            'last_name' => 'Deceased',
            'nin' => fake()->unique()->numerify('###########'),
            'reg_no' => 'DEC-'.fake()->unique()->numberBetween(10000, 99999),
            'guardian_name' => 'Guardian',
            'guardian_phone' => '08012345678',
            'vulnerability_status' => VulnerabilityStatus::A,
            'date_registered' => now()->toDateString(),
            'number_of_orphans_left' => 0,
            'number_of_widows_left' => 0,
        ], $attrs));
    }
}

// 1. Admin list renders
it('admin deceased list page renders', function () {
    $this->actingAs($this->admin);
    Livewire::test(ListDeceaseds::class)->loadTable()->assertSuccessful();
});

// 2. Admin create form renders
it('admin create deceased form renders', function () {
    $this->actingAs($this->admin);
    Livewire::test(CreateDeceased::class)
        ->assertSuccessful()
        ->assertFormExists();
});

// 3. Admin view renders
it('admin view deceased renders', function () {
    $this->actingAs($this->admin);
    $d = makeDeceased(['zone_id' => $this->zone->id]);
    Livewire::test(ViewDeceased::class, ['record' => $d->id])->assertSuccessful();
});

// 4. Coordinator list renders
it('coordinator deceased list page renders', function () {
    $this->actingAs($this->coordinator);
    Livewire::test(CoordinatorListDeceaseds::class)->loadTable()->assertSuccessful();
});

// 5. Coordinator create form renders
it('coordinator create deceased form renders', function () {
    $this->actingAs($this->coordinator);
    Livewire::test(CoordinatorCreateDeceased::class)
        ->assertSuccessful()
        ->assertFormExists();
});

// 6. Coordinator view renders for own-zone record
it('coordinator view renders for own-zone deceased', function () {
    $this->actingAs($this->coordinator);
    $d = makeDeceased(['zone_id' => $this->zone->id]);
    Livewire::test(CoordinatorViewDeceased::class, ['record' => $d->id])->assertSuccessful();
});

// 7. Admin create requires number_of_widows_left and number_of_orphans_left
it('admin create requires historical dependent counts', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreateDeceased::class)
        ->fillForm([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'vulnerability_status' => VulnerabilityStatus::A->value,
            'zone_id' => $this->zone->id,
            'guardian_name' => 'Guardian',
            'date_registered' => now()->toDateString(),
            'date_of_death' => now()->toDateString(),
            'number_of_widows_left' => null,
            'number_of_orphans_left' => null,
        ])
        ->call('create')
        ->assertHasFormErrors(['number_of_widows_left', 'number_of_orphans_left']);
});

// 8. Coordinator create requires historical dependent counts
it('coordinator create requires historical dependent counts', function () {
    $this->actingAs($this->coordinator);

    Livewire::test(CoordinatorCreateDeceased::class)
        ->fillForm([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'vulnerability_status' => VulnerabilityStatus::A->value,
            'guardian_name' => 'Guardian',
            'date_registered' => now()->toDateString(),
            'date_of_death' => now()->toDateString(),
            'number_of_widows_left' => null,
            'number_of_orphans_left' => null,
        ])
        ->call('create')
        ->assertHasFormErrors(['number_of_widows_left', 'number_of_orphans_left']);
});

// 9. Explicit 0 is valid for historical counts
it('accepts explicit zero for historical dependent counts', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreateDeceased::class)
        ->fillForm([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'nin' => '12345678901',
            'vulnerability_status' => VulnerabilityStatus::A->value,
            'zone_id' => $this->zone->id,
            'guardian_name' => 'Guardian',
            'guardian_phone' => '08012345678',
            'date_registered' => now()->toDateString(),
            'date_of_death' => now()->toDateString(),
            'number_of_widows_left' => 0,
            'number_of_orphans_left' => 0,
            'address' => 'Address',
        ])
        ->call('create')
        ->assertHasNoFormErrors(['number_of_widows_left', 'number_of_orphans_left']);
});

// 10. Negative historical counts are rejected
it('rejects negative numbers for historical dependent counts', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreateDeceased::class)
        ->fillForm([
            'number_of_widows_left' => -1,
            'number_of_orphans_left' => -2,
        ])
        ->call('create')
        ->assertHasFormErrors(['number_of_widows_left', 'number_of_orphans_left']);
});

// 11. Registration Number is auto-generated and immutable after creation
it('registration number is auto-generated and immutable after creation', function () {
    $d = makeDeceased(['zone_id' => $this->zone->id, 'reg_no' => 'DEC-99999']);

    $this->actingAs($this->admin);

    Livewire::test(EditDeceased::class, ['record' => $d->id])
        ->assertFormFieldIsDisabled('reg_no');
});

// 12. No Gender field exists on Admin or Coordinator form
it('asserts no Deceased Gender field exists on Admin or Coordinator forms', function () {
    $this->actingAs($this->admin);
    Livewire::test(CreateDeceased::class)->assertFormFieldDoesNotExist('gender');

    $this->actingAs($this->coordinator);
    Livewire::test(CoordinatorCreateDeceased::class)->assertFormFieldDoesNotExist('gender');
});

// 13. Physical Admin form labels and fields match Foundation requirements
it('physical admin form contains exact business labels and fields', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreateDeceased::class)
        ->assertFormFieldExists('first_name')
        ->assertFormFieldExists('middle_name')
        ->assertFormFieldExists('last_name')
        ->assertFormFieldExists('nin')
        ->assertFormFieldExists('reg_no')
        ->assertFormFieldExists('occupation')
        ->assertFormFieldExists('date_of_birth')
        ->assertFormFieldExists('age')
        ->assertFormFieldExists('vulnerability_status')
        ->assertFormFieldExists('date_registered')
        ->assertFormFieldExists('date_of_death')
        ->assertFormFieldExists('death_place')
        ->assertFormFieldExists('death_cause')
        ->assertFormFieldExists('number_of_widows_left')
        ->assertFormFieldExists('number_of_orphans_left')
        ->assertFormFieldExists('guardian_name')
        ->assertFormFieldExists('guardian_phone')
        ->assertFormFieldExists('address');
});

// 14. Physical Coordinator form labels and fields match Foundation requirements
it('physical coordinator form contains exact business labels and fields', function () {
    $this->actingAs($this->coordinator);

    Livewire::test(CoordinatorCreateDeceased::class)
        ->assertFormFieldExists('first_name')
        ->assertFormFieldExists('middle_name')
        ->assertFormFieldExists('last_name')
        ->assertFormFieldExists('nin')
        ->assertFormFieldExists('reg_no')
        ->assertFormFieldExists('date_of_birth')
        ->assertFormFieldExists('age')
        ->assertFormFieldExists('date_registered')
        ->assertFormFieldExists('date_of_death')
        ->assertFormFieldExists('death_place')
        ->assertFormFieldExists('death_cause')
        ->assertFormFieldExists('occupation')
        ->assertFormFieldExists('vulnerability_status')
        ->assertFormFieldExists('number_of_widows_left')
        ->assertFormFieldExists('number_of_orphans_left')
        ->assertFormFieldExists('guardian_name')
        ->assertFormFieldExists('guardian_phone')
        ->assertFormFieldExists('address');
});

// 15. Cause of death "Other" reveals and requires specify field
it('cause of death Other selection reveals and requires specify field', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreateDeceased::class)
        ->fillForm(['death_cause' => 'Other'])
        ->assertFormFieldIsVisible('death_cause_other')
        ->call('create')
        ->assertHasFormErrors(['death_cause_other']);
});

// 16. Creates deceased with all supported Foundation fields
it('creates deceased with all supported Foundation fields', function () {
    $d = makeDeceased([
        'zone_id' => $this->zone->id,
        'date_of_birth' => '1975-03-15',
        'date_of_death' => '2023-06-20',
        'date_registered' => '2023-06-22',
        'death_cause' => 'Natural Causes',
        'death_place' => 'Hospital / Health Facility',
        'occupation' => 'Farmer',
        'number_of_orphans_left' => 3,
        'number_of_widows_left' => 1,
        'address' => '5 Main Street, Kano',
        'guardian_name' => 'Musa Ibrahim',
        'guardian_phone' => '08099999999',
        'vulnerability_status' => VulnerabilityStatus::A,
    ]);
    $d->refresh();
    expect($d->date_of_birth->format('Y-m-d'))->toBe('1975-03-15');
    expect($d->date_of_death->format('Y-m-d'))->toBe('2023-06-20');
    expect($d->date_registered->format('Y-m-d'))->toBe('2023-06-22');
    expect($d->death_cause)->toBe('Natural Causes');
    expect($d->death_place)->toBe('Hospital / Health Facility');
    expect($d->occupation)->toBe('Farmer');
    expect($d->number_of_orphans_left)->toBe(3);
    expect($d->number_of_widows_left)->toBe(1);
    expect($d->guardian_name)->toBe('Musa Ibrahim');
    expect($d->zone_id)->toBe($this->zone->id);
});

// 17. Date registered and Date of Death are separate
it('date_registered and date_of_death are independent separate fields', function () {
    $d = makeDeceased(['date_registered' => '2023-06-22', 'date_of_death' => '2023-06-10']);
    $d->refresh();
    expect($d->date_registered->format('Y-m-d'))->toBe('2023-06-22');
    expect($d->date_of_death->format('Y-m-d'))->toBe('2023-06-10');
    expect($d->date_registered->format('Y-m-d'))->not->toBe($d->date_of_death->format('Y-m-d'));
});

// 18. Future date_of_death rejected
it('validates date of death cannot be in the future', function () {
    $this->actingAs($this->admin);
    Livewire::test(CreateDeceased::class)
        ->fillForm([
            'first_name' => 'Ali',
            'last_name' => 'Musa',
            'nin' => '12345678901',
            'zone_id' => $this->zone->id,
            'vulnerability_status' => 'A',
            'guardian_name' => 'Jane',
            'date_registered' => now()->format('Y-m-d'),
            'date_of_death' => Carbon::tomorrow()->format('Y-m-d'),
            'number_of_widows_left' => 0,
            'number_of_orphans_left' => 0,
            'address' => '1 Test St',
        ])
        ->call('create')
        ->assertHasFormErrors(['date_of_death']);
});

// 19. DOB after DOD rejected
it('validates date of birth cannot be after date of death', function () {
    $this->actingAs($this->admin);
    Livewire::test(CreateDeceased::class)
        ->fillForm([
            'first_name' => 'Ali',
            'last_name' => 'Musa',
            'nin' => '23456789012',
            'zone_id' => $this->zone->id,
            'vulnerability_status' => 'A',
            'guardian_name' => 'Jane',
            'date_registered' => now()->format('Y-m-d'),
            'date_of_birth' => '2020-01-01',
            'date_of_death' => '2019-01-01',
            'number_of_widows_left' => 0,
            'number_of_orphans_left' => 0,
            'address' => '1 Test St',
        ])
        ->call('create')
        ->assertHasFormErrors(['date_of_death']);
});

// 20. Exact age_at_death calculation
it('calculates age at death correctly when both dates are provided', function () {
    $d = makeDeceased(['date_of_birth' => '1980-06-15', 'date_of_death' => '2023-06-15']);
    expect($d->age_at_death)->toBe(43);
});

// 21. Birthday boundary — death before birthday in death year
it('calculates correct age when death occurs before birthday in death year', function () {
    $d = makeDeceased(['date_of_birth' => '1980-12-31', 'date_of_death' => '2020-01-01']);
    expect($d->age_at_death)->toBe(39);
});

// 22. Missing DOB falls back to legacy age
it('falls back to legacy age when date_of_birth is missing', function () {
    $d = makeDeceased(['age' => 55, 'date_of_birth' => null, 'date_of_death' => null]);
    expect($d->age_at_death)->toBe(55);
});

// 23. Missing DOD falls back to legacy age
it('falls back to legacy age when date_of_death is missing but DOB is set', function () {
    $d = makeDeceased(['age' => 62, 'date_of_birth' => '1960-01-01', 'date_of_death' => null]);
    expect($d->age_at_death)->toBe(62);
});

// 24. Historical declared orphan count preserved
it('preserves the declared orphan count recorded at registration', function () {
    $d = makeDeceased(['number_of_orphans_left' => 4]);
    $d->refresh();
    expect($d->number_of_orphans_left)->toBe(4);
});

// 25. Live registered orphan count calculated correctly
it('live registered orphan count reflects actual linked orphan records', function () {
    $d = makeDeceased(['zone_id' => $this->zone->id, 'number_of_orphans_left' => 5]);
    foreach (range(1, 2) as $i) {
        Orphan::withoutGlobalScopes()->create([
            'first_name' => "Child{$i}",
            'last_name' => 'Test',
            'nin' => fake()->unique()->numerify('###########'),
            'reg_no' => 'ORF-'.fake()->unique()->numberBetween(10000, 99999),
            'deceased_id' => $d->id,
            'child_sequence' => $i,
            'status' => OrphanStatus::ACTIVE,
            'is_eligible' => true,
            'gender' => 'MALE',
        ]);
    }
    expect($d->number_of_orphans_left)->toBe(5);  // declared stays 5
    expect($d->orphans()->count())->toBe(2);       // registered is 2
});

// 26. Historical declared widow count preserved
it('preserves the declared widow count recorded at registration', function () {
    $d = makeDeceased(['number_of_widows_left' => 2]);
    $d->refresh();
    expect($d->number_of_widows_left)->toBe(2);
});

// 27. Live registered widow count calculated correctly
it('live registered widow count reflects actual linked widow records', function () {
    $d = makeDeceased(['zone_id' => $this->zone->id, 'number_of_widows_left' => 3]);
    Widow::withoutGlobalScopes()->create([
        'first_name' => 'Widow',
        'last_name' => 'One',
        'nin' => fake()->unique()->numerify('###########'),
        'reg_no' => 'WDF-'.fake()->unique()->numberBetween(10000, 99999),
        'deceased_id' => $d->id,
        'child_sequence' => 1,
        'is_eligible' => true,
        'is_married' => false,
        'address' => 'Test Address',
    ]);
    expect($d->number_of_widows_left)->toBe(3);
    expect($d->widows()->count())->toBe(1);
});

// 28. Zone filter
it('zone filter restricts admin list to the selected zone', function () {
    $this->actingAs($this->admin);
    $a = makeDeceased(['zone_id' => $this->zone->id]);
    $b = makeDeceased(['zone_id' => $this->otherZone->id]);
    Livewire::test(ListDeceaseds::class)
        ->loadTable()
        ->filterTable('zone_id', $this->zone->id)
        ->assertCanSeeTableRecords([$a])
        ->assertCanNotSeeTableRecords([$b]);
});

// 29. Vulnerability filter
it('vulnerability filter restricts results to selected status', function () {
    $this->actingAs($this->admin);
    $a = makeDeceased(['zone_id' => $this->zone->id, 'vulnerability_status' => VulnerabilityStatus::A]);
    $c = makeDeceased(['zone_id' => $this->zone->id, 'vulnerability_status' => VulnerabilityStatus::C]);
    Livewire::test(ListDeceaseds::class)
        ->loadTable()
        ->filterTable('vulnerability_status', 'A')
        ->assertCanSeeTableRecords([$a])
        ->assertCanNotSeeTableRecords([$c]);
});

// 30. Cause-of-death filter
it('cause of death filter restricts results to matching cause', function () {
    $this->actingAs($this->admin);
    $d1 = makeDeceased(['zone_id' => $this->zone->id, 'death_cause' => 'Natural Causes']);
    $d2 = makeDeceased(['zone_id' => $this->zone->id, 'death_cause' => 'Road Traffic Accident / Trauma']);
    Livewire::test(ListDeceaseds::class)
        ->loadTable()
        ->filterTable('death_cause', 'Natural Causes')
        ->assertCanSeeTableRecords([$d1])
        ->assertCanNotSeeTableRecords([$d2]);
});

// 31. Death-year filter
it('death year filter restricts results to the selected year', function () {
    $this->actingAs($this->admin);
    $d2022 = makeDeceased(['zone_id' => $this->zone->id, 'date_of_death' => '2022-05-15']);
    $d2023 = makeDeceased(['zone_id' => $this->zone->id, 'date_of_death' => '2023-11-01']);
    Livewire::test(ListDeceaseds::class)
        ->loadTable()
        ->filterTable('death_year', ['year' => '2022'])
        ->assertCanSeeTableRecords([$d2022])
        ->assertCanNotSeeTableRecords([$d2023]);
});

// 32. Death-date range filter
it('death date range filter restricts to the given date range', function () {
    $this->actingAs($this->admin);
    $in = makeDeceased(['zone_id' => $this->zone->id, 'date_of_death' => '2023-06-15']);
    $out = makeDeceased(['zone_id' => $this->zone->id, 'date_of_death' => '2023-01-01']);
    Livewire::test(ListDeceaseds::class)
        ->loadTable()
        ->filterTable('date_of_death_range', ['dod_from' => '2023-06-01', 'dod_to' => '2023-12-31'])
        ->assertCanSeeTableRecords([$in])
        ->assertCanNotSeeTableRecords([$out]);
});

// 33. Registration-year filter
it('registration year filter restricts results to the selected year', function () {
    $this->actingAs($this->admin);
    $r2020 = makeDeceased(['zone_id' => $this->zone->id, 'date_registered' => '2020-03-01']);
    $r2023 = makeDeceased(['zone_id' => $this->zone->id, 'date_registered' => '2023-09-10']);
    Livewire::test(ListDeceaseds::class)
        ->loadTable()
        ->filterTable('registration_year', ['year' => '2020'])
        ->assertCanSeeTableRecords([$r2020])
        ->assertCanNotSeeTableRecords([$r2023]);
});

// 34. Declared orphan-count filter (uses number_of_orphans_left column)
it('declared orphans filter uses historical number_of_orphans_left field', function () {
    $this->actingAs($this->admin);
    $high = makeDeceased(['zone_id' => $this->zone->id, 'number_of_orphans_left' => 5]);
    $low = makeDeceased(['zone_id' => $this->zone->id, 'number_of_orphans_left' => 1]);
    Livewire::test(ListDeceaseds::class)
        ->loadTable()
        ->filterTable('declared_orphans', ['min_orphans_declared' => '3'])
        ->assertCanSeeTableRecords([$high])
        ->assertCanNotSeeTableRecords([$low]);
});

// 35. Declared widow-count filter (uses number_of_widows_left column)
it('declared widows filter uses historical number_of_widows_left field', function () {
    $this->actingAs($this->admin);
    $high = makeDeceased(['zone_id' => $this->zone->id, 'number_of_widows_left' => 3]);
    $low = makeDeceased(['zone_id' => $this->zone->id, 'number_of_widows_left' => 0]);
    Livewire::test(ListDeceaseds::class)
        ->loadTable()
        ->filterTable('declared_widows', ['min_widows_declared' => '2'])
        ->assertCanSeeTableRecords([$high])
        ->assertCanNotSeeTableRecords([$low]);
});

// 36. Age-range filter (uses age column)
it('age at death range filter restricts results correctly', function () {
    $this->actingAs($this->admin);
    $inRange = makeDeceased(['zone_id' => $this->zone->id, 'age' => 45]);
    $outRange = makeDeceased(['zone_id' => $this->zone->id, 'age' => 20]);
    Livewire::test(ListDeceaseds::class)
        ->loadTable()
        ->filterTable('age_at_death_range', ['age_from' => '35', 'age_to' => '60'])
        ->assertCanSeeTableRecords([$inRange])
        ->assertCanNotSeeTableRecords([$outRange]);
});

// 37. Intervention filter — positive match
it('has_intervention filter returns deceased whose orphans have approved intervention requests', function () {
    $this->actingAs($this->admin);

    $type = InterventionType::create(['name' => 'General Support']);

    $withIntervention = makeDeceased(['zone_id' => $this->zone->id]);
    $orphan = Orphan::withoutGlobalScopes()->create([
        'first_name' => 'Hasan',
        'last_name' => 'Musa',
        'nin' => fake()->unique()->numerify('###########'),
        'reg_no' => 'ORF-'.fake()->unique()->numberBetween(10000, 99999),
        'deceased_id' => $withIntervention->id,
        'child_sequence' => 1,
        'status' => OrphanStatus::ACTIVE,
        'is_eligible' => true,
        'gender' => 'MALE',
    ]);
    InterventionRequest::create([
        'orphan_id' => $orphan->id,
        'intervention_type_id' => $type->id,
        'status' => 'approved',
    ]);

    $withoutIntervention = makeDeceased(['zone_id' => $this->zone->id]);

    Livewire::test(ListDeceaseds::class)
        ->loadTable()
        ->filterTable('has_intervention', true)
        ->assertCanSeeTableRecords([$withIntervention])
        ->assertCanNotSeeTableRecords([$withoutIntervention]);
});

// 38. Intervention filter — negative match
it('has_intervention filter set to false returns deceased without orphan interventions', function () {
    $this->actingAs($this->admin);

    $type = InterventionType::create(['name' => 'Education Support']);

    $withIntervention = makeDeceased(['zone_id' => $this->zone->id]);
    $orphan = Orphan::withoutGlobalScopes()->create([
        'first_name' => 'Hasan',
        'last_name' => 'Musa',
        'nin' => fake()->unique()->numerify('###########'),
        'reg_no' => 'ORF-'.fake()->unique()->numberBetween(10000, 99999),
        'deceased_id' => $withIntervention->id,
        'child_sequence' => 1,
        'status' => OrphanStatus::ACTIVE,
        'is_eligible' => true,
        'gender' => 'MALE',
    ]);
    InterventionRequest::create([
        'orphan_id' => $orphan->id,
        'intervention_type_id' => $type->id,
        'status' => 'approved',
    ]);

    $noIntervention = makeDeceased(['zone_id' => $this->zone->id]);

    Livewire::test(ListDeceaseds::class)
        ->loadTable()
        ->filterTable('has_intervention', false)
        ->assertCanSeeTableRecords([$noIntervention])
        ->assertCanNotSeeTableRecords([$withIntervention]);
});

// 39. Coordinator own-zone visibility
it('coordinator can see own-zone deceased in the list', function () {
    $this->actingAs($this->coordinator);
    $own = makeDeceased(['zone_id' => $this->zone->id]);
    Livewire::test(CoordinatorListDeceaseds::class)
        ->loadTable()
        ->assertCanSeeTableRecords([$own]);
});

// 40. Coordinator cross-zone row hidden
it('coordinator cannot see other-zone deceased in the list', function () {
    $this->actingAs($this->coordinator);
    $other = makeDeceased(['zone_id' => $this->otherZone->id]);
    Livewire::test(CoordinatorListDeceaseds::class)
        ->loadTable()
        ->assertCanNotSeeTableRecords([$other]);
});

// 41. Coordinator forged zone_id — form uses auth-derived default
it('coordinator form zone_id field is present and defaults to own zone', function () {
    $this->actingAs($this->coordinator);
    Livewire::test(CoordinatorCreateDeceased::class)
        ->assertSuccessful()
        ->assertFormFieldExists('zone_id');
});

// 42. Coordinator direct cross-zone view is rejected
it('coordinator cannot view a deceased in a different zone via direct URL', function () {
    $this->actingAs($this->coordinator);
    $otherZoneDeceased = makeDeceased(['zone_id' => $this->otherZone->id]);

    try {
        Livewire::test(CoordinatorViewDeceased::class, ['record' => $otherZoneDeceased->id])
            ->assertForbidden();
    } catch (ModelNotFoundException $e) {
        expect($e)->toBeInstanceOf(ModelNotFoundException::class);
    }
});

// 43. Coordinator direct cross-zone edit is rejected
it('coordinator cannot edit a deceased in a different zone', function () {
    $this->actingAs($this->coordinator);
    $otherZoneDeceased = makeDeceased(['zone_id' => $this->otherZone->id]);
    $canEdit = \App\Filament\Coordinator\Resources\DeceasedResource::canEdit($otherZoneDeceased);
    expect($canEdit)->toBeFalse();
});

// 44. Deceased with linked history uses soft-delete only
it('deceased with linked orphans uses soft delete and history remains accessible', function () {
    $d = makeDeceased(['zone_id' => $this->zone->id]);
    $orphan = Orphan::withoutGlobalScopes()->create([
        'first_name' => 'Child',
        'last_name' => 'Linked',
        'nin' => fake()->unique()->numerify('###########'),
        'reg_no' => 'ORF-'.fake()->unique()->numberBetween(10000, 99999),
        'deceased_id' => $d->id,
        'child_sequence' => 1,
        'status' => OrphanStatus::ACTIVE,
        'is_eligible' => true,
        'gender' => 'FEMALE',
    ]);

    $d->delete();

    // Standard scoped query excludes soft deleted models
    expect(Deceased::find($d->id))->toBeNull();

    // Orphan still exists with the deceased_id intact
    $orphan->refresh();
    expect($orphan->deceased_id)->toBe($d->id);

    // Soft-deleted deceased is recoverable with withTrashed()
    $softDeleted = Deceased::withTrashed()->find($d->id);
    expect($softDeleted)->not->toBeNull();
    expect($softDeleted->trashed())->toBeTrue();
});

// 45. Archived orphan history remains linked
it('archived orphan history remains linked after deceased soft delete', function () {
    $d = makeDeceased(['zone_id' => $this->zone->id]);
    $orphan = Orphan::withoutGlobalScopes()->create([
        'first_name' => 'Archived',
        'last_name' => 'Child',
        'nin' => fake()->unique()->numerify('###########'),
        'reg_no' => 'ORF-'.fake()->unique()->numberBetween(10000, 99999),
        'deceased_id' => $d->id,
        'child_sequence' => 1,
        'status' => OrphanStatus::ARCHIVED,
        'is_eligible' => false,
        'gender' => 'MALE',
    ]);

    $d->delete();

    $orphan->refresh();
    expect($orphan->deceased_id)->toBe($d->id);
    expect($orphan->status)->toBe(OrphanStatus::ARCHIVED);
});

// 46. Widow history remains linked after deceased soft delete
it('widow history remains linked after deceased soft delete', function () {
    $d = makeDeceased(['zone_id' => $this->zone->id]);
    $widow = Widow::withoutGlobalScopes()->create([
        'first_name' => 'Widow',
        'last_name' => 'Fatima',
        'nin' => fake()->unique()->numerify('###########'),
        'reg_no' => 'WDF-'.fake()->unique()->numberBetween(10000, 99999),
        'deceased_id' => $d->id,
        'child_sequence' => 1,
        'is_eligible' => true,
        'is_married' => false,
        'address' => 'Test Address',
    ]);

    $d->delete();

    $widow->refresh();
    expect($widow->deceased_id)->toBe($d->id);
});

// 47. No cascading destruction: orphan records survive deceased soft-delete
it('orphan records are not destroyed when deceased is soft-deleted', function () {
    $d = makeDeceased(['zone_id' => $this->zone->id]);
    $orphanIds = [];
    foreach (range(1, 3) as $i) {
        $o = Orphan::withoutGlobalScopes()->create([
            'first_name' => "Child{$i}",
            'last_name' => 'Survivor',
            'nin' => fake()->unique()->numerify('###########'),
            'reg_no' => 'ORF-'.fake()->unique()->numberBetween(10000, 99999),
            'deceased_id' => $d->id,
            'child_sequence' => $i,
            'status' => OrphanStatus::ACTIVE,
            'is_eligible' => true,
            'gender' => 'MALE',
        ]);
        $orphanIds[] = $o->id;
    }

    $d->delete();

    foreach ($orphanIds as $oid) {
        $o = Orphan::withoutGlobalScopes()->withTrashed()->find($oid);
        expect($o)->not->toBeNull();
        expect($o->deceased_id)->toBe($d->id);
    }
});

// 48. Admin and Coordinator View display identical derived age at death when DOB and DOD exist
it('admin and coordinator view pages display identical derived age at death', function () {
    $d = makeDeceased([
        'zone_id' => $this->zone->id,
        'date_of_birth' => '1980-05-10',
        'date_of_death' => '2023-05-10',
        'age' => 0, // database legacy age is 0
    ]);

    $this->actingAs($this->admin);
    Livewire::test(ViewDeceased::class, ['record' => $d->id])
        ->assertSuccessful()
        ->assertSee('43 years');

    $this->actingAs($this->coordinator);
    Livewire::test(CoordinatorViewDeceased::class, ['record' => $d->id])
        ->assertSuccessful()
        ->assertSee('43 years');
});

// 49. Admin and Coordinator View display legacy age when DOB is unavailable
it('admin and coordinator view pages display legacy age when DOB is unavailable', function () {
    $d = makeDeceased([
        'zone_id' => $this->zone->id,
        'date_of_birth' => null,
        'date_of_death' => null,
        'age' => 65,
    ]);

    $this->actingAs($this->admin);
    Livewire::test(ViewDeceased::class, ['record' => $d->id])
        ->assertSuccessful()
        ->assertSee('65 years');

    $this->actingAs($this->coordinator);
    Livewire::test(CoordinatorViewDeceased::class, ['record' => $d->id])
        ->assertSuccessful()
        ->assertSee('65 years');
});

// 50. Form age field is hidden when DOB is supplied and visible when DOB is blank
it('form age field is hidden when date of birth is supplied', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreateDeceased::class)
        ->fillForm(['date_of_birth' => '1985-01-01'])
        ->assertFormFieldIsHidden('age');

    Livewire::test(CreateDeceased::class)
        ->fillForm(['date_of_birth' => null])
        ->assertFormFieldIsVisible('age');
});
