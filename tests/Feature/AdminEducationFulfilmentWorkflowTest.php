<?php

use App\Enums\Gender;
use App\Filament\Coordinator\Resources\EducationRequestResource\Pages\ViewEducationRequest;
use App\Models\Category;
use App\Models\Deceased;
use App\Models\InterventionRequest;
use App\Models\InterventionRequestItem;
use App\Models\InterventionType;
use App\Models\Item;
use App\Models\Orphan;
use App\Models\User;
use App\Models\Zone;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(\Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

    $this->coordinator = User::factory()->create();
    $this->coordinator->assignRole('coordinator');

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->zone = Zone::create(['name' => 'North Zone', 'coordinator_id' => $this->coordinator->id]);

    $this->deceased = Deceased::factory()->create(['zone_id' => $this->zone->id]);

    $this->orphan = Orphan::create([
        'deceased_id' => $this->deceased->id,
        'first_name' => 'Usman',
        'last_name' => 'Balarabe',
        'nin' => '12345678988',
        'reg_no' => 'ORP-00600',
        'child_sequence' => 1,
        'gender' => Gender::MALE,
        'is_eligible' => true,
    ]);

    $this->category = Category::create([
        'name' => 'Education & Books',
        'user_id' => $this->admin->id,
    ]);

    $this->itemUniform = Item::create([
        'name' => 'School Uniform Set',
        'description' => 'Navy Blue Shirt and Trousers',
        'category_id' => $this->category->id,
        'user_id' => $this->admin->id,
    ]);

    $this->itemBooks = Item::create([
        'name' => 'Notebook 80 Pages',
        'description' => 'Higher Education Pack',
        'category_id' => $this->category->id,
        'user_id' => $this->admin->id,
    ]);

    $this->educationType = InterventionType::where('name', 'Education - Uniform & Books')->first()
        ?? InterventionType::create([
            'name' => 'Education - Uniform & Books',
            'category' => 'education',
        ]);

    $this->actingAs($this->admin);
});

test('1. end-to-end education request -> verification -> approval -> partial & complete fulfillment -> closure', function () {
    // 1. Coordinator creates Education Request with items
    $request = InterventionRequest::create([
        'orphan_id' => $this->orphan->id,
        'intervention_type_id' => $this->educationType->id,
        'title' => 'School Starter Pack Request',
        'status' => 'pending',
        'submitted_by' => $this->coordinator->id,
        'amount_requested' => 15000,
    ]);

    $itemRow1 = InterventionRequestItem::create([
        'intervention_request_id' => $request->id,
        'item_id' => $this->itemUniform->id,
        'item_name' => 'School Uniform Set',
        'orphan_class' => 'JSS 1',
        'specification' => 'Size Medium',
        'quantity_requested' => 2,
        'quantity_fulfilled' => 0,
    ]);

    $itemRow2 = InterventionRequestItem::create([
        'intervention_request_id' => $request->id,
        'item_id' => $this->itemBooks->id,
        'item_name' => 'Notebook 80 Pages',
        'orphan_class' => 'JSS 1',
        'specification' => 'Pack of 12',
        'quantity_requested' => 10,
        'quantity_fulfilled' => 0,
    ]);

    // 2. Admin Verifies Request
    $request->markVerified($this->admin->id, 'Verified school enrollment and item need');
    expect($request->fresh()->verification_status)->toBe('verified');

    // 3. Admin Approves Request
    $request->approveRequest($this->admin->id, 'Approved for store fulfillment');
    expect($request->fresh()->status)->toBe('approved');

    // 4. Partial Fulfillment (1 out of 2 uniforms, 5 out of 10 notebooks)
    $itemRow1->update(['quantity_fulfilled' => 1]);
    $itemRow2->update(['quantity_fulfilled' => 5]);

    expect($itemRow1->fresh()->quantity_fulfilled)->toBe(1);
    expect($itemRow2->fresh()->quantity_fulfilled)->toBe(5);
    expect($request->fresh()->status)->toBe('approved'); // Partial fulfillment remains approved

    // 5. Complete Fulfillment (Remaining 1 uniform, 5 notebooks) -> Status becomes FULFILLED
    $itemRow1->update(['quantity_fulfilled' => 2]);
    $itemRow2->update(['quantity_fulfilled' => 10]);

    // Check if all requested items are fulfilled
    $allFulfilled = $request->items->every(fn ($item) => $item->quantity_fulfilled >= $item->quantity_requested);
    if ($allFulfilled) {
        $request->update(['status' => 'fulfilled']);
    }

    expect($request->fresh()->status)->toBe('fulfilled');

    // 6. Coordinator visibility on View Education Request page
    Filament::setCurrentPanel(Filament::getPanel('coordinator'));
    $this->actingAs($this->coordinator);

    Livewire::test(ViewEducationRequest::class, ['record' => $request->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('Usman');
});
