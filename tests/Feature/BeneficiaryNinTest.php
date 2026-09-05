<?php

use App\Enums\VulnerabilityStatus;
use App\Filament\Resources\Deceased\Pages\CreateDeceased;
use App\Filament\Resources\Widows\Pages\CreateWidow;
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

    $this->zone = Zone::create(['name' => 'NIN Domain Zone '.rand(1000, 9999)]);

    $this->deceased = Deceased::create([
        'first_name' => 'Base',
        'last_name' => 'Household',
        'nin' => fake()->unique()->numerify('###########'),
        'reg_no' => 'DEC-'.fake()->unique()->numberBetween(10000, 99999),
        'guardian_name' => 'G',
        'guardian_phone' => '08012345678',
        'vulnerability_status' => VulnerabilityStatus::A,
        'date_registered' => now()->toDateString(),
        'date_of_death' => now()->toDateString(),
        'number_of_orphans_left' => 0,
        'number_of_widows_left' => 0,
        'zone_id' => $this->zone->id,
        'has_nin' => true,
    ]);
});

it('derives has_nin from nin when has_nin is not explicitly set', function () {
    $d = Deceased::create([
        'first_name' => 'A',
        'last_name' => 'B',
        'nin' => '12345678901',
        'reg_no' => 'DEC-'.fake()->unique()->numberBetween(10000, 99999),
        'guardian_name' => 'G',
        'guardian_phone' => '08012345678',
        'vulnerability_status' => VulnerabilityStatus::A,
        'date_registered' => now()->toDateString(),
        'date_of_death' => now()->toDateString(),
        'number_of_orphans_left' => 0,
        'number_of_widows_left' => 0,
        'zone_id' => $this->zone->id,
    ]);

    expect($d->has_nin)->toBeTrue()
        ->and($d->nin)->toBe('12345678901');
});

it('forces nin to null when has_nin is false', function () {
    $w = Widow::create([
        'first_name' => 'A',
        'last_name' => 'W',
        'deceased_id' => Deceased::factory()->create()->id,
        'child_sequence' => 1,
        'reg_no' => 'WID-'.fake()->unique()->numberBetween(10000, 99999),
        'is_eligible' => true,
        'is_married' => false,
    ]);

    $w->update(['nin' => '99999999999', 'has_nin' => false]);

    expect($w->fresh()->nin)->toBeNull()
        ->and($w->fresh()->has_nin)->toBeFalse();
});

it('preserves nin leading zeros when has_nin is true', function () {
    $o = Orphan::create([
        'first_name' => 'A',
        'last_name' => 'O',
        'gender' => 'MALE',
        'birth_date' => now()->subYears(10)->toDateString(),
        'deceased_id' => Deceased::factory()->create()->id,
        'child_sequence' => 1,
        'reg_no' => 'ORP-'.fake()->unique()->numberBetween(10000, 99999),
    ]);

    $o->update(['nin' => '09876543210', 'has_nin' => true]);

    expect($o->fresh()->nin)->toBe('09876543210')
        ->and($o->fresh()->has_nin)->toBeTrue();
});

it('allows multiple NULL nin values and keeps non-null unique rows divergent', function () {
    // Seed one row that holds a concrete NIN.
    Orphan::create([
        'first_name' => 'Holder',
        'last_name' => 'NIN',
        'gender' => 'MALE',
        'birth_date' => now()->subYears(10)->toDateString(),
        'deceased_id' => $this->deceased->id,
        'child_sequence' => 1,
        'reg_no' => 'ORP-'.fake()->unique()->numberBetween(10000, 99999),
        'nin' => '12345678901',
        'has_nin' => true,
    ]);

    // Multiple rows may share a NULL nin.
    foreach (range(2, 4) as $i) {
        Orphan::create([
            'first_name' => 'A',
            'last_name' => 'O'.$i,
            'gender' => 'MALE',
            'birth_date' => now()->subYears(10)->toDateString(),
            'deceased_id' => $this->deceased->id,
            'child_sequence' => $i,
            'reg_no' => 'ORP-'.fake()->unique()->numberBetween(10000, 99999),
        ]);
    }

    // A second non-null row colliding on the same NIN must be rejected by the
    // database unique index.
    expect(fn () => Orphan::create([
        'first_name' => 'Dup',
        'last_name' => 'NIN',
        'gender' => 'MALE',
        'birth_date' => now()->subYears(10)->toDateString(),
        'deceased_id' => $this->deceased->id,
        'child_sequence' => 99,
        'reg_no' => 'ORP-'.fake()->unique()->numberBetween(10000, 99999),
        'nin' => '12345678901',
        'has_nin' => true,
    ]))->toThrow(\Illuminate\Database\QueryException::class);

    expect(Orphan::whereNull('nin')->count())->toBe(3);
});

it('has_nin and nin are persisted through the deceased create action', function () {
    Livewire::test(CreateDeceased::class)
        ->set('data.first_name', 'John')
        ->set('data.last_name', 'Doe')
        ->set('data.has_nin', true)
        ->set('data.nin', '01234567890')
        ->set('data.vulnerability_status', VulnerabilityStatus::A->value)
        ->set('data.zone_id', $this->zone->id)
        ->set('data.guardian_name', 'G')
        ->set('data.date_registered', now()->toDateString())
        ->set('data.date_of_death', now()->toDateString())
        ->set('data.number_of_widows_left', 0)
        ->set('data.number_of_orphans_left', 0)
        ->call('create')
        ->assertHasNoFormErrors();

    $d = Deceased::where('nin', '01234567890')->first();
    expect($d)->not->toBeNull()
        ->and($d->has_nin)->toBeTrue();
});

it('turning has_nin off on create nulls nin so it is not persisted', function () {
    Livewire::test(CreateWidow::class)
        ->set('data.deceased_id', $this->deceased->id)
        ->set('data.first_name', 'W')
        ->set('data.last_name', 'X')
        ->set('data.has_nin', true)
        ->set('data.nin', '55555555555')
        ->set('data.address', 'Addr')
        ->set('data.has_nin', false)
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Widow::where('nin', '55555555555')->exists())->toBeFalse();
});
