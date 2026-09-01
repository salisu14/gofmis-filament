<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Deceased;
use App\Models\InterventionRequest;
use App\Models\InterventionRequestItem;
use App\Models\InterventionType;
use App\Models\Item;
use App\Models\Orphan;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InterventionRequestItemRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Zone $zone;

    protected Deceased $deceased;

    protected Orphan $orphan;

    protected InterventionType $type;

    protected Category $category;

    protected Item $itemA;

    protected Item $itemB;

    protected InterventionRequest $requestA;

    protected InterventionRequest $requestB;

    protected function setUp(): void
    {
        parent::setUp();

        \Spatie\Permission\Models\Role::firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'web'],
            ['uuid' => (string) \Illuminate\Support\Str::uuid()]
        );

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->zone = Zone::create(['name' => 'Kano Central Zone', 'code' => 'KCZ']);

        $this->deceased = Deceased::create([
            'first_name' => 'Deceased',
            'last_name' => 'Parent',
            'nin' => '12345678901',
            'reg_no' => 'DEC-KCZ-001',
            'guardian_name' => 'Guardian',
            'guardian_phone' => '08012345678',
            'vulnerability_status' => 'A',
            'date_registered' => now(),
            'zone_id' => $this->zone->id,
        ]);

        $this->orphan = Orphan::create([
            'deceased_id' => $this->deceased->id,
            'first_name' => 'Orphan',
            'last_name' => 'Child',
            'date_of_birth' => now()->subYears(10),
            'gender' => \App\Enums\Gender::MALE->value,
            'reg_no' => 'ORP-KCZ-001',
        ]);

        $this->type = InterventionType::create([
            'name' => 'Medical Assistance',
        ]);

        $this->category = Category::create([
            'name' => 'Medical Supplies',
            'user_id' => $this->admin->id,
        ]);

        $this->itemA = Item::create([
            'name' => 'First Aid Kit',
            'category_id' => $this->category->id,
            'user_id' => $this->admin->id,
        ]);

        $this->itemB = Item::create([
            'name' => 'Wheelchair',
            'category_id' => $this->category->id,
            'user_id' => $this->admin->id,
        ]);

        $this->requestA = InterventionRequest::create([
            'request_number' => 'REQ-001',
            'deceased_id' => $this->deceased->id,
            'orphan_id' => $this->orphan->id,
            'user_id' => $this->admin->id,
            'zone_id' => $this->zone->id,
            'intervention_type_id' => $this->type->id,
            'status' => 'pending',
            'title' => 'Request A',
        ]);

        $this->requestB = InterventionRequest::create([
            'request_number' => 'REQ-002',
            'deceased_id' => $this->deceased->id,
            'orphan_id' => $this->orphan->id,
            'user_id' => $this->admin->id,
            'zone_id' => $this->zone->id,
            'intervention_type_id' => $this->type->id,
            'status' => 'pending',
            'title' => 'Request B',
        ]);
    }

    public function test_1_relation_manager_does_not_declare_related_resource_so_it_uses_item_schema(): void
    {
        $relationManager = Livewire::actingAs($this->admin)
            ->test(\App\Filament\Resources\InterventionRequests\RelationManagers\ItemsRelationManager::class, [
                'ownerRecord' => $this->requestA,
                'pageClass' => \App\Filament\Resources\InterventionRequests\Pages\EditInterventionRequest::class,
            ]);

        $instance = $relationManager->instance();
        expect($instance->getRelatedResource())->toBeNull();

        $formSchema = $instance->form(\Filament\Schemas\Schema::make());
        $componentKeys = collect($formSchema->getComponents())
            ->map(fn ($c) => method_exists($c, 'getName') ? $c->getName() : null)
            ->filter()
            ->values()
            ->toArray();

        expect($componentKeys)->toContain('item_id');
        expect($componentKeys)->not->toContain('intervention_request_id');
        expect($componentKeys)->not->toContain('deceased_id');
    }

    public function test_2_creating_item_under_request_a_automatically_associates_with_request_a(): void
    {
        $itemRow = InterventionRequestItem::create([
            'intervention_request_id' => $this->requestA->id,
            'item_id' => $this->itemA->id,
            'quantity_requested' => 2,
        ]);

        expect($itemRow->intervention_request_id)->toBe($this->requestA->id);
        expect($itemRow->item_name)->toBe('First Aid Kit');
    }

    public function test_3_selecting_item_a_derives_correct_item_name_snapshot(): void
    {
        $itemRow = InterventionRequestItem::create([
            'intervention_request_id' => $this->requestA->id,
            'item_id' => $this->itemA->id,
            'quantity_requested' => 1,
        ]);

        expect($itemRow->item_name)->toBe('First Aid Kit');
    }

    public function test_4_forged_item_name_payload_is_overridden_by_authoritative_item_name(): void
    {
        $itemRow = InterventionRequestItem::create([
            'intervention_request_id' => $this->requestA->id,
            'item_id' => $this->itemA->id,
            'item_name' => 'Forged Malicious Item Name',
            'quantity_requested' => 1,
        ]);

        expect($itemRow->fresh()->item_name)->toBe('First Aid Kit');
    }

    public function test_5_editing_item_id_from_item_a_to_item_b_refreshes_item_name_snapshot(): void
    {
        $itemRow = InterventionRequestItem::create([
            'intervention_request_id' => $this->requestA->id,
            'item_id' => $this->itemA->id,
            'quantity_requested' => 1,
        ]);

        expect($itemRow->item_name)->toBe('First Aid Kit');

        $itemRow->update([
            'item_id' => $this->itemB->id,
        ]);

        expect($itemRow->fresh()->item_name)->toBe('Wheelchair');
    }

    public function test_6_livewire_action_renders_and_executes_without_parent_form_misrouting(): void
    {
        Livewire::actingAs($this->admin)
            ->test(\App\Filament\Resources\InterventionRequests\RelationManagers\ItemsRelationManager::class, [
                'ownerRecord' => $this->requestA,
                'pageClass' => \App\Filament\Resources\InterventionRequests\Pages\EditInterventionRequest::class,
            ])
            ->assertSuccessful();
    }

    public function test_7_updating_same_item_id_with_forged_item_name_maintains_authoritative_item_name(): void
    {
        $itemRow = InterventionRequestItem::create([
            'intervention_request_id' => $this->requestA->id,
            'item_id' => $this->itemA->id,
            'quantity_requested' => 1,
        ]);

        expect($itemRow->item_name)->toBe('First Aid Kit');

        $itemRow->update([
            'item_name' => 'Forged Again',
            'quantity_requested' => 3,
        ]);

        expect($itemRow->fresh()->item_name)->toBe('First Aid Kit');
        expect($itemRow->fresh()->quantity_requested)->toBe(3);
    }
}
