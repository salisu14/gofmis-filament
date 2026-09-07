<?php

use App\Enums\UserStatus;
use App\Enums\VulnerabilityStatus;
use App\Filament\Resources\Deceased\Pages\EditDeceased;
use App\Models\Deceased;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create([
        'is_active' => true,
        'status' => UserStatus::ACTIVE,
    ]);
    $this->admin->assignRole('admin');
    $this->actingAs($this->admin);

    $this->zone = Zone::create(['name' => 'UTF8 Zone']);
});

it('EditDeceased renders a record whose "Other —" cause/place split on a multi-byte em dash', function () {
    // The record's death_cause / death_place used the canonical "Other — *"
    // prefix, whose em dash is a 3-byte UTF-8 character. The old byte-based
    // substr(8) split that character and produced invalid UTF-8 in the form.
    $d = Deceased::create([
        'first_name' => 'Nasiru',
        'last_name' => 'Musa',
        'nin' => '01234567890',
        'has_nin' => true,
        'reg_no' => 'GOF/2026/0037',
        'guardian_name' => 'Mutari Hajara Ali',
        'guardian_phone' => '08026328024',
        'vulnerability_status' => VulnerabilityStatus::C->value,
        'date_registered' => '2026-09-05',
        'address' => "Department of Physics\nFaculty of Science",
        'death_cause' => 'Other — Long illness',
        'death_place' => 'Other — Public Location',
        'occupation' => 'Business',
        'zone_id' => $this->zone->id,
        'number_of_orphans_left' => 0,
        'number_of_widows_left' => 0,
    ]);

    expect(mb_check_encoding($d->date_registered, 'UTF-8'))->toBeTrue();

    Livewire::test(EditDeceased::class, ['record' => $d->id])
        ->assertSuccessful()
        ->assertFormFieldExists('has_nin')
        ->assertSet('data.has_nin', true)
        ->assertSet('data.nin', '01234567890')
        ->assertSee('Has NIN?');
});

it('EditDeceased preserves the basename stored in the other fields without producing invalid UTF-8', function () {
    $d = Deceased::create([
        'first_name' => 'Amina',
        'last_name' => 'Bello',
        'reg_no' => 'GOF/2026/0041',
        'guardian_name' => 'Guardian',
        'guardian_phone' => '08011112222',
        'vulnerability_status' => VulnerabilityStatus::A->value,
        'date_registered' => '2026-09-01',
        'death_cause' => 'Other — Industrial Injury',
        'death_place' => 'Other — Residential Building',
        'zone_id' => $this->zone->id,
        'number_of_orphans_left' => 0,
        'number_of_widows_left' => 0,
    ]);

    Livewire::test(EditDeceased::class, ['record' => $d->id])
        ->assertSuccessful()
        ->assertSet('data.death_cause', 'Other')
        ->assertSet('data.death_cause_other', 'Industrial Injury')
        ->assertSet('data.death_place', 'Other')
        ->assertSet('data.death_place_other', 'Residential Building');

    // The full values round-trip cleanly when saved.
    $d->update([
        'death_cause_other' => 'Industrial Injury',
        'death_place_other' => 'Residential Building',
    ]);
    $d->refresh();
    expect($d->death_cause)->toBe('Other — Industrial Injury');
});

it('EditDeceased renders records that have no NIN / has_nin false without crashing', function () {
    $d = Deceased::create([
        'first_name' => 'Ibrahim',
        'last_name' => 'Danladi',
        'reg_no' => 'GOF/2026/0042',
        'guardian_name' => 'Guardian',
        'guardian_phone' => '08011113333',
        'vulnerability_status' => VulnerabilityStatus::B->value,
        'date_registered' => '2026-09-02',
        'zone_id' => $this->zone->id,
        'number_of_orphans_left' => 0,
        'number_of_widows_left' => 0,
    ]);

    Livewire::test(EditDeceased::class, ['record' => $d->id])
        ->assertSuccessful()
        ->assertFormFieldExists('has_nin')
        ->assertSet('data.has_nin', false)
        ->assertSet('data.nin', null);
});
