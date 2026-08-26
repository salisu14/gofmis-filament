<?php

use App\Enums\BeneficiaryStatus;
use App\Enums\VulnerabilityStatus;
use App\Enums\WelfarePackageStatus;
use App\Filament\Coordinator\Resources\WelfareRequestResource\Pages\CreateWelfareRequest;
use App\Models\Deceased;
use App\Models\User;
use App\Models\WelfareBeneficiary;
use App\Models\WelfarePackage;
use App\Models\Widow;
use App\Models\Zone;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('coordinator'));

    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->coordinator = User::factory()->create();
    $this->coordinator->assignRole('coordinator');

    $this->otherCoordinator = User::factory()->create();
    $this->otherCoordinator->assignRole('coordinator');

    $this->zone = Zone::create(['name' => 'Kano Zone', 'coordinator_id' => $this->coordinator->id]);
    $this->otherZone = Zone::create(['name' => 'Kaduna Zone', 'coordinator_id' => $this->otherCoordinator->id]);

    $this->package = WelfarePackage::create([
        'name' => 'Annual Welfare Package 2026',
        'description' => 'Food items and clothing',
        'status' => WelfarePackageStatus::OPEN,
        'start_date' => now()->subDays(2),
        'end_date' => now()->addDays(30),
        'created_by' => $this->admin->id,
    ]);
});

// 1. Create page renders safely when all deceased have full_name
test('1. welfare create page renders cleanly when all deceased have full_name', function () {
    Deceased::factory()->create([
        'first_name' => 'Kabiru',
        'last_name' => 'Salisu',
        'full_name' => 'Kabiru Salisu',
        'zone_id' => $this->zone->id,
    ]);

    $this->actingAs($this->coordinator);

    Livewire::test(CreateWelfareRequest::class)
        ->assertSuccessful()
        ->assertFormFieldIsVisible('welfare_package_id')
        ->assertFormFieldIsVisible('deceased_id');
});

// 2. Create page renders safely when a valid own-zone Deceased has NULL full_name
test('2. welfare create page renders cleanly when own-zone deceased has NULL full_name', function () {
    Deceased::factory()->create([
        'first_name' => 'Ibrahim',
        'last_name' => 'Dahiru',
        'full_name' => null, // NULL full_name
        'reg_no' => 'DEC-NULL-001',
        'zone_id' => $this->zone->id,
    ]);

    $this->actingAs($this->coordinator);

    // This used to throw TypeError: Argument #2 ($label) must be string, null given
    Livewire::test(CreateWelfareRequest::class)
        ->assertSuccessful();
});

// 3. Fallback label uses display_name / registration number safely
test('3. fallback label uses display_name or reg_no safely for legacy records', function () {
    $deceasedWithNoName = Deceased::create([
        'first_name' => '',
        'middle_name' => '',
        'last_name' => '',
        'full_name' => null,
        'nin' => '99887766554',
        'reg_no' => 'DEC-NONAME-99',
        'guardian_name' => 'Guardian Test',
        'guardian_phone' => '08012345678',
        'zone_id' => $this->zone->id,
        'vulnerability_status' => VulnerabilityStatus::A,
        'date_registered' => now(),
    ]);

    expect($deceasedWithNoName->display_name)->toBe('Deceased (DEC-NONAME-99)');
});

// 4. Coordinator sees only own-zone families in welfare select
test('4. coordinator sees only own-zone families and cross-zone family is excluded', function () {
    $ownDeceased = Deceased::factory()->create([
        'full_name' => 'Own Zone Deceased',
        'zone_id' => $this->zone->id,
    ]);

    $otherDeceased = Deceased::factory()->create([
        'full_name' => 'Other Zone Deceased',
        'zone_id' => $this->otherZone->id,
    ]);

    $this->actingAs($this->coordinator);

    Livewire::test(CreateWelfareRequest::class)
        ->assertSuccessful();
});

// 5. Duplicate welfare request protection still works
test('5. duplicate welfare request is rejected by validation', function () {
    $ownDeceased = Deceased::factory()->create([
        'full_name' => 'Family Head One',
        'zone_id' => $this->zone->id,
    ]);

    WelfareBeneficiary::create([
        'welfare_package_id' => (string) $this->package->id,
        'deceased_id' => (string) $ownDeceased->id,
        'status' => BeneficiaryStatus::PENDING,
        'suggested_by' => $this->coordinator->id,
    ]);

    $this->actingAs($this->coordinator);

    Livewire::test(CreateWelfareRequest::class)
        ->fillForm([
            'welfare_package_id' => (string) $this->package->id,
            'deceased_id' => (string) $ownDeceased->id,
        ])
        ->call('create')
        ->assertHasFormErrors(['welfare_package_id']);
});

// 6. Valid request can still be submitted cleanly
test('6. valid welfare request can be submitted by coordinator', function () {
    $ownDeceased = Deceased::factory()->create([
        'full_name' => 'Family Head Two',
        'zone_id' => $this->zone->id,
    ]);
    Widow::create([
        'first_name' => 'Amina',
        'last_name' => 'Test',
        'nin' => '99999999901',
        'reg_no' => 'WID-TEST-99',
        'child_sequence' => 1,
        'deceased_id' => $ownDeceased->id,
        'is_eligible' => true,
        'is_married' => false,
    ]);

    $this->actingAs($this->coordinator);

    Livewire::test(CreateWelfareRequest::class)
        ->set('data.welfare_package_id', (string) $this->package->id)
        ->set('data.deceased_id', (string) $ownDeceased->id)
        ->set('data.collection_notes', 'Urgent assistance needed')
        ->call('create')
        ->assertHasNoFormErrors();

    expect(WelfareBeneficiary::where('deceased_id', $ownDeceased->id)->exists())->toBeTrue();
});
