<?php

use App\Enums\BeneficiaryStatus;
use App\Enums\VulnerabilityStatus;
use App\Enums\WelfarePackageStatus;
use App\Filament\Coordinator\Resources\WelfareRequestResource;
use App\Filament\Coordinator\Resources\WelfareRequestResource\Pages\CreateWelfareRequest;
use App\Models\Deceased;
use App\Models\User;
use App\Models\WelfareBeneficiary;
use App\Models\WelfarePackage;
use App\Models\Widow;
use App\Models\Zone;
use Filament\Facades\Filament;
use Livewire\Livewire;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

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

    $this->coordinator->refresh();
    $this->otherCoordinator->refresh();

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
        ->set('data.welfare_package_id', (string) $this->package->id)
        ->set('data.deceased_id', (string) $ownDeceased->id)
        ->call('create')
        ->assertHasFormErrors(['welfare_package_id']);
});

// 6. Valid request can still be submitted cleanly
test('6. valid welfare request can be submitted by coordinator with create permission', function () {
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

// --- RBAC AUTHORIZATION ENFORCEMENT TESTS ---

test('7. coordinator WITH default view and create permissions can access welfare requests and submit nominations', function () {
    $this->actingAs($this->coordinator);

    expect(WelfareRequestResource::canViewAny())->toBeTrue();
    expect(WelfareRequestResource::canCreate())->toBeTrue();

    $this->get('/coordinator/welfare-requests')->assertStatus(200);
});

test('8. coordinator WITHOUT view_welfare_interventions is denied navigation and list/view access', function () {
    $role = \App\Models\Role::findByName('coordinator');
    $perm = \App\Models\Permission::findByName('view_welfare_interventions');
    $role->revokePermissionTo($perm);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $coordinator = User::factory()->create();
    $coordinator->assignRole('coordinator');
    Zone::create(['name' => 'Test Revoke View Zone', 'coordinator_id' => $coordinator->id]);

    $this->actingAs($coordinator);

    expect(WelfareRequestResource::canViewAny())->toBeFalse();

    $this->get('/coordinator/welfare-requests')->assertStatus(403);
});

test('9. coordinator WITHOUT create_welfare_interventions is denied create action and service execution', function () {
    $role = \App\Models\Role::findByName('coordinator');
    $perm = \App\Models\Permission::findByName('create_welfare_interventions');
    $role->revokePermissionTo($perm);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $coordinator = User::factory()->create();
    $coordinator->assignRole('coordinator');
    $zone = Zone::create(['name' => 'Test Revoke Create Zone', 'coordinator_id' => $coordinator->id]);
    $coordinator = $coordinator->fresh();

    $this->actingAs($coordinator);

    expect(WelfareRequestResource::canViewAny())->toBeTrue();
    expect(WelfareRequestResource::canCreate())->toBeFalse();

    $this->get('/coordinator/welfare-requests')->assertStatus(200);
    $this->get('/coordinator/welfare-requests/create')->assertStatus(403);

    $ownDeceased = Deceased::factory()->create(['zone_id' => $zone->id]);

    expect(function () use ($ownDeceased, $coordinator) {
        app(\App\Services\Welfare\WelfareNominationService::class)->nominate(
            (string) $this->package->id,
            [(string) $ownDeceased->id],
            $coordinator
        );
    })->toThrow(\Illuminate\Auth\Access\AuthorizationException::class);

    expect(WelfareBeneficiary::where('deceased_id', $ownDeceased->id)->exists())->toBeFalse();
});

test('10. coordinator WITH permission but WRONG zone cannot view, edit, or nominate out-of-zone records', function () {
    $this->actingAs($this->coordinator);

    $otherDeceased = Deceased::factory()->create(['zone_id' => $this->otherZone->id]);
    $otherBeneficiary = WelfareBeneficiary::create([
        'welfare_package_id' => (string) $this->package->id,
        'deceased_id' => (string) $otherDeceased->id,
        'status' => BeneficiaryStatus::PENDING,
        'suggested_by' => $this->otherCoordinator->id,
    ]);

    expect(WelfareRequestResource::canView($otherBeneficiary))->toBeFalse();
    expect(WelfareRequestResource::canEdit($otherBeneficiary))->toBeFalse();

    $result = app(\App\Services\Welfare\WelfareNominationService::class)->nominate(
        (string) $this->package->id,
        [(string) $otherDeceased->id],
        $this->coordinator
    );

    expect($result['nominated_count'])->toBe(0);
    expect($result['ineligible_count'])->toBe(1);
});

test('11. coordinator WITHOUT edit_welfare_interventions cannot edit even pending own-zone record', function () {
    $coordinator = User::factory()->create();
    $coordinator->assignRole('coordinator');
    $zone = Zone::create(['name' => 'Test Edit Zone', 'coordinator_id' => $coordinator->id]);
    $this->actingAs($coordinator);

    $ownDeceased = Deceased::factory()->create(['zone_id' => $zone->id]);
    $ownBeneficiary = WelfareBeneficiary::create([
        'welfare_package_id' => (string) $this->package->id,
        'deceased_id' => (string) $ownDeceased->id,
        'status' => BeneficiaryStatus::PENDING,
        'suggested_by' => $coordinator->id,
    ]);

    expect(WelfareRequestResource::canEdit($ownBeneficiary))->toBeFalse();

    $role = \App\Models\Role::findByName('coordinator');
    $perm = \App\Models\Permission::findByName('edit_welfare_interventions');
    $role->givePermissionTo($perm);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $coordinator = User::find($coordinator->id);
    $this->actingAs($coordinator);

    expect(WelfareRequestResource::canEdit($ownBeneficiary))->toBeTrue();
});

test('12. super admin and admin retain full access regardless of individual permissions', function () {
    $this->actingAs($this->admin);

    $ownDeceased = Deceased::factory()->create(['zone_id' => $this->zone->id]);
    $ownBeneficiary = WelfareBeneficiary::create([
        'welfare_package_id' => (string) $this->package->id,
        'deceased_id' => (string) $ownDeceased->id,
        'status' => BeneficiaryStatus::PENDING,
        'suggested_by' => $this->coordinator->id,
    ]);

    expect(WelfareRequestResource::canViewAny())->toBeTrue();
    expect(WelfareRequestResource::canCreate())->toBeTrue();
    expect(WelfareRequestResource::canView($ownBeneficiary))->toBeTrue();
    expect(WelfareRequestResource::canEdit($ownBeneficiary))->toBeTrue();
});

test('13. permission revocation immediately denies access', function () {
    $coordinator = User::factory()->create();
    $coordinator->assignRole('coordinator');
    Zone::create(['name' => 'Test Revoke All Zone', 'coordinator_id' => $coordinator->id]);
    $this->actingAs($coordinator);

    expect(WelfareRequestResource::canViewAny())->toBeTrue();
    expect(WelfareRequestResource::canCreate())->toBeTrue();

    $role = \App\Models\Role::findByName('coordinator');
    $permView = \App\Models\Permission::findByName('view_welfare_interventions');
    $permCreate = \App\Models\Permission::findByName('create_welfare_interventions');
    $role->revokePermissionTo($permView);
    $role->revokePermissionTo($permCreate);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $freshCoordinator = User::find($coordinator->id);
    $this->actingAs($freshCoordinator);

    expect(WelfareRequestResource::canViewAny())->toBeFalse();
    expect(WelfareRequestResource::canCreate())->toBeFalse();
});
