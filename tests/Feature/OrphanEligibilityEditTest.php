<?php

use App\Enums\Gender;
use App\Enums\OrphanStatus;
use App\Enums\UserStatus;
use App\Filament\Resources\Orphans\Pages\EditOrphan;
use App\Models\Deceased;
use App\Models\Orphan;
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

    $this->zone = Zone::create(['name' => 'Orphan Eligibility Zone']);

    $this->deceased = Deceased::create([
        'first_name' => 'Parent',
        'last_name' => 'Deceased',
        'reg_no' => 'DEC-'.fake()->unique()->numberBetween(10000, 99999),
        'guardian_name' => 'Guardian',
        'guardian_phone' => '08012345678',
        'vulnerability_status' => \App\Enums\VulnerabilityStatus::A->value,
        'date_registered' => now()->toDateString(),
        'date_of_death' => now()->subMonth()->toDateString(),
        'number_of_orphans_left' => 1,
        'number_of_widows_left' => 0,
        'zone_id' => $this->zone->id,
    ]);
});

function orphanFor(array $attrs = []): Orphan
{
    $deceased = Deceased::latest('id')->first();

    return Orphan::create(array_merge([
        'first_name' => 'Amina',
        'last_name' => 'Child',
        'gender' => Gender::FEMALE,
        'birth_date' => now()->subYears(12)->toDateString(),
        'deceased_id' => $deceased->id,
        'child_sequence' => 1,
        'reg_no' => 'ORP-'.fake()->unique()->numberBetween(10000, 99999),
        'status' => OrphanStatus::ACTIVE,
        'is_eligible' => true,
        'address' => 'Somewhere',
    ], $attrs));
}

it('an Admin can toggle Eligible for Support from false to true and persist it', function () {
    $this->actingAs($this->admin);
    // PENDING_REVIEW orphans default to is_eligible = false; without a terminal
    // status they must remain editable so staff can re-enable eligibility.
    $orphan = orphanFor([
        'status' => OrphanStatus::PENDING_REVIEW,
        'is_eligible' => false,
    ]);

    Livewire::test(EditOrphan::class, ['record' => $orphan->id])
        ->assertSuccessful()
        ->assertFormFieldExists('is_eligible')
        ->assertFormFieldIsEnabled('is_eligible')
        ->assertSet('data.is_eligible', false)
        ->set('data.is_eligible', true)
        ->call('save')
        ->assertHasNoFormErrors();

    expect($orphan->fresh()->is_eligible)->toBeTrue();
});

it('an Admin can keep the field editable and re-enable it when ineligible but not archived', function () {
    $this->actingAs($this->admin);
    // Temporarily ineligible (e.g. missing docs) but not archived -> editable.
    $orphan = orphanFor([
        'status' => OrphanStatus::ACTIVE,
        'is_eligible' => false,
    ]);

    Livewire::test(EditOrphan::class, ['record' => $orphan->id])
        ->assertFormFieldIsEnabled('is_eligible')
        ->set('data.is_eligible', true)
        ->call('save')
        ->assertHasNoFormErrors();

    expect($orphan->fresh()->is_eligible)->toBeTrue();
});

it('an Admin can disable eligibility on an active orphan and persist it', function () {
    $this->actingAs($this->admin);
    $orphan = orphanFor(['is_eligible' => true]);

    Livewire::test(EditOrphan::class, ['record' => $orphan->id])
        ->assertFormFieldIsEnabled('is_eligible')
        ->set('data.is_eligible', false)
        ->call('save')
        ->assertHasNoFormErrors();

    expect($orphan->fresh()->is_eligible)->toBeFalse();
});

it('an archived orphan keeps eligibility locked', function () {
    $this->actingAs($this->admin);
    $orphan = orphanFor([
        'status' => OrphanStatus::ARCHIVED,
        'is_eligible' => false,
    ]);

    Livewire::test(EditOrphan::class, ['record' => $orphan->id])
        ->assertSuccessful()
        ->assertFormFieldIsDisabled('is_eligible');
});

it('an unauthorized (non-admin) user cannot edit an orphan via the policy', function () {
    $orphan = orphanFor();

    $coordinator = User::factory()->create(['is_active' => true, 'status' => UserStatus::ACTIVE]);
    $coordinator->assignRole('coordinator');
    $coordinator->coordinatedZone()->save($this->zone);

    $this->actingAs($coordinator);

    // The policy denies update for non-admins, so it can never be bypassed.
    $canUpdate = app(\App\Policies\OrphanPolicy::class)->update($coordinator, $orphan);
    expect($canUpdate)->toBeFalse();

    // The Admin edit page (with the editable eligibility toggle) is therefore
    // not reachable by a coordinator via the admin resource's edit route.
    Livewire::test(EditOrphan::class, ['record' => $orphan->id])
        ->assertStatus(403);
});

it('the domain workflow still auto-archives an over-aged male orphan on save', function () {
    $this->actingAs($this->admin);
    // A currently-active young male orphan remains editable; if staff later
    // advances the birth date beyond 18 the model hook must archive + mark
    // ineligible on save instead of leaving an eligible adult record.
    $orphan = orphanFor([
        'gender' => Gender::MALE,
        'birth_date' => now()->subYears(10)->toDateString(),
        'status' => OrphanStatus::ACTIVE,
        'is_eligible' => true,
    ]);

    Livewire::test(EditOrphan::class, ['record' => $orphan->id])
        ->assertSuccessful()
        ->assertFormFieldIsEnabled('is_eligible')
        ->set('data.birth_date', now()->subYears(19)->toDateString())
        ->call('save')
        ->assertHasNoFormErrors();

    $orphan->refresh();
    expect($orphan->status)->toBe(OrphanStatus::ARCHIVED)
        ->and($orphan->is_eligible)->toBeFalse();
});
