<?php

namespace Tests\Feature;

use App\Enums\BeneficiaryStatus;
use App\Enums\CollectionStatus;
use App\Enums\Gender;
use App\Enums\OrphanStatus;
use App\Enums\StockMovementType;
use App\Enums\UserStatus;
use App\Enums\VulnerabilityStatus;
use App\Enums\WelfarePackageStatus;
use App\Filament\Resources\WelfarePackages\RelationManagers\BeneficiariesRelationManager;
use App\Models\Deceased;
use App\Models\Item;
use App\Models\Orphan;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\WelfareBeneficiary;
use App\Models\WelfarePackage;
use App\Models\WelfarePackageItem;
use App\Models\Widow;
use App\Models\Zone;
use App\Services\BeneficiaryService;
use App\Services\Welfare\WelfareNominationService;
use App\Services\Welfare\WelfarePackageLifecycleService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use RuntimeException;

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

    $this->zoneA = Zone::create(['name' => 'Zone North', 'code' => 'ZN-01']);
    $this->zoneB = Zone::create(['name' => 'Zone South', 'code' => 'ZS-02']);

    $this->admin = User::factory()->create(['status' => UserStatus::ACTIVE]);
    $this->admin->assignRole('admin');

    $this->coordinator = User::factory()->create(['status' => UserStatus::ACTIVE]);
    $this->coordinator->assignRole('coordinator');
    $this->zoneA->update(['coordinator_id' => $this->coordinator->id]);

    $this->package = WelfarePackage::create([
        'name' => 'Regression Package',
        'start_date' => now()->subDays(2),
        'end_date' => now()->addDays(10),
        'status' => WelfarePackageStatus::OPEN,
        'created_by' => $this->admin->id,
    ]);

    $this->category = \App\Models\Category::create([
        'name' => 'Regression Category',
        'user_id' => $this->admin->id,
    ]);

    $this->item = Item::create([
        'name' => 'Regression Item',
        'category_id' => $this->category->id,
        'user_id' => $this->admin->id,
    ]);

    WelfarePackageItem::create([
        'welfare_package_id' => $this->package->id,
        'item_id' => $this->item->id,
        'quantity_per_family' => 1,
    ]);
});

if (! function_exists('makeHousehold')) {
    function makeHousehold(string $nin, string $regNo, string $zoneId, bool $eligibleWidow = true, bool $eligibleOrphan = false): Deceased
    {
        $deceased = Deceased::create([
            'first_name' => 'Family',
            'last_name' => 'Head',
            'nin' => $nin,
            'reg_no' => $regNo,
            'guardian_name' => 'Guardian',
            'guardian_phone' => '08012345678',
            'date_registered' => now()->subMonths(1),
            'date_of_death' => now()->subMonths(2),
            'zone_id' => $zoneId,
            'vulnerability_status' => VulnerabilityStatus::B,
        ]);

        if ($eligibleWidow) {
            Widow::create([
                'first_name' => 'Widow',
                'last_name' => 'Head',
                'nin' => $nin.'W',
                'reg_no' => $regNo.'-W',
                'child_sequence' => 1,
                'deceased_id' => $deceased->id,
                'is_eligible' => true,
                'is_married' => false,
            ]);
        }

        if ($eligibleOrphan) {
            Orphan::create([
                'first_name' => 'Orphan',
                'last_name' => 'Head',
                'reg_no' => $regNo.'-O',
                'child_sequence' => 1,
                'gender' => Gender::FEMALE,
                'birth_date' => now()->subYears(10),
                'deceased_id' => $deceased->id,
                'status' => OrphanStatus::ACTIVE,
                'is_eligible' => true,
                'is_married' => false,
            ]);
        }

        return $deceased;
    }
}

if (! function_exists('addStock')) {
    function addStock(string $itemId, int $quantity): void
    {
        StockMovement::create([
            'item_id' => $itemId,
            'movement_type' => StockMovementType::OPENING_BALANCE,
            'quantity' => $quantity,
            'occurred_at' => now(),
            'created_by' => null,
        ]);
    }
}

// ─── 1-3. BeneficiariesRelationManager nomination guards ─────────────────────

test('1. beneficiaries relation manager cannot nominate cross-zone household', function () {
    $crossZone = makeHousehold('80000000001', 'REG-XZ01', $this->zoneB->id);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($this->coordinator);

    $component = Livewire::test(BeneficiariesRelationManager::class, [
        'ownerRecord' => $this->package,
        'pageClass' => \App\Filament\Resources\WelfarePackages\Pages\EditWelfarePackage::class,
    ]);

    $component->callTableAction('create', data: [
        'deceased_id' => $crossZone->id,
    ]);

    expect(WelfareBeneficiary::where('deceased_id', $crossZone->id)->exists())->toBeFalse();
});

test('2. beneficiaries relation manager cannot nominate ineligible household', function () {
    $ineligible = makeHousehold('80000000002', 'REG-IN01', $this->zoneA->id, eligibleWidow: false);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($this->coordinator);

    $component = Livewire::test(BeneficiariesRelationManager::class, [
        'ownerRecord' => $this->package,
        'pageClass' => \App\Filament\Resources\WelfarePackages\Pages\EditWelfarePackage::class,
    ]);

    $component->callTableAction('create', data: [
        'deceased_id' => $ineligible->id,
    ]);

    expect(WelfareBeneficiary::where('deceased_id', $ineligible->id)->exists())->toBeFalse();
});

test('3. beneficiaries relation manager cannot nominate into non-OPEN package', function () {
    $closed = WelfarePackage::create([
        'name' => 'Closed Pkg',
        'start_date' => now()->subDays(2),
        'end_date' => now()->addDays(10),
        'status' => WelfarePackageStatus::CLOSED,
        'created_by' => $this->admin->id,
    ]);

    $household = makeHousehold('80000000003', 'REG-CL01', $this->zoneA->id);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($this->admin);

    $component = Livewire::test(BeneficiariesRelationManager::class, [
        'ownerRecord' => $closed,
        'pageClass' => \App\Filament\Resources\WelfarePackages\Pages\EditWelfarePackage::class,
    ]);

    // The create action is hidden for non-OPEN packages; even if invoked
    // server-side, the canonical service rejects it.
    $component->assertTableActionHidden('create');

    expect(WelfareBeneficiary::where('deceased_id', $household->id)->exists())->toBeFalse();
});

// ─── 4. Coordinator CreateWelfareRequest uses canonical semantics ────────────

test('4. coordinator create welfare request rejects cross-zone household via canonical service', function () {
    $crossZone = makeHousehold('80000000004', 'REG-CZ01', $this->zoneB->id);

    Filament::setCurrentPanel(Filament::getPanel('coordinator'));
    $this->actingAs($this->coordinator);

    Livewire::test(\App\Filament\Coordinator\Resources\WelfareRequestResource\Pages\CreateWelfareRequest::class)
        ->set('data.welfare_package_id', (string) $this->package->id)
        ->set('data.deceased_id', (string) $crossZone->id)
        ->call('create')
        ->assertHasFormErrors(['deceased_id']);

    expect(WelfareBeneficiary::where('deceased_id', $crossZone->id)->exists())->toBeFalse();
});

// ─── 5. BeneficiaryService cannot bypass canonical nomination rules ──────────

test('5. beneficiary service suggest rejects ineligible household', function () {
    $ineligible = makeHousehold('80000000005', 'REG-BS01', $this->zoneA->id, eligibleWidow: false);

    $service = app(BeneficiaryService::class);

    expect(fn () => $service->suggestBeneficiary($this->package, $ineligible->id, $this->admin->id))
        ->toThrow(\Illuminate\Validation\ValidationException::class);

    expect(WelfareBeneficiary::where('deceased_id', $ineligible->id)->exists())->toBeFalse();
});

// ─── 6. Concurrent / duplicate nomination prevented ─────────────────────────

test('6. duplicate nomination is prevented by canonical service and unique constraint', function () {
    $household = makeHousehold('80000000006', 'REG-DU01', $this->zoneA->id);
    $service = app(WelfareNominationService::class);

    $service->nominateSingle($this->package->id, $household->id, $this->admin);

    expect(fn () => $service->nominateSingle($this->package->id, $household->id, $this->admin))
        ->toThrow(\Illuminate\Validation\ValidationException::class);

    expect(WelfareBeneficiary::where('welfare_package_id', $this->package->id)->where('deceased_id', $household->id)->count())->toBe(1);
});

// ─── 7-9. Composition / delete semantics ─────────────────────────────────────

test('7. open package with zero nominations is composition editable', function () {
    $open = WelfarePackage::create([
        'name' => 'Open No Noms',
        'start_date' => now()->subDays(2),
        'end_date' => now()->addDays(10),
        'status' => WelfarePackageStatus::OPEN,
        'created_by' => $this->admin->id,
    ]);

    expect($open->isCompositionEditable())->toBeTrue();
    expect($this->admin->can('update', $open))->toBeTrue();
});

test('8. open package with nominations cannot modify composition', function () {
    $household = makeHousehold('80000000007', 'REG-OP01', $this->zoneA->id);

    WelfareBeneficiary::create([
        'welfare_package_id' => $this->package->id,
        'deceased_id' => $household->id,
        'suggested_by' => $this->admin->id,
        'status' => BeneficiaryStatus::PENDING,
        'collection_status' => CollectionStatus::NOT_COLLECTED,
    ]);

    expect($this->package->fresh()->isCompositionEditable())->toBeFalse();
    expect($this->admin->can('update', $this->package->fresh()))->toBeFalse();

    expect(fn () => app(\App\Services\WelfarePackageService::class)->syncItems($this->package->fresh(), [
        ['item_id' => $this->item->id, 'quantity_per_family' => 2],
    ]))->toThrow(RuntimeException::class);
});

test('9. draft package with nominations cannot be deleted', function () {
    $draft = WelfarePackage::create([
        'name' => 'Draft With Noms',
        'start_date' => now()->subDays(2),
        'end_date' => now()->addDays(10),
        'status' => WelfarePackageStatus::DRAFT,
        'created_by' => $this->admin->id,
    ]);

    $household = makeHousehold('80000000008', 'REG-DR01', $this->zoneA->id);

    WelfareBeneficiary::create([
        'welfare_package_id' => $draft->id,
        'deceased_id' => $household->id,
        'suggested_by' => $this->admin->id,
        'status' => BeneficiaryStatus::PENDING,
        'collection_status' => CollectionStatus::NOT_COLLECTED,
    ]);

    expect($this->admin->can('delete', $draft->fresh()))->toBeFalse();
});

// ─── 10. Reopening item-less package rejected ────────────────────────────────

test('10. reopening item-less package is rejected', function () {
    $closedNoItems = WelfarePackage::create([
        'name' => 'Closed No Items',
        'start_date' => now()->subDays(2),
        'end_date' => now()->addDays(10),
        'status' => WelfarePackageStatus::CLOSED,
        'created_by' => $this->admin->id,
    ]);

    expect(fn () => app(WelfarePackageLifecycleService::class)->reopenPackage($closedNoItems))
        ->toThrow(RuntimeException::class);
});

// ─── 11-13. Collection authorization + eligibility revalidation ──────────────

test('11. coordinator cannot collect welfare', function () {
    $household = makeHousehold('80000000009', 'REG-CC01', $this->zoneA->id);

    $beneficiary = WelfareBeneficiary::create([
        'welfare_package_id' => $this->package->id,
        'deceased_id' => $household->id,
        'suggested_by' => $this->coordinator->id,
        'status' => BeneficiaryStatus::APPROVED,
        'approved_by' => $this->admin->id,
        'collection_status' => CollectionStatus::NOT_COLLECTED,
    ]);

    expect(fn () => app(BeneficiaryService::class)->collectPackage($beneficiary, 'notes', $this->coordinator->id))
        ->toThrow(RuntimeException::class);
});

test('12. admin can collect eligible approved welfare', function () {
    addStock($this->item->id, 10);

    $household = makeHousehold('80000000010', 'REG-AC01', $this->zoneA->id);

    $beneficiary = WelfareBeneficiary::create([
        'welfare_package_id' => $this->package->id,
        'deceased_id' => $household->id,
        'suggested_by' => $this->admin->id,
        'status' => BeneficiaryStatus::APPROVED,
        'approved_by' => $this->admin->id,
        'collection_status' => CollectionStatus::NOT_COLLECTED,
    ]);

    $collected = app(BeneficiaryService::class)->collectPackage($beneficiary, 'Delivered', $this->admin->id);

    expect($collected->isCollected())->toBeTrue();
});

test('13. collection revalidates eligibility - widow remarriage blocks collection', function () {
    addStock($this->item->id, 10);

    $household = makeHousehold('80000000011', 'REG-EL01', $this->zoneA->id);
    $widow = $household->widows->first();

    $beneficiary = WelfareBeneficiary::create([
        'welfare_package_id' => $this->package->id,
        'deceased_id' => $household->id,
        'suggested_by' => $this->admin->id,
        'status' => BeneficiaryStatus::APPROVED,
        'approved_by' => $this->admin->id,
        'collection_status' => CollectionStatus::NOT_COLLECTED,
    ]);

    // Widow remarries after approval -> household becomes ineligible
    $widow->markAsMarried(marriedAt: now()->subDay());

    expect(fn () => app(BeneficiaryService::class)->collectPackage($beneficiary, 'Delivered', $this->admin->id))
        ->toThrow(RuntimeException::class);

    expect($beneficiary->fresh()->isCollected())->toBeFalse();
});

// ─── 14-15. Orphan eligibility changes block collection ─────────────────────

test('14. orphan aging out before collection blocks collection', function () {
    addStock($this->item->id, 10);

    $deceased = Deceased::create([
        'first_name' => 'Orphan',
        'last_name' => 'Household',
        'nin' => '80000000012',
        'reg_no' => 'REG-OR01',
        'guardian_name' => 'Guardian',
        'guardian_phone' => '08012345678',
        'date_registered' => now()->subMonths(1),
        'date_of_death' => now()->subMonths(2),
        'zone_id' => $this->zoneA->id,
        'vulnerability_status' => VulnerabilityStatus::B,
    ]);

    $orphan = Orphan::create([
        'first_name' => 'Orphan',
        'last_name' => 'Child',
        'reg_no' => 'ORP-OR01',
        'child_sequence' => 1,
        'gender' => Gender::MALE,
        'birth_date' => now()->subYears(17),
        'deceased_id' => $deceased->id,
        'status' => OrphanStatus::ACTIVE,
        'is_eligible' => true,
        'is_married' => false,
    ]);

    $beneficiary = WelfareBeneficiary::create([
        'welfare_package_id' => $this->package->id,
        'deceased_id' => $deceased->id,
        'suggested_by' => $this->admin->id,
        'status' => BeneficiaryStatus::APPROVED,
        'approved_by' => $this->admin->id,
        'collection_status' => CollectionStatus::NOT_COLLECTED,
    ]);

    // Orphan turns 18 -> household loses eligibility
    $orphan->update(['birth_date' => now()->subYears(18)->subDay()]);

    expect(fn () => app(BeneficiaryService::class)->collectPackage($beneficiary, 'Delivered', $this->admin->id))
        ->toThrow(RuntimeException::class);

    expect($beneficiary->fresh()->isCollected())->toBeFalse();
});

test('15. orphan becoming ineligible (archived) before collection blocks collection', function () {
    addStock($this->item->id, 10);

    $deceased = Deceased::create([
        'first_name' => 'Orphan',
        'last_name' => 'Archived',
        'nin' => '80000000013',
        'reg_no' => 'REG-AR01',
        'guardian_name' => 'Guardian',
        'guardian_phone' => '08012345678',
        'date_registered' => now()->subMonths(1),
        'date_of_death' => now()->subMonths(2),
        'zone_id' => $this->zoneA->id,
        'vulnerability_status' => VulnerabilityStatus::B,
    ]);

    Orphan::create([
        'first_name' => 'Orphan',
        'last_name' => 'Child',
        'reg_no' => 'ORP-AR01',
        'child_sequence' => 1,
        'gender' => Gender::FEMALE,
        'birth_date' => now()->subYears(10),
        'deceased_id' => $deceased->id,
        'status' => OrphanStatus::ACTIVE,
        'is_eligible' => true,
        'is_married' => false,
    ]);

    $beneficiary = WelfareBeneficiary::create([
        'welfare_package_id' => $this->package->id,
        'deceased_id' => $deceased->id,
        'suggested_by' => $this->admin->id,
        'status' => BeneficiaryStatus::APPROVED,
        'approved_by' => $this->admin->id,
        'collection_status' => CollectionStatus::NOT_COLLECTED,
    ]);

    // Archive the orphan
    $deceased->orphans()->first()->archiveForIneligibility('MARRIAGE');

    expect(fn () => app(BeneficiaryService::class)->collectPackage($beneficiary, 'Delivered', $this->admin->id))
        ->toThrow(RuntimeException::class);

    expect($beneficiary->fresh()->isCollected())->toBeFalse();
});

// ─── 16-18. Stock posting semantics ─────────────────────────────────────────

test('16. single collection posts stock movement exactly once', function () {
    addStock($this->item->id, 10);

    $household = makeHousehold('80000000014', 'REG-SP01', $this->zoneA->id);

    $beneficiary = WelfareBeneficiary::create([
        'welfare_package_id' => $this->package->id,
        'deceased_id' => $household->id,
        'suggested_by' => $this->admin->id,
        'status' => BeneficiaryStatus::APPROVED,
        'approved_by' => $this->admin->id,
        'collection_status' => CollectionStatus::NOT_COLLECTED,
    ]);

    app(BeneficiaryService::class)->collectPackage($beneficiary, 'notes', $this->admin->id);

    $movements = StockMovement::where('movement_type', StockMovementType::WELFARE_ISSUE)
        ->where('reference_type', WelfareBeneficiary::class)
        ->where('reference_id', $beneficiary->id)
        ->get();

    expect($movements)->toHaveCount(1)
        ->and($movements->first()->quantity)->toBe(-1);
});

test('17. repeated collection cannot double-post stock', function () {
    addStock($this->item->id, 10);

    $household = makeHousehold('80000000015', 'REG-RP01', $this->zoneA->id);

    $beneficiary = WelfareBeneficiary::create([
        'welfare_package_id' => $this->package->id,
        'deceased_id' => $household->id,
        'suggested_by' => $this->admin->id,
        'status' => BeneficiaryStatus::APPROVED,
        'approved_by' => $this->admin->id,
        'collection_status' => CollectionStatus::NOT_COLLECTED,
    ]);

    $service = app(BeneficiaryService::class);
    $service->collectPackage($beneficiary, 'first', $this->admin->id);

    expect(fn () => $service->collectPackage($beneficiary, 'second', $this->admin->id))
        ->toThrow(RuntimeException::class);

    $movements = StockMovement::where('movement_type', StockMovementType::WELFARE_ISSUE)
        ->where('reference_type', WelfareBeneficiary::class)
        ->where('reference_id', $beneficiary->id)
        ->count();

    expect($movements)->toBe(1);
});

test('18. bulk collection posts the same stock ledger effects as single collection', function () {
    addStock($this->item->id, 10);

    $h1 = makeHousehold('80000000016', 'REG-BK01', $this->zoneA->id);
    $h2 = makeHousehold('80000000017', 'REG-BK02', $this->zoneA->id);

    $b1 = WelfareBeneficiary::create([
        'welfare_package_id' => $this->package->id,
        'deceased_id' => $h1->id,
        'suggested_by' => $this->admin->id,
        'status' => BeneficiaryStatus::APPROVED,
        'approved_by' => $this->admin->id,
        'collection_status' => CollectionStatus::NOT_COLLECTED,
    ]);

    $b2 = WelfareBeneficiary::create([
        'welfare_package_id' => $this->package->id,
        'deceased_id' => $h2->id,
        'suggested_by' => $this->admin->id,
        'status' => BeneficiaryStatus::APPROVED,
        'approved_by' => $this->admin->id,
        'collection_status' => CollectionStatus::NOT_COLLECTED,
    ]);

    $collected = app(BeneficiaryService::class)->bulkCollect([$b1->id, $b2->id], 'bulk', $this->admin->id);

    expect($collected)->toBe(2);

    $movementCount = StockMovement::where('movement_type', StockMovementType::WELFARE_ISSUE)
        ->where('reference_type', WelfareBeneficiary::class)
        ->whereIn('reference_id', [$b1->id, $b2->id])
        ->count();

    expect($movementCount)->toBe(2);

    $onHand = StockMovement::where('item_id', $this->item->id)->sum('quantity');
    expect($onHand)->toBe(8);
});

// ─── 19-20. Stock sufficiency ────────────────────────────────────────────────

test('19. insufficient stock blocks collection', function () {
    $household = makeHousehold('80000000018', 'REG-IS01', $this->zoneA->id);

    $beneficiary = WelfareBeneficiary::create([
        'welfare_package_id' => $this->package->id,
        'deceased_id' => $household->id,
        'suggested_by' => $this->admin->id,
        'status' => BeneficiaryStatus::APPROVED,
        'approved_by' => $this->admin->id,
        'collection_status' => CollectionStatus::NOT_COLLECTED,
    ]);

    expect(fn () => app(BeneficiaryService::class)->collectPackage($beneficiary, 'notes', $this->admin->id))
        ->toThrow(RuntimeException::class);

    expect($beneficiary->fresh()->isCollected())->toBeFalse();
});

test('20. failed collection leaves beneficiary and stock ledger consistent', function () {
    addStock($this->item->id, 10);

    $household = makeHousehold('80000000019', 'REG-FC01', $this->zoneA->id);

    $beneficiary = WelfareBeneficiary::create([
        'welfare_package_id' => $this->package->id,
        'deceased_id' => $household->id,
        'suggested_by' => $this->admin->id,
        'status' => BeneficiaryStatus::APPROVED,
        'approved_by' => $this->admin->id,
        'collection_status' => CollectionStatus::NOT_COLLECTED,
    ]);

    // Remove stock so collection fails
    StockMovement::where('item_id', $this->item->id)->delete();

    try {
        app(BeneficiaryService::class)->collectPackage($beneficiary, 'notes', $this->admin->id);
    } catch (RuntimeException $e) {
        // expected
    }

    expect($beneficiary->fresh()->isCollected())->toBeFalse();
    expect(StockMovement::where('reference_type', WelfareBeneficiary::class)
        ->where('reference_id', $beneficiary->id)
        ->count())->toBe(0);
});

// ─── 21. Event dispatch ──────────────────────────────────────────────────────

test('21. BeneficiaryCollected event is emitted exactly once on successful collection', function () {
    addStock($this->item->id, 10);

    Event::fake([\App\Events\BeneficiaryCollected::class]);

    $household = makeHousehold('80000000020', 'REG-EV01', $this->zoneA->id);

    $beneficiary = WelfareBeneficiary::create([
        'welfare_package_id' => $this->package->id,
        'deceased_id' => $household->id,
        'suggested_by' => $this->admin->id,
        'status' => BeneficiaryStatus::APPROVED,
        'approved_by' => $this->admin->id,
        'collection_status' => CollectionStatus::NOT_COLLECTED,
    ]);

    app(BeneficiaryService::class)->collectPackage($beneficiary, 'notes', $this->admin->id);

    Event::assertDispatched(\App\Events\BeneficiaryCollected::class, 1);
});

// ─── 22-23. Widget corrections ───────────────────────────────────────────────

test('22. welfare intervention widget distinguishes approved from collected', function () {
    $household = makeHousehold('80000000021', 'REG-WG01', $this->zoneA->id);

    $approvedNotCollected = WelfareBeneficiary::create([
        'welfare_package_id' => $this->package->id,
        'deceased_id' => $household->id,
        'suggested_by' => $this->admin->id,
        'status' => BeneficiaryStatus::APPROVED,
        'collection_status' => CollectionStatus::NOT_COLLECTED,
    ]);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($this->admin);

    Livewire::test(\App\Filament\Widgets\WelfareInterventionWidget::class)
        ->assertCanSeeTableRecords([$approvedNotCollected]);
});

test('23. pending items widget does not leak cross-zone welfare counts', function () {
    $ownHousehold = makeHousehold('80000000022', 'REG-PW01', $this->zoneA->id);
    $crossHousehold = makeHousehold('80000000023', 'REG-PW02', $this->zoneB->id);

    WelfareBeneficiary::create([
        'welfare_package_id' => $this->package->id,
        'deceased_id' => $ownHousehold->id,
        'suggested_by' => $this->coordinator->id,
        'status' => BeneficiaryStatus::PENDING,
        'collection_status' => CollectionStatus::NOT_COLLECTED,
    ]);

    WelfareBeneficiary::create([
        'welfare_package_id' => $this->package->id,
        'deceased_id' => $crossHousehold->id,
        'suggested_by' => $this->admin->id,
        'status' => BeneficiaryStatus::PENDING,
        'collection_status' => CollectionStatus::NOT_COLLECTED,
    ]);

    Filament::setCurrentPanel(Filament::getPanel('coordinator'));
    $this->actingAs($this->coordinator);

    $widget = Livewire::test(\App\Filament\Coordinator\Widgets\PendingItemsWidget::class);

    $viewData = invokeWidgetGetViewData($widget->instance());

    expect($viewData['counts']['welfare'])->toBe(1);
});

function invokeWidgetGetViewData(object $widget): array
{
    $method = new \ReflectionMethod($widget, 'getViewData');
    $method->setAccessible(true);

    return $method->invoke($widget);
}

// ─── 24. Legacy drop migration down() schema fidelity ────────────────────────

test('24. legacy drop migration down recreates the original welfare schema', function () {
    $migration = require database_path('migrations/2026_09_01_000001_drop_legacy_welfare_tables.php');

    // Simulate a rollback by running down() from an empty state.
    // The original up() migrations created `welfare` (uuid PK) and
    // `deceased_welfare` (uuid PK, FKs to welfare + deceased).
    $migration->down();

    expect(\Illuminate\Support\Facades\Schema::hasTable('welfare'))->toBeTrue();
    expect(\Illuminate\Support\Facades\Schema::hasTable('deceased_welfare'))->toBeTrue();

    $welfareColumns = \Illuminate\Support\Facades\Schema::getColumnListing('welfare');
    expect($welfareColumns)->toContain('id', 'name', 'date', 'collection_status', 'welfare_status');

    $welfareIdType = \Illuminate\Support\Facades\Schema::getColumnType('welfare', 'id');
    expect($welfareIdType)->toBe('varchar'); // uuid columns introspect as varchar in SQLite

    $dwColumns = \Illuminate\Support\Facades\Schema::getColumnListing('deceased_welfare');
    expect($dwColumns)->toContain('id', 'welfare_id', 'deceased_id', 'collection_status');
});
