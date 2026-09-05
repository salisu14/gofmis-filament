<?php

use App\Enums\OrphanStatus;
use App\Enums\VulnerabilityStatus;
use App\Filament\Resources\Deceased\Pages\CreateDeceased;
use App\Filament\Resources\Deceased\Pages\EditDeceased;
use App\Filament\Resources\Orphans\Pages\CreateOrphan;
use App\Filament\Resources\Orphans\Pages\EditOrphan;
use App\Filament\Resources\Widows\Pages\CreateWidow;
use App\Filament\Resources\Widows\Pages\EditWidow;
use App\Models\Deceased;
use App\Models\Orphan;
use App\Models\User;
use App\Models\Widow;
use App\Models\Zone;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    $this->actingAs($this->admin);

    $this->zone = Zone::create(['name' => 'NIN Zone '.rand(1000, 9999)]);

    $this->deceased = Deceased::create([
        'first_name' => 'Alhaji',
        'last_name' => 'Deceased',
        'date_of_death' => now()->subMonth()->toDateString(),
        'nin' => fake()->unique()->numerify('###########'),
        'reg_no' => 'DEC-'.fake()->unique()->numberBetween(10000, 99999),
        'guardian_name' => 'Guardian',
        'guardian_phone' => '08012345678',
        'vulnerability_status' => VulnerabilityStatus::A,
        'date_registered' => now()->toDateString(),
        'number_of_orphans_left' => 0,
        'number_of_widows_left' => 0,
        'zone_id' => $this->zone->id,
        'has_nin' => true,
    ]);

    $this->widow = Widow::create([
        'first_name' => 'Fatima',
        'last_name' => 'Widow',
        'nin' => fake()->unique()->numerify('###########'),
        'reg_no' => 'WID-'.fake()->unique()->numberBetween(10000, 99999),
        'deceased_id' => $this->deceased->id,
        'child_sequence' => 1,
        'is_eligible' => true,
        'is_married' => false,
        'address' => 'NIN address',
        'has_nin' => true,
    ]);

    $this->orphan = Orphan::create([
        'first_name' => 'Child',
        'last_name' => 'Orphan',
        'gender' => 'MALE',
        'birth_date' => now()->subYears(10)->toDateString(),
        'nin' => fake()->unique()->numerify('###########'),
        'reg_no' => 'ORP-'.fake()->unique()->numberBetween(10000, 99999),
        'deceased_id' => $this->deceased->id,
        'child_sequence' => 1,
        'status' => OrphanStatus::ACTIVE,
        'is_eligible' => true,
        'address' => 'Orphan NIN address',
        'has_nin' => true,
    ]);
});

it('renders the literal Has NIN? label on all three admin create pages', function () {
    Livewire::test(CreateDeceased::class)
        ->assertSuccessful()
        ->assertSee('Has NIN?');

    Livewire::test(CreateWidow::class)
        ->assertSuccessful()
        ->assertSee('Has NIN?');

    Livewire::test(CreateOrphan::class)
        ->assertSuccessful()
        ->assertSee('Has NIN?');
});

it('deceased create form has Has NIN? field and defaults OFF', function () {
    Livewire::test(CreateDeceased::class)
        ->assertFormFieldExists('has_nin')
        ->assertFormFieldIsVisible('has_nin')
        ->assertFormFieldIsHidden('nin');
});

it('deceased toggle ON reveals and requires NIN, OFF hides and clears it', function () {
    Livewire::test(CreateDeceased::class)
        ->set('data.has_nin', true)
        ->assertFormFieldIsVisible('nin')
        ->call('create')
        ->assertHasFormErrors(['nin']);

    Livewire::test(CreateDeceased::class)
        ->set('data.has_nin', true)
        ->set('data.nin', '12345678901')
        ->assertFormFieldIsVisible('nin')
        ->set('data.has_nin', false)
        ->assertFormFieldIsHidden('nin')
        ->assertSet('data.nin', null);
});

it('deceased valid 11-digit NIN saves and preserves leading zero', function () {
    $this->withoutExceptionHandling();

    Livewire::test(CreateDeceased::class)
        ->set('data.first_name', 'John')
        ->set('data.last_name', 'Doe')
        ->set('data.has_nin', true)
        ->set('data.nin', '01234567890')
        ->set('data.vulnerability_status', VulnerabilityStatus::A->value)
        ->set('data.zone_id', $this->zone->id)
        ->set('data.guardian_name', 'Guardian')
        ->set('data.guardian_phone', '08012345678')
        ->set('data.date_registered', now()->toDateString())
        ->set('data.date_of_death', now()->toDateString())
        ->set('data.number_of_widows_left', 0)
        ->set('data.number_of_orphans_left', 0)
        ->set('data.address', 'NIN test address')
        ->call('create')
        ->assertHasNoFormErrors();

    $saved = Deceased::where('nin', '01234567890')->first();
    expect($saved)->not->toBeNull()
        ->and($saved->nin)->toBe('01234567890')
        ->and($saved->has_nin)->toBeTrue();
});

it('deceased invalid length and non-digit NIN are rejected', function () {
    Livewire::test(CreateDeceased::class)
        ->set('data.has_nin', true)
        ->set('data.nin', '12345')
        ->call('create')
        ->assertHasFormErrors(['nin']);

    Livewire::test(CreateDeceased::class)
        ->set('data.has_nin', true)
        ->set('data.nin', 'abcdefghijk')
        ->call('create')
        ->assertHasFormErrors(['nin']);
});

it('deceased duplicate NIN is rejected', function () {
    Livewire::test(CreateDeceased::class)
        ->set('data.first_name', 'John')
        ->set('data.last_name', 'Dup')
        ->set('data.has_nin', true)
        ->set('data.nin', $this->deceased->nin)
        ->set('data.vulnerability_status', VulnerabilityStatus::A->value)
        ->set('data.zone_id', $this->zone->id)
        ->set('data.guardian_name', 'Guardian')
        ->set('data.guardian_phone', '08012345678')
        ->set('data.date_registered', now()->toDateString())
        ->set('data.date_of_death', now()->toDateString())
        ->set('data.number_of_widows_left', 0)
        ->set('data.number_of_orphans_left', 0)
        ->call('create')
        ->assertHasFormErrors(['nin']);
});

it('deceased edit allows own NIN and turning OFF saves nin as NULL', function () {
    Livewire::test(EditDeceased::class, ['record' => $this->deceased->id])
        ->assertFormFieldExists('has_nin')
        ->assertFormFieldIsVisible('has_nin')
        ->set('data.has_nin', false)
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->deceased->fresh()->nin)->toBeNull()
        ->and($this->deceased->fresh()->has_nin)->toBeFalse();
});

it('widow create form has Has NIN? field and defaults OFF', function () {
    Livewire::test(CreateWidow::class)
        ->assertFormFieldExists('has_nin')
        ->assertFormFieldIsVisible('has_nin')
        ->assertFormFieldIsHidden('nin');
});

it('widow toggle ON reveals and requires NIN, OFF hides and clears it', function () {
    Livewire::test(CreateWidow::class)
        ->set('data.has_nin', true)
        ->assertFormFieldIsVisible('nin')
        ->call('create')
        ->assertHasFormErrors(['nin']);

    Livewire::test(CreateWidow::class)
        ->set('data.has_nin', true)
        ->set('data.nin', '12345678901')
        ->set('data.has_nin', false)
        ->assertFormFieldIsHidden('nin')
        ->assertSet('data.nin', null);
});

it('widow valid 11-digit NIN saves and preserves leading zero', function () {
    $this->withoutExceptionHandling();

    Livewire::test(CreateWidow::class)
        ->set('data.deceased_id', $this->deceased->id)
        ->set('data.first_name', 'Aisha')
        ->set('data.last_name', 'WidowTwo')
        ->set('data.has_nin', true)
        ->set('data.nin', '09876543210')
        ->set('data.address', 'Address')
        ->call('create')
        ->assertHasNoFormErrors();

    $saved = Widow::where('nin', '09876543210')->first();
    expect($saved)->not->toBeNull()
        ->and($saved->nin)->toBe('09876543210')
        ->and($saved->has_nin)->toBeTrue();
});

it('widow invalid length and non-digit NIN are rejected', function () {
    Livewire::test(CreateWidow::class)
        ->set('data.deceased_id', $this->deceased->id)
        ->set('data.first_name', 'Aisha')
        ->set('data.last_name', 'WidowBad')
        ->set('data.has_nin', true)
        ->set('data.nin', '123')
        ->call('create')
        ->assertHasFormErrors(['nin']);

    Livewire::test(CreateWidow::class)
        ->set('data.deceased_id', $this->deceased->id)
        ->set('data.first_name', 'Aisha')
        ->set('data.last_name', 'WidowBad2')
        ->set('data.has_nin', true)
        ->set('data.nin', 'xxxxxxxxxxx')
        ->call('create')
        ->assertHasFormErrors(['nin']);
});

it('widow duplicate NIN under same deceased is rejected', function () {
    Livewire::test(CreateWidow::class)
        ->set('data.deceased_id', $this->deceased->id)
        ->set('data.first_name', 'Aisha')
        ->set('data.last_name', 'WidowDup')
        ->set('data.has_nin', true)
        ->set('data.nin', $this->widow->nin)
        ->set('data.address', 'Address')
        ->call('create')
        ->assertHasFormErrors(['nin']);
});

it('widow edit allows own NIN and turning OFF saves nin as NULL', function () {
    Livewire::test(EditWidow::class, ['record' => $this->widow->id])
        ->assertFormFieldExists('has_nin')
        ->assertFormFieldIsVisible('has_nin')
        ->set('data.has_nin', false)
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->widow->fresh()->nin)->toBeNull()
        ->and($this->widow->fresh()->has_nin)->toBeFalse();
});

it('orphan create form has Has NIN? field and defaults OFF', function () {
    Livewire::test(CreateOrphan::class, ['deceased_id' => $this->deceased->id])
        ->assertFormFieldExists('has_nin')
        ->assertFormFieldIsVisible('has_nin')
        ->assertFormFieldIsHidden('nin');
});

it('orphan toggle ON reveals and requires NIN, OFF hides and clears it', function () {
    Livewire::test(CreateOrphan::class)
        ->set('data.has_nin', true)
        ->assertFormFieldIsVisible('nin')
        ->call('create')
        ->assertHasFormErrors(['nin']);

    Livewire::test(CreateOrphan::class)
        ->set('data.has_nin', true)
        ->set('data.nin', '12345678901')
        ->set('data.has_nin', false)
        ->assertFormFieldIsHidden('nin')
        ->assertSet('data.nin', null);
});

it('orphan valid 11-digit NIN saves and preserves leading zero', function () {
    $this->withoutExceptionHandling();

    Livewire::test(CreateOrphan::class)
        ->set('data.deceased_id', $this->deceased->id)
        ->set('data.first_name', 'Zainab')
        ->set('data.last_name', 'OrphanTwo')
        ->set('data.gender', 'FEMALE')
        ->set('data.birth_date', now()->subYears(12)->toDateString())
        ->set('data.has_nin', true)
        ->set('data.nin', '01122334455')
        ->set('data.address', 'Address')
        ->call('create')
        ->assertHasNoFormErrors();

    $saved = Orphan::where('nin', '01122334455')->first();
    expect($saved)->not->toBeNull()
        ->and($saved->nin)->toBe('01122334455')
        ->and($saved->has_nin)->toBeTrue();
});

it('orphan invalid length and non-digit NIN are rejected', function () {
    Livewire::test(CreateOrphan::class)
        ->set('data.deceased_id', $this->deceased->id)
        ->set('data.first_name', 'Zainab')
        ->set('data.last_name', 'OrphanBad')
        ->set('data.gender', 'FEMALE')
        ->set('data.birth_date', now()->subYears(12)->toDateString())
        ->set('data.has_nin', true)
        ->set('data.nin', '12')
        ->call('create')
        ->assertHasFormErrors(['nin']);

    Livewire::test(CreateOrphan::class)
        ->set('data.deceased_id', $this->deceased->id)
        ->set('data.first_name', 'Zainab')
        ->set('data.last_name', 'OrphanBad2')
        ->set('data.gender', 'FEMALE')
        ->set('data.birth_date', now()->subYears(12)->toDateString())
        ->set('data.has_nin', true)
        ->set('data.nin', 'abcdefghijk')
        ->call('create')
        ->assertHasFormErrors(['nin']);
});

it('orphan duplicate NIN is rejected', function () {
    Livewire::test(CreateOrphan::class)
        ->set('data.deceased_id', $this->deceased->id)
        ->set('data.first_name', 'Zainab')
        ->set('data.last_name', 'OrphanDup')
        ->set('data.gender', 'FEMALE')
        ->set('data.birth_date', now()->subYears(12)->toDateString())
        ->set('data.has_nin', true)
        ->set('data.nin', $this->orphan->nin)
        ->set('data.address', 'Address')
        ->call('create')
        ->assertHasFormErrors(['nin']);
});

it('orphan edit allows own NIN and turning OFF saves nin as NULL', function () {
    Livewire::test(EditOrphan::class, ['record' => $this->orphan->id])
        ->assertFormFieldExists('has_nin')
        ->assertFormFieldIsVisible('has_nin')
        ->set('data.has_nin', false)
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->orphan->fresh()->nin)->toBeNull()
        ->and($this->orphan->fresh()->has_nin)->toBeFalse();
});
