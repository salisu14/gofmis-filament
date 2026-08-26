<?php

use App\Enums\VulnerabilityStatus;
use App\Filament\Resources\Deceased\Pages\ListDeceaseds;
use App\Models\Deceased;
use App\Models\User;
use App\Models\Zone;
use App\Models\Widow;
use App\Models\Orphan;
use App\Enums\Gender;
use App\Enums\OrphanStatus;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin'], ['id' => Str::uuid(), 'uuid' => Str::uuid()]);
    Role::firstOrCreate(['name' => 'super_admin'], ['id' => Str::uuid(), 'uuid' => Str::uuid()]);
    Role::firstOrCreate(['name' => 'coordinator'], ['id' => Str::uuid(), 'uuid' => Str::uuid()]);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->zone = Zone::create(['name' => 'Zone A']);
});

function makeDeceased(array $attrs = []): Deceased
{
    return Deceased::create(array_merge([
        'first_name' => 'Adamu',
        'last_name' => 'Bello',
        'nin' => '90000000001',
        'reg_no' => 'UAT-DEC-001',
        'guardian_name' => 'Guardian',
        'guardian_phone' => '08012345678',
        'vulnerability_status' => VulnerabilityStatus::B,
        'date_registered' => now()->toDateString(),
        'zone_id' => \App\Models\Zone::first()?->id,
    ], $attrs));
}

test('deceased list renders the full name column value', function () {
    $deceased = makeDeceased(['full_name' => 'Adamu Bello']);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($this->admin);

    Livewire::test(ListDeceaseds::class)
        ->loadTable()
        ->assertCanSeeTableRecords([$deceased]);

    // The Full name column must resolve to the canonical displayed name.
    $component = Livewire::test(ListDeceaseds::class)->loadTable();

    $column = $component->instance()->getTable()->getColumn('full_name');
    $state = $column->getStateFromRecord($deceased);

    expect($state)->toBe('Adamu Bello');
});

test('deceased list full name column renders name even when full_name column is blank', function () {
    // Regression: legacy/baseline records can have a blank full_name column
    // while first/last names are populated. The Full Name column must still
    // render the canonical display_name (accessor fallback), not a blank cell.
    $deceased = Deceased::create([
        'first_name' => 'Adamu',
        'last_name' => 'Bello',
        'full_name' => null,
        'nin' => '90000000002',
        'reg_no' => 'UAT-DEC-004',
        'guardian_name' => 'Guardian',
        'guardian_phone' => '08012345678',
        'vulnerability_status' => VulnerabilityStatus::A,
        'date_registered' => now()->toDateString(),
        'zone_id' => $this->zone->id,
    ]);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($this->admin);

    $component = Livewire::test(ListDeceaseds::class)->loadTable();

    $column = $component->instance()->getTable()->getColumn('full_name');
    $state = $column->getStateFromRecord($deceased);

    expect($state)->toBe('Adamu Bello');
});
