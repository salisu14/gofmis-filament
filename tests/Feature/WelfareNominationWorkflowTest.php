<?php

namespace Tests\Feature;

use App\Enums\BeneficiaryStatus;
use App\Enums\CollectionStatus;
use App\Enums\StockMovementType;
use App\Enums\WelfarePackageStatus;
use App\Filament\Coordinator\Resources\WelfareRequestResource;
use App\Models\Category;
use App\Models\Deceased;
use App\Models\Item;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\WelfareBeneficiary;
use App\Models\WelfarePackage;
use App\Models\WelfarePackageItem;
use App\Models\Widow;
use App\Models\Zone;
use App\Services\BeneficiaryService;
use App\Services\Welfare\WelfareNominationService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WelfareNominationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected Zone $zoneA;

    protected Zone $zoneB;

    protected User $coordinatorZoneA;

    protected User $coordinatorZoneB;

    protected User $adminUser;

    protected WelfarePackage $openPackage;

    protected Deceased $deceasedZoneA;

    protected Deceased $deceasedZoneB;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('coordinator'));

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $coordinatorRole = Role::firstOrCreate(['name' => 'coordinator', 'guard_name' => 'web']);

        $viewWelfare = Permission::firstOrCreate(['name' => 'view_welfare_interventions', 'guard_name' => 'web']);
        $createWelfare = Permission::firstOrCreate(['name' => 'create_welfare_interventions', 'guard_name' => 'web']);
        $coordinatorRole->givePermissionTo([$viewWelfare, $createWelfare]);

        $this->zoneA = Zone::create(['name' => 'Zone Alpha', 'code' => 'ZA']);
        $this->zoneB = Zone::create(['name' => 'Zone Beta', 'code' => 'ZB']);

        $this->coordinatorZoneA = User::factory()->create(['name' => 'Coordinator Zone A']);
        $this->coordinatorZoneA->assignRole('coordinator');
        $this->zoneA->update(['coordinator_id' => $this->coordinatorZoneA->id]);

        $this->coordinatorZoneB = User::factory()->create(['name' => 'Coordinator Zone B']);
        $this->coordinatorZoneB->assignRole('coordinator');
        $this->zoneB->update(['coordinator_id' => $this->coordinatorZoneB->id]);

        $this->adminUser = User::factory()->create(['name' => 'Admin User']);
        $this->adminUser->assignRole('admin');

        $this->coordinatorZoneA = $this->coordinatorZoneA->fresh();
        $this->coordinatorZoneB = $this->coordinatorZoneB->fresh();

        $category = Category::create(['name' => 'Food Category', 'user_id' => $this->adminUser->id]);
        $item = Item::create([
            'name' => 'Rice Bag 50kg',
            'category_id' => $category->id,
            'user_id' => $this->adminUser->id,
            'unit_of_measure' => 'bag',
            'reorder_level' => 10,
            'is_active' => true,
        ]);

        StockMovement::create([
            'item_id' => $item->id,
            'movement_type' => StockMovementType::OPENING_BALANCE,
            'quantity' => 1000,
            'occurred_at' => now(),
            'created_by' => $this->adminUser->id,
        ]);

        $this->openPackage = WelfarePackage::create([
            'name' => 'Ramadan Food Package 2026',
            'description' => 'Annual food package distribution',
            'status' => WelfarePackageStatus::OPEN,
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth(),
            'created_by' => $this->adminUser->id,
        ]);

        WelfarePackageItem::create([
            'welfare_package_id' => $this->openPackage->id,
            'item_id' => $item->id,
            'quantity_per_family' => 1,
        ]);

        $this->deceasedZoneA = Deceased::factory()->create([
            'first_name' => 'DeceasedA',
            'last_name' => 'Alpha',
            'zone_id' => $this->zoneA->id,
        ]);

        Widow::create([
            'deceased_id' => $this->deceasedZoneA->id,
            'first_name' => 'WidowA',
            'last_name' => 'Alpha',
            'nin' => '12345678901',
            'reg_no' => 'WID-ZA-001',
            'address' => '123 Main St',
            'child_sequence' => 1,
            'is_eligible' => true,
            'is_married' => false,
            'marital_status' => 'WIDOWED',
        ]);

        $this->deceasedZoneB = Deceased::factory()->create([
            'first_name' => 'DeceasedB',
            'last_name' => 'Beta',
            'zone_id' => $this->zoneB->id,
        ]);

        Widow::create([
            'deceased_id' => $this->deceasedZoneB->id,
            'first_name' => 'WidowB',
            'last_name' => 'Beta',
            'nin' => '12345678902',
            'reg_no' => 'WID-ZB-001',
            'address' => '123 Main St',
            'child_sequence' => 1,
            'is_eligible' => true,
            'is_married' => false,
            'marital_status' => 'WIDOWED',
        ]);
    }

    public function test_1_coordinator_can_list_own_zone_welfare_nominations(): void
    {
        $nominationA = WelfareBeneficiary::create([
            'welfare_package_id' => $this->openPackage->id,
            'deceased_id' => $this->deceasedZoneA->id,
            'suggested_by' => $this->coordinatorZoneA->id,
            'status' => BeneficiaryStatus::PENDING,
            'collection_status' => CollectionStatus::NOT_COLLECTED,
        ]);

        $this->actingAs($this->coordinatorZoneA);

        Livewire::test(WelfareRequestResource\Pages\ListWelfareRequests::class)
            ->assertCanSeeTableRecords([$nominationA]);
    }

    public function test_2_coordinator_cannot_see_another_zones_nominations(): void
    {
        $nominationB = WelfareBeneficiary::create([
            'welfare_package_id' => $this->openPackage->id,
            'deceased_id' => $this->deceasedZoneB->id,
            'suggested_by' => $this->coordinatorZoneB->id,
            'status' => BeneficiaryStatus::PENDING,
            'collection_status' => CollectionStatus::NOT_COLLECTED,
        ]);

        $this->actingAs($this->coordinatorZoneA);

        Livewire::test(WelfareRequestResource\Pages\ListWelfareRequests::class)
            ->assertCanNotSeeTableRecords([$nominationB]);
    }

    public function test_3_coordinator_can_create_nomination_for_eligible_own_zone_beneficiary(): void
    {
        $this->actingAs($this->coordinatorZoneA);

        Livewire::test(WelfareRequestResource\Pages\CreateWelfareRequest::class)
            ->set('data.welfare_package_id', (string) $this->openPackage->id)
            ->set('data.deceased_id', (string) $this->deceasedZoneA->id)
            ->set('data.collection_notes', 'Urgent support needed')
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('welfare_beneficiaries', [
            'welfare_package_id' => $this->openPackage->id,
            'deceased_id' => $this->deceasedZoneA->id,
            'suggested_by' => $this->coordinatorZoneA->id,
            'status' => BeneficiaryStatus::PENDING->value,
        ]);
    }

    public function test_4_coordinator_cannot_nominate_cross_zone_beneficiary(): void
    {
        $this->actingAs($this->coordinatorZoneA);

        Livewire::test(WelfareRequestResource\Pages\CreateWelfareRequest::class)
            ->set('data.welfare_package_id', (string) $this->openPackage->id)
            ->set('data.deceased_id', (string) $this->deceasedZoneB->id)
            ->call('create')
            ->assertHasFormErrors(['deceased_id']);

        $this->assertDatabaseMissing('welfare_beneficiaries', [
            'welfare_package_id' => $this->openPackage->id,
            'deceased_id' => $this->deceasedZoneB->id,
        ]);
    }

    public function test_5_livewire_tampering_with_beneficiary_id_is_rejected(): void
    {
        $this->actingAs($this->coordinatorZoneA);

        Livewire::test(WelfareRequestResource\Pages\CreateWelfareRequest::class)
            ->set('data.welfare_package_id', (string) $this->openPackage->id)
            ->set('data.deceased_id', (string) $this->deceasedZoneB->id)
            ->call('create')
            ->assertHasFormErrors(['deceased_id']);
    }

    public function test_6_package_selection_persists_correctly(): void
    {
        $this->actingAs($this->coordinatorZoneA);

        $beneficiary = app(WelfareNominationService::class)
            ->nominateSingle($this->openPackage->id, $this->deceasedZoneA->id, $this->coordinatorZoneA);

        $this->assertEquals($this->openPackage->id, $beneficiary->welfare_package_id);
    }

    public function test_7_required_fields_are_validated(): void
    {
        $this->actingAs($this->coordinatorZoneA);

        Livewire::test(WelfareRequestResource\Pages\CreateWelfareRequest::class)
            ->set('data.welfare_package_id', null)
            ->set('data.deceased_id', null)
            ->call('create')
            ->assertHasFormErrors(['welfare_package_id', 'deceased_id']);
    }

    public function test_8_duplicate_submission_does_not_create_duplicate_active_nomination(): void
    {
        $this->actingAs($this->coordinatorZoneA);

        Livewire::test(WelfareRequestResource\Pages\CreateWelfareRequest::class)
            ->set('data.welfare_package_id', (string) $this->openPackage->id)
            ->set('data.deceased_id', (string) $this->deceasedZoneA->id)
            ->call('create')
            ->assertHasNoFormErrors();

        Livewire::test(WelfareRequestResource\Pages\CreateWelfareRequest::class)
            ->set('data.welfare_package_id', (string) $this->openPackage->id)
            ->set('data.deceased_id', (string) $this->deceasedZoneA->id)
            ->call('create')
            ->assertHasFormErrors(['deceased_id']);

        $this->assertEquals(1, WelfareBeneficiary::where('welfare_package_id', $this->openPackage->id)->where('deceased_id', $this->deceasedZoneA->id)->count());
    }

    public function test_9_historical_assistance_does_not_block_legitimate_future_nomination(): void
    {
        $this->actingAs($this->coordinatorZoneA);

        WelfareBeneficiary::create([
            'welfare_package_id' => $this->openPackage->id,
            'deceased_id' => $this->deceasedZoneA->id,
            'suggested_by' => $this->coordinatorZoneA->id,
            'status' => BeneficiaryStatus::APPROVED,
            'collection_status' => CollectionStatus::COLLECTED,
            'collected_at' => now(),
        ]);

        $package2 = WelfarePackage::create([
            'name' => 'Eid Relief Package 2026',
            'status' => WelfarePackageStatus::OPEN,
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth(),
            'created_by' => $this->adminUser->id,
        ]);

        $nomination2 = app(WelfareNominationService::class)
            ->nominateSingle($package2->id, $this->deceasedZoneA->id, $this->coordinatorZoneA);

        $this->assertInstanceOf(WelfareBeneficiary::class, $nomination2);
        $this->assertEquals($package2->id, $nomination2->welfare_package_id);
    }

    public function test_10_pending_to_approved_transition_works_for_authorized_admin(): void
    {
        $nomination = WelfareBeneficiary::create([
            'welfare_package_id' => $this->openPackage->id,
            'deceased_id' => $this->deceasedZoneA->id,
            'suggested_by' => $this->coordinatorZoneA->id,
            'status' => BeneficiaryStatus::PENDING,
            'collection_status' => CollectionStatus::NOT_COLLECTED,
        ]);

        $approved = app(BeneficiaryService::class)->approveBeneficiary($nomination, $this->adminUser->id);

        $this->assertTrue($approved->isApproved());
        $this->assertEquals($this->adminUser->id, $approved->approved_by);
    }

    public function test_11_coordinator_cannot_approve_directly(): void
    {
        $nomination = WelfareBeneficiary::create([
            'welfare_package_id' => $this->openPackage->id,
            'deceased_id' => $this->deceasedZoneA->id,
            'suggested_by' => $this->coordinatorZoneA->id,
            'status' => BeneficiaryStatus::PENDING,
            'collection_status' => CollectionStatus::NOT_COLLECTED,
        ]);

        $this->actingAs($this->coordinatorZoneA);

        $this->assertFalse($this->coordinatorZoneA->can('approve', $nomination));
    }

    public function test_12_pending_or_rejected_request_cannot_be_marked_collected(): void
    {
        $pending = WelfareBeneficiary::create([
            'welfare_package_id' => $this->openPackage->id,
            'deceased_id' => $this->deceasedZoneA->id,
            'suggested_by' => $this->coordinatorZoneA->id,
            'status' => BeneficiaryStatus::PENDING,
            'collection_status' => CollectionStatus::NOT_COLLECTED,
        ]);

        $this->expectException(\RuntimeException::class);
        app(BeneficiaryService::class)->collectPackage($pending, 'Notes', $this->adminUser->id);
    }

    public function test_13_approved_request_can_be_marked_collected_once(): void
    {
        $nomination = WelfareBeneficiary::create([
            'welfare_package_id' => $this->openPackage->id,
            'deceased_id' => $this->deceasedZoneA->id,
            'suggested_by' => $this->coordinatorZoneA->id,
            'status' => BeneficiaryStatus::APPROVED,
            'collection_status' => CollectionStatus::NOT_COLLECTED,
        ]);

        $collected = app(BeneficiaryService::class)->collectPackage($nomination, 'Collected at center', $this->adminUser->id);

        $this->assertTrue($collected->isCollected());
        $this->assertNotNull($collected->collected_at);
        $this->assertEquals($this->adminUser->id, $collected->collected_by);
    }

    public function test_14_second_collection_attempt_is_rejected_safely(): void
    {
        $nomination = WelfareBeneficiary::create([
            'welfare_package_id' => $this->openPackage->id,
            'deceased_id' => $this->deceasedZoneA->id,
            'suggested_by' => $this->coordinatorZoneA->id,
            'status' => BeneficiaryStatus::APPROVED,
            'collection_status' => CollectionStatus::NOT_COLLECTED,
        ]);

        app(BeneficiaryService::class)->collectPackage($nomination, 'First collection', $this->adminUser->id);

        $this->expectException(\RuntimeException::class);
        app(BeneficiaryService::class)->collectPackage($nomination, 'Second collection', $this->adminUser->id);
    }

    public function test_15_collected_at_persists_correctly(): void
    {
        $nomination = WelfareBeneficiary::create([
            'welfare_package_id' => $this->openPackage->id,
            'deceased_id' => $this->deceasedZoneA->id,
            'suggested_by' => $this->coordinatorZoneA->id,
            'status' => BeneficiaryStatus::APPROVED,
            'collection_status' => CollectionStatus::NOT_COLLECTED,
        ]);

        $before = now()->subSecond();
        app(BeneficiaryService::class)->collectPackage($nomination, 'Notes', $this->adminUser->id);

        $fresh = $nomination->fresh();
        $this->assertTrue($fresh->collected_at->gte($before));
    }

    public function test_16_collected_record_is_deletable_blocked(): void
    {
        $nomination = WelfareBeneficiary::create([
            'welfare_package_id' => $this->openPackage->id,
            'deceased_id' => $this->deceasedZoneA->id,
            'suggested_by' => $this->coordinatorZoneA->id,
            'status' => BeneficiaryStatus::APPROVED,
            'collection_status' => CollectionStatus::NOT_COLLECTED,
        ]);

        app(BeneficiaryService::class)->collectPackage($nomination, 'Notes', $this->adminUser->id);

        $this->expectException(\DomainException::class);
        $nomination->fresh()->delete();
    }

    public function test_17_ineligible_beneficiary_cannot_receive_new_nomination(): void
    {
        $deceasedIneligible = Deceased::factory()->create([
            'zone_id' => $this->zoneA->id,
        ]);

        Widow::create([
            'deceased_id' => $deceasedIneligible->id,
            'first_name' => 'WidowRemarried',
            'last_name' => 'Ineligible',
            'nin' => '12345678999',
            'reg_no' => 'WID-ZA-099',
            'address' => '123 Main St',
            'child_sequence' => 1,
            'is_eligible' => false,
            'is_married' => true,
            'marital_status' => 'REMARRIED',
        ]);

        $result = app(WelfareNominationService::class)
            ->nominate($this->openPackage->id, [$deceasedIneligible->id], $this->coordinatorZoneA);

        $this->assertEquals(0, $result['nominated_count']);
        $this->assertEquals(1, $result['ineligible_count']);
    }

    public function test_18_cross_zone_direct_url_returns_denied(): void
    {
        $nominationB = WelfareBeneficiary::create([
            'welfare_package_id' => $this->openPackage->id,
            'deceased_id' => $this->deceasedZoneB->id,
            'suggested_by' => $this->coordinatorZoneB->id,
            'status' => BeneficiaryStatus::PENDING,
            'collection_status' => CollectionStatus::NOT_COLLECTED,
        ]);

        $this->actingAs($this->coordinatorZoneA);

        $response = $this->get(WelfareRequestResource::getUrl('view', ['record' => $nominationB->id], panel: 'coordinator'));
        $this->assertTrue(in_array($response->status(), [403, 404]));
    }

    public function test_19_cross_zone_livewire_mutation_is_rejected(): void
    {
        $nominationB = WelfareBeneficiary::create([
            'welfare_package_id' => $this->openPackage->id,
            'deceased_id' => $this->deceasedZoneB->id,
            'suggested_by' => $this->coordinatorZoneB->id,
            'status' => BeneficiaryStatus::PENDING,
            'collection_status' => CollectionStatus::NOT_COLLECTED,
        ]);

        $this->actingAs($this->coordinatorZoneA);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        Livewire::test(WelfareRequestResource\Pages\EditWelfareRequest::class, ['record' => $nominationB->id]);
    }

    public function test_20_admin_global_welfare_access_remains_intact(): void
    {
        $nominationA = WelfareBeneficiary::create([
            'welfare_package_id' => $this->openPackage->id,
            'deceased_id' => $this->deceasedZoneA->id,
            'suggested_by' => $this->coordinatorZoneA->id,
            'status' => BeneficiaryStatus::PENDING,
            'collection_status' => CollectionStatus::NOT_COLLECTED,
        ]);

        $nominationB = WelfareBeneficiary::create([
            'welfare_package_id' => $this->openPackage->id,
            'deceased_id' => $this->deceasedZoneB->id,
            'suggested_by' => $this->coordinatorZoneB->id,
            'status' => BeneficiaryStatus::PENDING,
            'collection_status' => CollectionStatus::NOT_COLLECTED,
        ]);

        $this->actingAs($this->adminUser);

        Livewire::test(WelfareRequestResource\Pages\ListWelfareRequests::class)
            ->assertCanSeeTableRecords([$nominationA, $nominationB]);
    }

    public function test_21_existing_welfare_filters_remain_functional(): void
    {
        $pending = WelfareBeneficiary::create([
            'welfare_package_id' => $this->openPackage->id,
            'deceased_id' => $this->deceasedZoneA->id,
            'suggested_by' => $this->coordinatorZoneA->id,
            'status' => BeneficiaryStatus::PENDING,
            'collection_status' => CollectionStatus::NOT_COLLECTED,
        ]);

        $approved = WelfareBeneficiary::create([
            'welfare_package_id' => $this->openPackage->id,
            'deceased_id' => $this->deceasedZoneB->id,
            'suggested_by' => $this->coordinatorZoneB->id,
            'status' => BeneficiaryStatus::APPROVED,
            'collection_status' => CollectionStatus::NOT_COLLECTED,
        ]);

        $this->actingAs($this->adminUser);

        Livewire::test(WelfareRequestResource\Pages\ListWelfareRequests::class)
            ->filterTable('status', BeneficiaryStatus::PENDING->value)
            ->assertCanSeeTableRecords([$pending])
            ->assertCanNotSeeTableRecords([$approved]);
    }

    public function test_22_page_reload_after_create_retains_correct_state(): void
    {
        $this->actingAs($this->coordinatorZoneA);

        $beneficiary = app(WelfareNominationService::class)
            ->nominateSingle($this->openPackage->id, $this->deceasedZoneA->id, $this->coordinatorZoneA);

        $fresh = $beneficiary->fresh();

        $this->assertEquals(BeneficiaryStatus::PENDING, $fresh->status);
        $this->assertEquals(CollectionStatus::NOT_COLLECTED, $fresh->collection_status);
        $this->assertNull($fresh->collected_at);
    }

    public function test_23_rejected_same_package_renomination_is_blocked(): void
    {
        $this->actingAs($this->coordinatorZoneA);

        WelfareBeneficiary::create([
            'welfare_package_id' => $this->openPackage->id,
            'deceased_id' => $this->deceasedZoneA->id,
            'suggested_by' => $this->coordinatorZoneA->id,
            'status' => BeneficiaryStatus::REJECTED,
            'rejection_reason' => 'Ineligible request',
            'collection_status' => CollectionStatus::NOT_COLLECTED,
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(WelfareNominationService::class)
            ->nominateSingle($this->openPackage->id, $this->deceasedZoneA->id, $this->coordinatorZoneA);
    }
}
