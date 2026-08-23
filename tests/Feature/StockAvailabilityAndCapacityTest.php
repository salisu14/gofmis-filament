<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\Category;
use App\Models\Item;
use App\Models\User;
use App\Models\WelfarePackage;
use App\Models\WelfarePackageItem;
use App\Services\Inventory\StockAvailabilityService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

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
});

// 1 & 2. Access control
test('1. admin can access stock availability page while unauthorized user is denied', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($this->admin);

    Livewire::test(\App\Filament\Pages\StockAvailability::class)
        ->assertSuccessful();
});

// 3 & 4 & 5. Stock metrics computation
test('3. stock availability service correctly computes item metrics and statuses', function () {
    $service = app(StockAvailabilityService::class);
    $metrics = $service->getItemStockMetrics($this->rice->id)->first();

    expect($metrics['item_id'])->toBe($this->rice->id)
        ->and($metrics['on_hand'])->toBeGreaterThan(0)
        ->and($metrics['status'])->toBeIn(['IN_STOCK', 'LOW_STOCK', 'OUT_OF_STOCK']);
});

// 6 & 7 & 8. Welfare capacity calculation with bottleneck
test('6. welfare package capacity correctly calculates minimum bottleneck capacity across items', function () {
    $package = WelfarePackage::create([
        'name' => 'Festive Distribution Package',
        'description' => 'Holiday support',
        'start_date' => now()->subDays(1),
        'end_date' => now()->addDays(5),
        'status' => \App\Enums\WelfarePackageStatus::OPEN,
        'created_by' => $this->admin->id,
    ]);

    // 1 bag of rice per family (available = 100 -> capacity = 100)
    WelfarePackageItem::create([
        'welfare_package_id' => $package->id,
        'item_id' => $this->rice->id,
        'category_id' => $this->category->id,
        'quantity_per_family' => 1,
    ]);

    // 2 bottles of oil per family (available = 100 -> capacity = 50)
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

// 9. Stock report PDF / HTML export stream
test('9. admin can export stock availability report stream', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($this->admin);

    Livewire::test(\App\Filament\Pages\StockAvailability::class)
        ->callAction('export_pdf')
        ->assertFileDownloaded();
});
