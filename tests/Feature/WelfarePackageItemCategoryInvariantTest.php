<?php

namespace Tests\Feature;

use App\Enums\WelfarePackageStatus;
use App\Models\Category;
use App\Models\Item;
use App\Models\User;
use App\Models\WelfarePackage;
use App\Models\WelfarePackageItem;
use App\Services\Inventory\StockAvailabilityService;
use App\Services\WelfarePackageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WelfarePackageItemCategoryInvariantTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Category $foodCategory;

    protected Category $schoolCategory;

    protected Item $riceItem;

    protected Item $notebookItem;

    protected function setUp(): void
    {
        parent::setUp();

        \Spatie\Permission\Models\Role::firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'web'],
            ['uuid' => (string) \Illuminate\Support\Str::uuid()]
        );
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->foodCategory = Category::create([
            'name' => 'Food & Provisions',
            'user_id' => $this->admin->id,
        ]);

        $this->schoolCategory = Category::create([
            'name' => 'School Supplies',
            'user_id' => $this->admin->id,
        ]);

        $this->riceItem = Item::create([
            'name' => 'Rice 50kg',
            'category_id' => $this->foodCategory->id,
            'user_id' => $this->admin->id,
        ]);

        $this->notebookItem = Item::create([
            'name' => 'Exercise Notebooks (Pack of 10)',
            'category_id' => $this->schoolCategory->id,
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_1_welfare_package_item_relation_manager_form_does_not_expose_editable_category_id_selector(): void
    {
        $package = WelfarePackage::create([
            'name' => 'Ramadan Relief Package',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(30)->toDateString(),
            'created_by' => $this->admin->id,
            'status' => WelfarePackageStatus::DRAFT,
        ]);

        $relationManager = Livewire::actingAs($this->admin)
            ->test(\App\Filament\Resources\WelfarePackages\RelationManagers\ItemsRelationManager::class, [
                'ownerRecord' => $package,
                'pageClass' => \App\Filament\Resources\WelfarePackages\Pages\EditWelfarePackage::class,
            ]);

        $formSchema = $relationManager->instance()->form(\Filament\Schemas\Schema::make());
        $componentKeys = collect($formSchema->getComponents())
            ->map(fn ($c) => method_exists($c, 'getName') ? $c->getName() : null)
            ->filter()
            ->values()
            ->toArray();

        expect($componentKeys)->toContain('item_id');
        expect($componentKeys)->toContain('category_display');
        expect($componentKeys)->not->toContain('category_id');
    }

    public function test_2_selecting_an_item_correctly_derives_and_accesses_its_category_via_model_relationship(): void
    {
        $package = WelfarePackage::create([
            'name' => 'Education Kit 2026',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(30)->toDateString(),
            'created_by' => $this->admin->id,
            'status' => WelfarePackageStatus::DRAFT,
        ]);

        $packageItem = WelfarePackageItem::create([
            'welfare_package_id' => $package->id,
            'item_id' => $this->notebookItem->id,
            'quantity_per_family' => 2,
        ]);

        expect($packageItem->item->name)->toBe('Exercise Notebooks (Pack of 10)');
        expect($packageItem->category->name)->toBe('School Supplies');
        expect($packageItem->category->id)->toBe($this->schoolCategory->id);
    }

    public function test_3_creating_a_package_item_stores_and_uses_category_derived_authoritatively_from_item(): void
    {
        $package = WelfarePackage::create([
            'name' => 'Food Pack 2026',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(30)->toDateString(),
            'created_by' => $this->admin->id,
            'status' => WelfarePackageStatus::DRAFT,
        ]);

        app(WelfarePackageService::class)->syncItems($package, [
            ['item_id' => $this->riceItem->id, 'quantity_per_family' => 1],
        ]);

        $itemRecord = $package->fresh()->items->first();

        expect($itemRecord)->not->toBeNull();
        expect($itemRecord->item_id)->toBe($this->riceItem->id);
        expect($itemRecord->category->id)->toBe($this->foodCategory->id);
        expect($itemRecord->category->name)->toBe('Food & Provisions');
    }

    public function test_4_forged_category_id_payload_cannot_create_a_category_mismatch_in_db(): void
    {
        $package = WelfarePackage::create([
            'name' => 'Payload Anti Forgery Package',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(30)->toDateString(),
            'created_by' => $this->admin->id,
            'status' => WelfarePackageStatus::DRAFT,
        ]);

        app(WelfarePackageService::class)->syncItems($package, [
            [
                'item_id' => $this->riceItem->id,
                'category_id' => $this->schoolCategory->id, // Forged mismatched category
                'quantity_per_family' => 1,
            ],
        ]);

        $itemRecord = $package->fresh()->items->first();

        expect($itemRecord->category->id)->toBe($this->foodCategory->id);
        expect($itemRecord->category->name)->toBe('Food & Provisions');
    }

    public function test_5_updating_item_id_automatically_shifts_the_derived_category(): void
    {
        $package = WelfarePackage::create([
            'name' => 'Dynamic Shift Package',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(30)->toDateString(),
            'created_by' => $this->admin->id,
            'status' => WelfarePackageStatus::DRAFT,
        ]);

        $packageItem = WelfarePackageItem::create([
            'welfare_package_id' => $package->id,
            'item_id' => $this->riceItem->id,
            'quantity_per_family' => 1,
        ]);

        expect($packageItem->category->name)->toBe('Food & Provisions');

        $packageItem->update(['item_id' => $this->notebookItem->id]);

        expect($packageItem->fresh()->category->name)->toBe('School Supplies');
    }

    public function test_6_invalid_item_id_throws_exception_when_syncing_items(): void
    {
        $package = WelfarePackage::create([
            'name' => 'Validation Edge Case Package',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(30)->toDateString(),
            'created_by' => $this->admin->id,
            'status' => WelfarePackageStatus::DRAFT,
        ]);

        expect(fn () => app(WelfarePackageService::class)->syncItems($package, [
            ['item_id' => '00000000-0000-0000-0000-000000000000', 'quantity_per_family' => 1],
        ]))->toThrow(\InvalidArgumentException::class);
    }

    public function test_7_existing_package_creation_and_edit_flow_succeeds(): void
    {
        $this->actingAs($this->admin);

        $package = WelfarePackage::create([
            'name' => 'Full Workflow Package',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(30)->toDateString(),
            'created_by' => $this->admin->id,
            'status' => WelfarePackageStatus::DRAFT,
        ]);

        app(WelfarePackageService::class)->syncItems($package, [
            ['item_id' => $this->riceItem->id, 'quantity_per_family' => 1],
            ['item_id' => $this->notebookItem->id, 'quantity_per_family' => 3],
        ]);

        expect($package->fresh()->items)->toHaveCount(2);

        $duplicated = app(WelfarePackageService::class)->duplicatePackage($package, 'Cloned Package');
        expect($duplicated->items)->toHaveCount(2);
        expect($duplicated->items->pluck('item_id')->toArray())->toContain($this->riceItem->id);
    }

    public function test_8_stock_availability_calculations_remain_completely_unaffected(): void
    {
        $package = WelfarePackage::create([
            'name' => 'Stock Reservation Package',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(30)->toDateString(),
            'created_by' => $this->admin->id,
            'status' => WelfarePackageStatus::OPEN,
        ]);

        WelfarePackageItem::create([
            'welfare_package_id' => $package->id,
            'item_id' => $this->riceItem->id,
            'quantity_per_family' => 5,
        ]);

        $metrics = app(StockAvailabilityService::class)->getItemStockMetrics($this->riceItem->id);

        expect($metrics)->not->toBeEmpty();
        expect($metrics->first()['item_id'])->toBe($this->riceItem->id);
    }

    public function test_9_multiple_items_across_distinct_categories_function_independently(): void
    {
        $package = WelfarePackage::create([
            'name' => 'Multi Category Package',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(30)->toDateString(),
            'created_by' => $this->admin->id,
            'status' => WelfarePackageStatus::DRAFT,
        ]);

        WelfarePackageItem::create([
            'welfare_package_id' => $package->id,
            'item_id' => $this->riceItem->id,
            'quantity_per_family' => 2,
        ]);

        WelfarePackageItem::create([
            'welfare_package_id' => $package->id,
            'item_id' => $this->notebookItem->id,
            'quantity_per_family' => 5,
        ]);

        $categories = $package->fresh()->items->map(fn ($pi) => $pi->category->name)->values()->toArray();

        expect($categories)->toContain('Food & Provisions');
        expect($categories)->toContain('School Supplies');
    }

    public function test_10_standalone_item_management_remains_intact(): void
    {
        expect($this->riceItem->category->name)->toBe('Food & Provisions');
        expect($this->notebookItem->category->name)->toBe('School Supplies');

        $this->riceItem->update(['category_id' => $this->schoolCategory->id]);
        expect($this->riceItem->fresh()->category->name)->toBe('School Supplies');
    }
}
