<?php

use App\Filament\Coordinator\Resources\OrphanResource;
use App\Models\Deceased;
use App\Models\Orphan;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $this->coordinatorRole = \App\Models\Role::where('name', 'coordinator')->first();
    test()->assertNotNull($this->coordinatorRole);

    $this->coordinator = User::factory()->create(['is_active' => true, 'status' => \App\Enums\UserStatus::ACTIVE]);
    $this->coordinator->assignRole('coordinator');

    $this->zone = Zone::create(['name' => 'Zone R', 'coordinator_id' => $this->coordinator->id]);
    $this->coordinator->unsetRelation('coordinatedZone');

    $this->orphan = Orphan::create([
        'deceased_id' => Deceased::factory()->create(['zone_id' => $this->zone->id])->id,
        'first_name' => 'RBAC', 'last_name' => 'Orphan', 'full_name' => 'RBAC Orphan',
        'reg_no' => 'ORP-R-1', 'nin' => '55555555555',
        'gender' => \App\Enums\Gender::MALE, 'birth_date' => now()->subYears(9)->toDateString(),
        'status' => \App\Enums\OrphanStatus::ACTIVE, 'is_eligible' => true,
    ]);

    $this->actingAs($this->coordinator);
});

test('coordinator role without create_orphans permission cannot create', function () {
    $this->coordinatorRole->syncPermissions([]);
    app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    expect(OrphanResource::canCreate())->toBeFalse();
});

test('granting create_orphans to the coordinator role enables create', function () {
    $this->coordinatorRole->givePermissionTo('create_orphans');
    app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    expect(OrphanResource::canCreate())->toBeTrue();
});

test('revoking create_orphans from the role disables coordinator immediately', function () {
    $this->coordinatorRole->givePermissionTo('create_orphans');
    $this->coordinatorRole->revokePermissionTo('create_orphans');
    app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    expect(OrphanResource::canCreate())->toBeFalse();
});

test('coordinator role without edit_orphans cannot edit even within own zone', function () {
    $this->coordinatorRole->syncPermissions([]);
    app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    expect(OrphanResource::canEdit($this->orphan))->toBeFalse();
});

test('coordinator role with edit_orphans CAN edit within own zone', function () {
    $this->coordinatorRole->givePermissionTo('edit_orphans');
    app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    expect(OrphanResource::canEdit($this->orphan))->toBeTrue();
});

test('coordinator cannot cross zone boundary even with permission granted', function () {
    $this->coordinatorRole->givePermissionTo('edit_orphans');
    app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $otherZone = Zone::create(['name' => 'Zone R2']);
    $foreignOrphan = Orphan::create([
        'deceased_id' => Deceased::factory()->create(['zone_id' => $otherZone->id])->id,
        'first_name' => 'Foreign', 'last_name' => 'Orphan', 'full_name' => 'Foreign Orphan',
        'reg_no' => 'ORP-R-2', 'nin' => '77777777777',
        'gender' => \App\Enums\Gender::FEMALE, 'birth_date' => now()->subYears(8)->toDateString(),
        'status' => \App\Enums\OrphanStatus::ACTIVE, 'is_eligible' => true,
    ]);

    expect(OrphanResource::canEdit($foreignOrphan))->toBeFalse();
});

test('admin platform rule is unaffected by coordinator role permission revocation', function () {
    $this->coordinatorRole->syncPermissions([]);
    app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $admin = User::factory()->create(['is_active' => true, 'status' => \App\Enums\UserStatus::ACTIVE]);
    $admin->assignRole('admin');
    $this->actingAs($admin);

    expect(OrphanResource::canCreate())->toBeTrue();
});

test('widow resource create requires create_widows permission and zone', function () {
    $this->coordinatorRole->syncPermissions([]);
    app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    expect(\App\Filament\Coordinator\Resources\WidowResource::canCreate())->toBeFalse();

    $this->coordinatorRole->givePermissionTo('create_widows');
    app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    expect(\App\Filament\Coordinator\Resources\WidowResource::canCreate())->toBeTrue();
});

test('deceased resource create requires create_deceased permission and zone', function () {
    $this->coordinatorRole->syncPermissions([]);
    app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    expect(\App\Filament\Coordinator\Resources\DeceasedResource::canCreate())->toBeFalse();

    $this->coordinatorRole->givePermissionTo('create_deceased');
    app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    expect(\App\Filament\Coordinator\Resources\DeceasedResource::canCreate())->toBeTrue();
});

test('widow edit denied cross-zone even with edit_widows permission', function () {
    $this->coordinatorRole->givePermissionTo('edit_widows');
    app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $foreignWidow = \App\Models\Widow::create([
        'deceased_id' => Deceased::factory()->create(['zone_id' => Zone::create(['name' => 'Zone RW'])->id])->id,
        'first_name' => 'Foreign', 'last_name' => 'Widow', 'full_name' => 'Foreign Widow',
        'reg_no' => 'WID-RW-1', 'nin' => '66666666666',
        'is_eligible' => true, 'is_married' => false, 'child_sequence' => 1,
    ]);

    expect(\App\Filament\Coordinator\Resources\WidowResource::canEdit($foreignWidow))->toBeFalse();
});
