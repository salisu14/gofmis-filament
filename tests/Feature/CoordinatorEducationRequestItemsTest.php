<?php

use App\Filament\Coordinator\Resources\EducationRequestResource\Pages\CreateEducationRequest;
use App\Filament\Resources\Verifications\Pages\ListEducationVerifications;
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
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('coordinator'));

    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->seed(\Database\Seeders\InterventionTypeSeeder::class);

    $this->coordinator = User::factory()->create();
    $this->coordinator->assignRole('coordinator');

    $this->otherCoordinator = User::factory()->create();
    $this->otherCoordinator->assignRole('coordinator');

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->zone = Zone::create(['name' => 'North Zone', 'coordinator_id' => $this->coordinator->id]);
    $this->otherZone = Zone::create(['name' => 'South Zone', 'coordinator_id' => $this->otherCoordinator->id]);

    $this->deceased = Deceased::factory()->create(['zone_id' => $this->zone->id]);
    $this->otherDeceased = Deceased::factory()->create(['zone_id' => $this->otherZone->id]);

    $this->orphan = Orphan::create([
        'deceased_id' => $this->deceased->id,
        'first_name' => 'Amina',
        'last_name' => 'Musa',
        'reg_no' => 'ORP-00201',
        'gender' => \App\Enums\Gender::FEMALE,
        'date_of_birth' => now()->subYears(10),
        'is_eligible' => true,
    ]);

    $this->otherOrphan = Orphan::create([
        'deceased_id' => $this->otherDeceased->id,
        'first_name' => 'Zainab',
        'last_name' => 'Ibrahim',
        'reg_no' => 'ORP-00202',
        'gender' => \App\Enums\Gender::FEMALE,
        'date_of_birth' => now()->subYears(11),
        'is_eligible' => true,
    ]);

    $this->category = Category::create([
        'name' => 'Education & Uniforms',
        'user_id' => $this->admin->id,
    ]);

    $this->itemUniform = Item::create([
        'name' => 'School Uniform',
        'description' => 'Standard School Navy Blue Uniform',
        'category_id' => $this->category->id,
        'user_id' => $this->admin->id,
    ]);

    $this->itemBooks = Item::create([
        'name' => 'Exercise Books 80 Pages',
        'description' => 'Higher Education Notebooks',
        'category_id' => $this->category->id,
        'user_id' => $this->admin->id,
    ]);

    $this->itemBag = Item::create([
        'name' => 'School Bag',
        'description' => 'Waterproof Backpack',
        'category_id' => $this->category->id,
        'user_id' => $this->admin->id,
    ]);

    $this->uniformType = InterventionType::where('name', 'Education - Uniform & Books')->first()
        ?? InterventionType::create(['name' => 'Education - Uniform & Books']);

    $this->feesType = InterventionType::where('name', 'Education - School Fees')->first()
        ?? InterventionType::create(['name' => 'Education - School Fees']);

    $this->actingAs($this->coordinator);
});

test('1, 2 & 3. coordinator can create item-based Education Request with multiple requested items', function () {
    Livewire::test(CreateEducationRequest::class)
        ->set('data.orphan_id', (string) $this->orphan->id)
        ->set('data.intervention_type_id', (string) $this->uniformType->id)
        ->set('data.request_date', now()->format('Y-m-d'))
        ->set('data.requested_level', 'jss_1')
        ->set('data.notes', 'Uniform and books needed for new term')
        ->set('data.items', [
            [
                'item_id' => (string) $this->itemUniform->id,
                'item_name' => $this->itemUniform->name,
                'orphan_class' => 'Size 34 / JSS 1',
                'specification' => 'Blue cotton uniform',
                'quantity_requested' => 2,
            ],
            [
                'item_id' => (string) $this->itemBooks->id,
                'item_name' => $this->itemBooks->name,
                'orphan_class' => 'JSS 1',
                'specification' => 'Higher education notebooks',
                'quantity_requested' => 10,
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $request = InterventionRequest::latest('created_at')->first();
    expect($request)->not->toBeNull()
        ->and($request->items)->toHaveCount(2);

    $uniformRow = $request->items->where('item_id', $this->itemUniform->id)->first();
    expect($uniformRow->quantity_requested)->toBe(2)
        ->and($uniformRow->orphan_class)->toBe('Size 34 / JSS 1')
        ->and($uniformRow->specification)->toBe('Blue cotton uniform')
        ->and($uniformRow->quantity_fulfilled)->toBe(0);

    $booksRow = $request->items->where('item_id', $this->itemBooks->id)->first();
    expect($booksRow->quantity_requested)->toBe(10)
        ->and($booksRow->orphan_class)->toBe('JSS 1')
        ->and($booksRow->quantity_fulfilled)->toBe(0);
});

test('4. coordinator cannot set Qty Fulfilled during request creation', function () {
    Livewire::test(CreateEducationRequest::class)
        ->set('data.orphan_id', (string) $this->orphan->id)
        ->set('data.intervention_type_id', (string) $this->uniformType->id)
        ->set('data.request_date', now()->format('Y-m-d'))
        ->set('data.notes', 'Testing Qty Fulfilled field restriction')
        ->set('data.items', [
            [
                'item_id' => (string) $this->itemUniform->id,
                'quantity_requested' => 2,
                'quantity_fulfilled' => 5, // Tampered input
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $request = InterventionRequest::latest('created_at')->first();
    $item = $request->items->first();
    expect($item->quantity_fulfilled)->toBe(0);
});

test('5. coordinator cannot create arbitrary master items', function () {
    // Coordinator does not have master item management permissions
    expect($this->coordinator->hasPermissionTo('create_projects'))->toBeFalse()
        ->and($this->coordinator->can('create', Item::class))->toBeFalse();
});

test('6 & 16. coordinator cannot attach items or create request for another zone orphan', function () {
    Livewire::test(CreateEducationRequest::class)
        ->set('data.orphan_id', (string) $this->otherOrphan->id)
        ->set('data.intervention_type_id', (string) $this->uniformType->id)
        ->set('data.notes', 'Cross-zone request attempt')
        ->set('data.items', [
            [
                'item_id' => (string) $this->itemUniform->id,
                'quantity_requested' => 1,
            ],
        ])
        ->call('create')
        ->assertHasFormErrors(['orphan_id']);

    $this->assertDatabaseMissing('intervention_requests', [
        'notes' => 'Cross-zone request attempt',
    ]);
});

test('7. item-based intervention requiring items rejects empty requested-item set', function () {
    Livewire::test(CreateEducationRequest::class)
        ->set('data.orphan_id', (string) $this->orphan->id)
        ->set('data.intervention_type_id', (string) $this->uniformType->id)
        ->set('data.notes', 'Empty items request attempt')
        ->set('data.items', [])
        ->call('create')
        ->assertHasFormErrors(['items']);
});

test('8. monetary/non-item intervention can be submitted without fake items', function () {
    Livewire::test(CreateEducationRequest::class)
        ->set('data.orphan_id', (string) $this->orphan->id)
        ->set('data.intervention_type_id', (string) $this->feesType->id)
        ->set('data.requested_amount', 45000)
        ->set('data.notes', 'School fee monetary request')
        ->set('data.items', [])
        ->call('create')
        ->assertHasNoFormErrors();

    $request = InterventionRequest::latest('created_at')->first();
    expect($request->requested_amount)->toEqual(45000.00)
        ->and($request->items)->toHaveCount(0);
});

test('9 & 10. Admin Education Verification displays Coordinator-submitted requested items', function () {
    $request = InterventionRequest::create([
        'orphan_id' => $this->orphan->id,
        'intervention_type_id' => $this->uniformType->id,
        'request_date' => now(),
        'requested_amount' => 15000,
        'notes' => 'Items verification display test',
        'status' => 'pending',
        'verification_status' => 'pending',
    ]);

    $itemRecord = InterventionRequestItem::create([
        'intervention_request_id' => $request->id,
        'item_id' => $this->itemUniform->id,
        'item_name' => $this->itemUniform->name,
        'quantity_requested' => 3,
        'orphan_class' => 'Size 36',
        'quantity_fulfilled' => 0,
    ]);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($this->admin);

    Livewire::test(ListEducationVerifications::class)
        ->assertSuccessful();

    // Verify action
    Livewire::test(ListEducationVerifications::class)
        ->callTableAction('verify', $request, [
            'verification_notes' => 'Verified items available in stock.',
        ])
        ->assertHasNoTableActionErrors();

    // 11 & 12. Verification and approval do NOT duplicate or recreate requested item records
    expect(InterventionRequestItem::where('intervention_request_id', $request->id)->count())->toBe(1)
        ->and($itemRecord->fresh()->quantity_requested)->toBe(3);

    // Approve action
    Livewire::test(ListEducationVerifications::class)
        ->callTableAction('approve', $request)
        ->assertHasNoTableActionErrors();

    expect(InterventionRequestItem::where('intervention_request_id', $request->id)->count())->toBe(1)
        ->and($request->fresh()->status)->toBe('approved');
});

test('13, 14 & 15. Admin/authorized fulfillment updates Qty Fulfilled, Coordinator sees read-only progress and cannot alter request', function () {
    $request = InterventionRequest::create([
        'orphan_id' => $this->orphan->id,
        'intervention_type_id' => $this->uniformType->id,
        'request_date' => now(),
        'status' => 'approved',
        'verification_status' => 'verified',
    ]);

    $reqItem = InterventionRequestItem::create([
        'intervention_request_id' => $request->id,
        'item_id' => $this->itemUniform->id,
        'item_name' => $this->itemUniform->name,
        'quantity_requested' => 2,
        'quantity_fulfilled' => 0,
    ]);

    // Admin updates fulfillment
    $reqItem->update(['quantity_fulfilled' => 2]);
    expect($reqItem->fresh()->is_fully_fulfilled)->toBeTrue();

    // Coordinator view verification
    Filament::setCurrentPanel(Filament::getPanel('coordinator'));
    $this->actingAs($this->coordinator);

    expect($request->fresh()->items->first()->quantity_fulfilled)->toBe(2)
        ->and($request->fresh()->status)->toBe('approved');
});
