<?php

use App\Enums\UserStatus;
use App\Filament\Imports\DeceasedImporter;
use App\Filament\Imports\OrphanImporter;
use App\Filament\Imports\WidowImporter;
use App\Models\Deceased;
use App\Models\Orphan;
use App\Models\User;
use App\Models\Widow;
use App\Models\Zone;
use App\Services\MfaService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\ZonesTableSeeder;
use Filament\Actions\Imports\Models\Import;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(ZonesTableSeeder::class);

    $this->admin = User::factory()->create([
        'is_active' => true,
        'status' => UserStatus::ACTIVE,
    ]);
    $this->admin->assignRole('super_admin');

    // super_admin is an MFA-mandatory role, so a raw HTTP request through the
    // admin panel must be backed by an enabled 2FA secret and a verified
    // MFA session, otherwise the middleware redirects to MFA enrollment.
    $mfa = new MfaService;
    $this->admin->saveAppAuthenticationSecret($mfa->generateSecret());
    $this->admin->update([
        'mfa_confirmed_at' => now(),
        'mfa_enabled_at' => now(),
    ]);

    session()->put('mfa_verified_at', time());
    session()->put('mfa_verified_user_id', $this->admin->id);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($this->admin);

    $this->zone = Zone::first();
});

it('renders the beneficiary index pages and exposes the export action to a super admin', function () {
    $this->get(\App\Filament\Resources\Deceased\DeceasedResource::getUrl('index'))
        ->assertSuccessful();
    $this->get(\App\Filament\Resources\Widows\WidowResource::getUrl('index'))
        ->assertSuccessful();
    $this->get(\App\Filament\Resources\Orphans\OrphanResource::getUrl('index'))
        ->assertSuccessful();
});

it('exports are wired and gated by the export_* permission on every beneficiary resource', function () {
    Livewire::test(\App\Filament\Resources\Deceased\Pages\ListDeceaseds::class)
        ->assertSuccessful();

    Livewire::test(\App\Filament\Resources\Widows\Pages\ListWidows::class)
        ->assertSuccessful();

    Livewire::test(\App\Filament\Resources\Orphans\Pages\ListOrphans::class)
        ->assertSuccessful();

    // The super_admin role owns the export/import permissions, so the actions
    // are present on each list page.
    expect($this->admin->can('export_deceased'))->toBeTrue()
        ->and($this->admin->can('export_widows'))->toBeTrue()
        ->and($this->admin->can('export_orphans'))->toBeTrue()
        ->and($this->admin->can('import_deceased'))->toBeTrue()
        ->and($this->admin->can('import_widows'))->toBeTrue()
        ->and($this->admin->can('import_orphans'))->toBeTrue();
});

it('imports a deceased CSV row and derives has_nin from the nin value', function () {
    $deceased = Deceased::factory()->create(['zone_id' => $this->zone->id]);

    $import = Import::create([
        'file_name' => 'deceased.csv',
        'file_disk' => 'local',
        'user_id' => $this->admin->id,
        'importer' => DeceasedImporter::class,
        'total_rows' => 1,
    ]);

    $importer = new DeceasedImporter(
        $import,
        collect(DeceasedImporter::getColumns())->mapWithKeys(fn ($c) => [$c->getName() => $c->getLabel() ?? $c->getName()])->all(),
        []
    );

    $importer([
        'reg_no' => 'DEC/IMP/0001',
        'nin' => '01234567890',
        'first_name' => 'Imported',
        'last_name' => 'Person',
        'guardian_name' => 'Guardian',
        'guardian_phone' => '08011112222',
        'vulnerability_status' => 'High (B)',
        'date_registered' => now()->toDateString(),
        'number_of_orphans_left' => 0,
        'number_of_widows_left' => 0,
        'zone' => $this->zone->name,
    ]);

    $row = Deceased::where('reg_no', 'DEC/IMP/0001')->first();
    expect($row)->not->toBeNull()
        ->and($row->nin)->toBe('01234567890')
        ->and($row->has_nin)->toBeTrue()
        ->and($row->zone_id)->toBe($this->zone->id)
        ->and($row->vulnerability_status->value)->toBe('B');
});

it('imports an orphan CSV row and preserves leading zeros in the nin', function () {
    $deceased = Deceased::factory()->create(['zone_id' => $this->zone->id]);

    $import = Import::create([
        'file_name' => 'orphan.csv',
        'file_disk' => 'local',
        'user_id' => $this->admin->id,
        'importer' => OrphanImporter::class,
        'total_rows' => 1,
    ]);

    $importer = new OrphanImporter(
        $import,
        collect(OrphanImporter::getColumns())->mapWithKeys(fn ($c) => [$c->getName() => $c->getLabel() ?? $c->getName()])->all(),
        []
    );

    $importer([
        'reg_no' => 'ORP/IMP/0001',
        'nin' => '09876543210',
        'first_name' => 'Imported',
        'last_name' => 'Child',
        'deceased' => $deceased->reg_no,
        'gender' => 'Female',
        'birth_date' => now()->subYears(10)->toDateString(),
    ]);

    $row = Orphan::withoutGlobalScopes()->where('reg_no', 'ORP/IMP/0001')->first();
    expect($row)->not->toBeNull()
        ->and($row->nin)->toBe('09876543210')
        ->and($row->has_nin)->toBeTrue()
        ->and($row->gender->value)->toBe('FEMALE')
        ->and((string) $row->deceased_id)->toBe((string) $deceased->id);
});

it('imports a widow CSV row and links it to the resolved deceased', function () {
    $deceased = Deceased::factory()->create(['zone_id' => $this->zone->id]);

    $import = Import::create([
        'file_name' => 'widow.csv',
        'file_disk' => 'local',
        'user_id' => $this->admin->id,
        'importer' => WidowImporter::class,
        'total_rows' => 1,
    ]);

    $importer = new WidowImporter(
        $import,
        collect(WidowImporter::getColumns())->mapWithKeys(fn ($c) => [$c->getName() => $c->getLabel() ?? $c->getName()])->all(),
        []
    );

    $importer([
        'reg_no' => 'WID/IMP/0001',
        'nin' => '12345678901',
        'first_name' => 'Imported',
        'last_name' => 'Widow',
        'deceased' => $deceased->reg_no,
        'is_eligible' => 'yes',
        'is_married' => 'no',
        'child_sequence' => 1,
    ]);

    $row = Widow::withoutGlobalScopes()->where('reg_no', 'WID/IMP/0001')->first();
    expect($row)->not->toBeNull()
        ->and($row->nin)->toBe('12345678901')
        ->and($row->has_nin)->toBeTrue()
        ->and((string) $row->deceased_id)->toBe((string) $deceased->id)
        ->and($row->child_sequence)->toBe(1);
});
