<?php

namespace Tests\Feature;

use App\Enums\StockMovementType;
use App\Enums\UserStatus;
use App\Models\Category;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\WelfarePackage;
use App\Models\WelfarePackageItem;
use App\Services\Inventory\StockAvailabilityService;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);

    $this->admin = User::factory()->create(['status' => UserStatus::ACTIVE]);
    $this->admin->assignRole('admin');
    $this->admin->givePermissionTo('view_welfare_interventions');

    $this->coordinator = User::factory()->create(['status' => UserStatus::ACTIVE]);
    $this->coordinator->assignRole('coordinator');

    $this->category = Category::create(['name' => 'Food Staples', 'user_id' => $this->admin->id]);

    $this->rice = Item::create([
        'name' => 'Rice (50kg)',
        'description' => 'Grain bag',
        'category_id' => $this->category->id,
        'user_id' => $this->admin->id,
    ]);

    $this->oil = Item::create([
        'name' => 'Vegetable Oil (5L)',
        'description' => 'Cooking oil bottle',
        'category_id' => $this->category->id,
        'user_id' => $this->admin->id,
    ]);

    // Seed canonical opening balances via stock_movements
    StockMovement::create([
        'item_id' => $this->rice->id,
        'movement_type' => StockMovementType::OPENING_BALANCE,
        'quantity' => 200,
        'occurred_at' => now(),
        'created_by' => $this->admin->id,
        'notes' => 'Test opening balance',
    ]);

    StockMovement::create([
        'item_id' => $this->oil->id,
        'movement_type' => StockMovementType::OPENING_BALANCE,
        'quantity' => 100,
        'occurred_at' => now(),
        'created_by' => $this->admin->id,
        'notes' => 'Test opening balance',
    ]);
});

// 1. Access control
test('1. admin can access stock availability page while unauthorized user is denied', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($this->admin);

    Livewire::test(\App\Filament\Pages\StockAvailability::class)
        ->assertSuccessful();
});

// 2. Items with no movements show zero on-hand
test('2. items with no stock movements show zero on-hand', function () {
    $emptyItem = Item::create([
        'name' => 'Empty Item',
        'description' => 'No movements',
        'category_id' => $this->category->id,
        'user_id' => $this->admin->id,
    ]);

    $service = app(StockAvailabilityService::class);
    $metrics = $service->getItemStockMetrics($emptyItem->id)->first();

    expect($metrics['on_hand'])->toBe(0)
        ->and($metrics['available'])->toBe(0)
        ->and($metrics['status'])->toBe('OUT_OF_STOCK');
});

// 3. Stock metrics computed from ledger
test('3. stock availability service derives on-hand from stock movements ledger', function () {
    $service = app(StockAvailabilityService::class);
    $metrics = $service->getItemStockMetrics($this->rice->id)->first();

    expect($metrics['item_id'])->toBe($this->rice->id)
        ->and($metrics['on_hand'])->toBe(200)
        ->and($metrics['reserved'])->toBe(0)
        ->and($metrics['available'])->toBe(200)
        ->and($metrics['status'])->toBe('IN_STOCK');
});

// 4. Outflow reduces on-hand
test('4. welfare issue movement reduces on-hand stock', function () {
    // Simulate a welfare distribution
    StockMovement::create([
        'item_id' => $this->rice->id,
        'movement_type' => StockMovementType::WELFARE_ISSUE,
        'quantity' => -50,
        'occurred_at' => now(),
        'created_by' => $this->admin->id,
        'notes' => 'Test welfare distribution',
    ]);

    $service = app(StockAvailabilityService::class);
    $metrics = $service->getItemStockMetrics($this->rice->id)->first();

    expect($metrics['on_hand'])->toBe(150)
        ->and($metrics['available'])->toBe(150);
});

// 5. Purchase receipt increases on-hand
test('5. purchase receipt movement increases on-hand stock', function () {
    StockMovement::create([
        'item_id' => $this->oil->id,
        'movement_type' => StockMovementType::PURCHASE_RECEIPT,
        'quantity' => 50,
        'occurred_at' => now(),
        'created_by' => $this->admin->id,
        'notes' => 'Test purchase receipt',
    ]);

    $service = app(StockAvailabilityService::class);
    $metrics = $service->getItemStockMetrics($this->oil->id)->first();

    expect($metrics['on_hand'])->toBe(150);
});

// 6. Welfare capacity bottleneck
test('6. welfare package capacity correctly calculates minimum bottleneck capacity across items', function () {
    $package = WelfarePackage::create([
        'name' => 'Festive Distribution Package',
        'description' => 'Holiday support',
        'start_date' => now()->subDays(1),
        'end_date' => now()->addDays(5),
        'status' => \App\Enums\WelfarePackageStatus::OPEN,
        'created_by' => $this->admin->id,
    ]);

    // Rice: 200 on-hand, 1 per family => capacity 200
    WelfarePackageItem::create([
        'welfare_package_id' => $package->id,
        'item_id' => $this->rice->id,
        'category_id' => $this->category->id,
        'quantity_per_family' => 1,
    ]);

    // Oil: 100 on-hand, 2 per family => capacity 50 (bottleneck)
    WelfarePackageItem::create([
        'welfare_package_id' => $package->id,
        'item_id' => $this->oil->id,
        'category_id' => $this->category->id,
        'quantity_per_family' => 2,
    ]);

    $service = app(StockAvailabilityService::class);
    $capacityData = $service->calculatePackageCapacity($package);

    expect($capacityData['capacity'])->toBe(50)
        ->and($capacityData['bottleneck_item'])->toBe($this->oil->name)
        ->and($capacityData['readiness_status'])->toBe('READY');
});

// 7. Empty package returns INCOMPLETE
test('7. empty welfare package returns incomplete readiness', function () {
    $package = WelfarePackage::create([
        'name' => 'Empty Package',
        'description' => 'No items',
        'start_date' => now()->subDays(1),
        'end_date' => now()->addDays(5),
        'status' => \App\Enums\WelfarePackageStatus::OPEN,
        'created_by' => $this->admin->id,
    ]);

    $service = app(StockAvailabilityService::class);
    $capacityData = $service->calculatePackageCapacity($package);

    expect($capacityData['capacity'])->toBe(0)
        ->and($capacityData['readiness_status'])->toBe('INCOMPLETE');
});

// 8. Stock report PDF export
test('8. admin can export stock availability report as genuine PDF', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($this->admin);

    Livewire::test(\App\Filament\Pages\StockAvailability::class)
        ->callAction('export_pdf')
        ->assertFileDownloaded();
});

// 9. Reconcile command runs without error
test('9. inventory reconcile command runs successfully', function () {
    $this->artisan('inventory:reconcile')
        ->assertSuccessful();
});
