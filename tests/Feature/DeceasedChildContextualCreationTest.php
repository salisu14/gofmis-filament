<?php

use App\Enums\Gender;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Categories\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\Deceased\RelationManagers\OrphansRelationManager;
use App\Filament\Resources\Deceased\RelationManagers\WidowsRelationManager;
use App\Filament\Resources\Orphans\Pages\CreateOrphan;
use App\Filament\Resources\Widows\Pages\CreateWidow;
use App\Models\Category;
use App\Models\Deceased;
use App\Models\Item;
use App\Models\Orphan;
use App\Models\User;
use App\Models\Widow;
use App\Models\Zone;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create(['is_active' => true, 'status' => \App\Enums\UserStatus::ACTIVE]);
    $this->admin->assignRole('admin');
    $this->actingAs($this->admin);

    $this->zoneA = Zone::create(['name' => 'Zone A']);
    $this->zoneB = Zone::create(['name' => 'Zone B']);

    $this->deceasedA = Deceased::factory()->create([
        'first_name' => 'Kabiru',
        'last_name' => 'Dandali',
        'full_name' => 'Kabiru Dandali',
        'zone_id' => $this->zoneA->id,
    ]);

    $this->deceasedB = Deceased::factory()->create([
        'first_name' => 'Sani',
        'last_name' => 'Bello',
        'full_name' => 'Sani Bello',
        'zone_id' => $this->zoneB->id,
    ]);
});

test('category items relation manager baseline reference proves parent auto-binding and absence of category_id selector', function () {
    $category = Category::create([
        'name' => 'Food Items',
        'user_id' => $this->admin->id,
    ]);

    $testable = Livewire::test(ItemsRelationManager::class, [
        'ownerRecord' => $category,
        'pageClass' => EditCategory::class,
    ]);

    $action = $testable->instance()->getTable()->getAction('create');
    $schema = $action->getForm(\Filament\Schemas\Schema::make($testable->instance()));
    $fieldKeys = array_keys($schema->getFlatFields());

    expect($fieldKeys)->not->toContain('category_id');

    $testable
        ->callTableAction('create', data: [
            'name' => 'Rice Bag',
            'user_id' => $this->admin->id,
            'description' => '50kg Bag',
        ])
        ->assertHasNoTableActionErrors();

    $item = Item::where('name', 'Rice Bag')->firstOrFail();

    expect((string) $item->category_id)->toEqual((string) $category->id);
});

test('creating widow from deceased A relation manager automatically assigns deceased_id = A and hides deceased_id selector', function () {
    $testable = Livewire::test(WidowsRelationManager::class, [
        'ownerRecord' => $this->deceasedA,
        'pageClass' => \App\Filament\Resources\Deceased\Pages\EditDeceased::class,
    ]);

    // Runtime form schema verification on the CreateAction instance
    $action = $testable->instance()->getTable()->getAction('create');
    $schema = $action->getForm(\Filament\Schemas\Schema::make($testable->instance()));
    $fieldKeys = array_keys($schema->getFlatFields());

    expect($fieldKeys)->not->toContain('deceased_id')
        ->and($fieldKeys)->toContain('deceased_family_display');

    $testable
        ->callTableAction('create', data: [
            'first_name' => 'Amina',
            'last_name' => 'Dandali',
            'nin' => '12345678901',
            'has_nin' => true,
            'address' => 'House 1, Kano Road',
            'is_eligible' => true,
            'is_married' => false,
        ])
        ->assertHasNoTableActionErrors();

    $widow = Widow::where('nin', '12345678901')->firstOrFail();

    expect((string) $widow->deceased_id)->toEqual((string) $this->deceasedA->id)
        ->and($widow->child_sequence)->toBe(1);
});

test('creating orphan from deceased A relation manager automatically assigns deceased_id = A and hides deceased_id selector', function () {
    $testable = Livewire::test(OrphansRelationManager::class, [
        'ownerRecord' => $this->deceasedA,
        'pageClass' => \App\Filament\Resources\Deceased\Pages\EditDeceased::class,
    ]);

    // Runtime form schema verification on the CreateAction instance
    $action = $testable->instance()->getTable()->getAction('create');
    $schema = $action->getForm(\Filament\Schemas\Schema::make($testable->instance()));
    $fieldKeys = array_keys($schema->getFlatFields());

    expect($fieldKeys)->not->toContain('deceased_id')
        ->and($fieldKeys)->toContain('deceased_family_display');

    $testable
        ->callTableAction('create', data: [
            'first_name' => 'Usman',
            'last_name' => 'Dandali',
            'gender' => Gender::MALE,
            'birth_date' => now()->subYears(10)->toDateString(),
            'nin' => '98765432101',
            'has_nin' => true,
            'address' => 'House 1, Kano Road',
            'has_birth_cert' => false,
        ])
        ->assertHasNoTableActionErrors();

    $orphan = Orphan::where('nin', '98765432101')->firstOrFail();

    expect((string) $orphan->deceased_id)->toEqual((string) $this->deceasedA->id)
        ->and($orphan->child_sequence)->toBe(1);
});

test('attempting to forge deceased_id B while creating widow under deceased A is overridden server-side', function () {
    Livewire::test(WidowsRelationManager::class, [
        'ownerRecord' => $this->deceasedA,
        'pageClass' => \App\Filament\Resources\Deceased\Pages\EditDeceased::class,
    ])
        ->callTableAction('create', data: [
            'deceased_id' => (string) $this->deceasedB->id, // Forged payload attempt
            'first_name' => 'Fatima',
            'last_name' => 'Dandali',
            'nin' => '11223344556',
            'has_nin' => true,
            'address' => 'House 1, Kano Road',
            'is_eligible' => true,
            'is_married' => false,
        ])
        ->assertHasNoTableActionErrors();

    $widow = Widow::where('nin', '11223344556')->firstOrFail();

    expect((string) $widow->deceased_id)->toEqual((string) $this->deceasedA->id)
        ->and((string) $widow->deceased_id)->not->toEqual((string) $this->deceasedB->id);
});

test('attempting to forge deceased_id B while creating orphan under deceased A is overridden server-side', function () {
    Livewire::test(OrphansRelationManager::class, [
        'ownerRecord' => $this->deceasedA,
        'pageClass' => \App\Filament\Resources\Deceased\Pages\EditDeceased::class,
    ])
        ->callTableAction('create', data: [
            'deceased_id' => (string) $this->deceasedB->id, // Forged payload attempt
            'first_name' => 'Ibrahim',
            'last_name' => 'Dandali',
            'gender' => Gender::MALE,
            'birth_date' => now()->subYears(8)->toDateString(),
            'nin' => '66554433221',
            'has_nin' => true,
            'address' => 'House 1, Kano Road',
            'has_birth_cert' => false,
        ])
        ->assertHasNoTableActionErrors();

    $orphan = Orphan::where('nin', '66554433221')->firstOrFail();

    expect((string) $orphan->deceased_id)->toEqual((string) $this->deceasedA->id)
        ->and((string) $orphan->deceased_id)->not->toEqual((string) $this->deceasedB->id);
});

test('standalone widow create page exposes and requires deceased_id selection', function () {
    Livewire::test(CreateWidow::class)
        ->assertFormFieldExists('deceased_id')
        ->assertFormFieldDoesNotExist('deceased_family_display');
});

test('standalone orphan create page exposes and requires deceased_id selection', function () {
    Livewire::test(CreateOrphan::class)
        ->assertFormFieldExists('deceased_id')
        ->assertFormFieldDoesNotExist('deceased_family_display');
});

test('coordinator zone isolation prevents creation of widow under deceased outside managed zone', function () {
    $coordinator = User::factory()->create(['is_active' => true, 'status' => \App\Enums\UserStatus::ACTIVE]);
    $coordinator->assignRole('coordinator');
    $coordinator->givePermissionTo('create_widows');
    $coordinator->update(['zone_id' => $this->zoneA->id]);

    $this->actingAs($coordinator);

    // Attempting to access relation manager for Deceased B (Zone B)
    Livewire::test(WidowsRelationManager::class, [
        'ownerRecord' => $this->deceasedB,
        'pageClass' => \App\Filament\Resources\Deceased\Pages\EditDeceased::class,
    ])
        ->assertTableActionHidden('create');
});

test('coordinator zone isolation prevents creation of orphan under deceased outside managed zone', function () {
    $coordinator = User::factory()->create(['is_active' => true, 'status' => \App\Enums\UserStatus::ACTIVE]);
    $coordinator->assignRole('coordinator');
    $coordinator->givePermissionTo('create_orphans');
    $coordinator->update(['zone_id' => $this->zoneA->id]);

    $this->actingAs($coordinator);

    // Attempting to access relation manager for Deceased B (Zone B)
    Livewire::test(OrphansRelationManager::class, [
        'ownerRecord' => $this->deceasedB,
        'pageClass' => \App\Filament\Resources\Deceased\Pages\EditDeceased::class,
    ])
        ->assertTableActionHidden('create');
});
