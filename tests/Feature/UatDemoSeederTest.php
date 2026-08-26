<?php

namespace Tests\Feature;

use App\Enums\BeneficiaryStatus;
use App\Enums\CollectionStatus;
use App\Enums\Gender;
use App\Enums\OrphanStatus;
use App\Enums\StockMovementType;
use App\Enums\WelfarePackageStatus;
use App\Enums\WidowLoanStatus;
use App\Models\Deceased;
use App\Models\Item;
use App\Models\Orphan;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\WelfareBeneficiary;
use App\Models\WelfarePackage;
use App\Models\Widow;
use App\Models\WidowLoan;
use App\Models\WidowLoanRepayment;
use App\Models\Zone;
use Database\Seeders\UatDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Baseline reference seeders that UAT data depends on (zones, roles,
    // permissions, orphan classes, intervention types). UatDemoSeeder itself
    // seeds the actors (users) first, so orphan classes are created there.
    $this->seed(\Database\Seeders\ZonesTableSeeder::class);
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->seed(\Database\Seeders\InterventionTypeSeeder::class);
});

function seedUatOnce(): void
{
    Artisan::call('db:seed', ['--class' => UatDemoSeeder::class]);
}

test('UatDemoSeeder refuses to run in production environment', function () {
    $seeder = new UatDemoSeeder();

    // Force the application environment to production and invoke the guard.
    $app = app();
    $originalEnv = $app->environment();
    $app->detectEnvironment(fn () => 'production');

    try {
        expect(fn () => $seeder->run())->toThrow(\RuntimeException::class);
    } finally {
        $app->detectEnvironment(fn () => $originalEnv);
    }
});

test('UatDemoSeeder creates deterministic actors and zone assignments', function () {
    seedUatOnce();

    expect(User::where('email', 'sadmin@admin.com')->exists())->toBeTrue();
    expect(User::where('email', 'admin@admin.com')->exists())->toBeTrue();
    expect(User::where('email', 'coordinator.a1@admin.com')->exists())->toBeTrue();
    expect(User::where('email', 'coordinator.a2@admin.com')->exists())->toBeTrue();
    expect(User::where('email', 'coordinator.b1@admin.com')->exists())->toBeTrue();

    $zoneA1 = Zone::where('name', 'A1')->first();
    $zoneA2 = Zone::where('name', 'A2')->first();
    $zoneB1 = Zone::where('name', 'B1')->first();

    expect($zoneA1->coordinator_id)->not->toBeNull();
    expect($zoneA2->coordinator_id)->not->toBeNull();
    expect($zoneB1->coordinator_id)->not->toBeNull();

    // Coordinators manage distinct zones
    expect($zoneA1->coordinator_id)->not->toBe($zoneA2->coordinator_id);
    expect($zoneA1->coordinator_id)->not->toBe($zoneB1->coordinator_id);
});

test('UatDemoSeeder is idempotent - second run does not duplicate entities', function () {
    seedUatOnce();

    $householdCount = Deceased::count();
    $widowCount = Widow::count();
    $orphanCount = Orphan::count();
    $itemCount = Item::count();
    $openingMovements = StockMovement::where('movement_type', StockMovementType::OPENING_BALANCE)->count();
    $packageCount = WelfarePackage::count();
    $nominationCount = WelfareBeneficiary::count();
    $loanCount = WidowLoan::count();
    $repaymentCount = WidowLoanRepayment::count();

    seedUatOnce();

    expect(Deceased::count())->toBe($householdCount);
    expect(Widow::count())->toBe($widowCount);
    expect(Orphan::count())->toBe($orphanCount);
    expect(Item::count())->toBe($itemCount);
    expect(StockMovement::where('movement_type', StockMovementType::OPENING_BALANCE)->count())->toBe($openingMovements);
    expect(WelfarePackage::count())->toBe($packageCount);
    expect(WelfareBeneficiary::count())->toBe($nominationCount);
    expect(WidowLoan::count())->toBe($loanCount);
    expect(WidowLoanRepayment::count())->toBe($repaymentCount);
});

test('UatDemoSeeder creates coherent household relationships', function () {
    seedUatOnce();

    expect(Deceased::count())->toBe(20);
    expect(Widow::count())->toBeGreaterThanOrEqual(10);
    expect(Orphan::count())->toBeGreaterThanOrEqual(20);

    // Every widow/orphan belongs to an existing household
    expect(Widow::whereDoesntHave('deceased')->count())->toBe(0);
    expect(Orphan::whereDoesntHave('deceased')->count())->toBe(0);

    // number_of_orphans_left / number_of_widows_left are consistent
    foreach (Deceased::withCount(['orphans', 'widows'])->get() as $deceased) {
        expect((int) $deceased->number_of_orphans_left)->toBe($deceased->orphans_count);
        expect((int) $deceased->number_of_widows_left)->toBe($deceased->widows_count);
    }
});

test('UatDemoSeeder represents eligible and ineligible scenarios', function () {
    seedUatOnce();

    // Scenario 1: eligible widow + eligible orphan
    $h1 = Deceased::where('reg_no', 'UAT-DEC-001')->first();
    expect($h1->widows->contains(fn ($w) => $w->isOperationalBeneficiary() && $w->is_eligible))->toBeTrue();
    expect($h1->orphans->contains(fn ($o) => $o->isOperationalBeneficiary() && $o->is_eligible))->toBeTrue();

    // Scenario 6: remarried widow is ineligible
    $h6 = Deceased::where('reg_no', 'UAT-DEC-006')->first();
    $h6widow = $h6->widows->first();
    expect($h6widow->is_married)->toBeTrue();
    expect($h6widow->isOperationalBeneficiary())->toBeFalse();

    // Scenario 7: aged-out orphan is ineligible
    $h7 = Deceased::where('reg_no', 'UAT-DEC-007')->first();
    $h7orphan = $h7->orphans->first();
    expect($h7orphan->isOverAged())->toBeTrue();
    expect($h7orphan->isOperationalBeneficiary())->toBeFalse();

    // Scenario 8: no eligible welfare beneficiary
    $h8 = Deceased::where('reg_no', 'UAT-DEC-008')->first();
    expect($h8->widows->contains(fn ($w) => $w->isOperationalBeneficiary() && $w->is_eligible))->toBeFalse();
    expect($h8->orphans->contains(fn ($o) => $o->isOperationalBeneficiary() && $o->is_eligible))->toBeFalse();

    // At least one household with an eligible widow only
    expect(Deceased::whereHas('widows', fn ($q) => $q->where('is_eligible', true)->where('is_married', false))
        ->whereDoesntHave('orphans', fn ($q) => $q->where('is_eligible', true)->where('status', OrphanStatus::ACTIVE))
        ->count())->toBeGreaterThan(0);
});

test('UatDemoSeeder stock movement totals reconcile with item stock', function () {
    seedUatOnce();

    foreach (Item::all() as $item) {
        $ledgerTotal = StockMovement::where('item_id', $item->id)->sum('quantity');
        // No negative opening stock and each item has a deterministic opening balance
        expect(StockMovement::where('item_id', $item->id)->where('movement_type', StockMovementType::OPENING_BALANCE)->sum('quantity'))
            ->toBeGreaterThan(0);

        // Welfare issues reduce the ledger; total ledger must never go below zero
        expect($ledgerTotal)->toBeGreaterThanOrEqual(0);
    }
});

test('UatDemoSeeder welfare nomination and collection state is internally consistent', function () {
    seedUatOnce();

    // Every nomination belongs to an existing package and household
    expect(WelfareBeneficiary::whereDoesntHave('welfarePackage')->count())->toBe(0);
    expect(WelfareBeneficiary::whereDoesntHave('deceased')->count())->toBe(0);

    // Collected beneficiaries must be APPROVED + COLLECTED and have stock ledger effects
    foreach (WelfareBeneficiary::collected()->get() as $beneficiary) {
        expect($beneficiary->status)->toBe(BeneficiaryStatus::APPROVED);
        expect($beneficiary->collection_status)->toBe(CollectionStatus::COLLECTED);

        $movements = StockMovement::where('movement_type', StockMovementType::WELFARE_ISSUE)
            ->where('reference_type', WelfareBeneficiary::class)
            ->where('reference_id', $beneficiary->id)
            ->count();

        expect($movements)->toBeGreaterThan(0);
    }

    // Package G (reopened with prior nominations) must be OPEN but NOT composition-editable
    $pkgG = WelfarePackage::where('name', 'UAT Welfare Reopened Prior Nominations')->first();
    expect($pkgG)->not->toBeNull();
    expect($pkgG->status)->toBe(WelfarePackageStatus::OPEN);
    expect($pkgG->isCompositionEditable())->toBeFalse();
    expect($pkgG->hasNominations())->toBeTrue();

    // Package F must be CLOSED with collected nominations
    $pkgF = WelfarePackage::where('name', 'UAT Welfare Closed Collected')->first();
    expect($pkgF->status)->toBe(WelfarePackageStatus::CLOSED);
    expect($pkgF->beneficiaries()->collected()->count())->toBeGreaterThan(0);
});

test('UatDemoSeeder WRL repayment balances reconcile', function () {
    seedUatOnce();

    foreach (WidowLoan::all() as $loan) {
        $totalRepaid = (float) $loan->repayments()->sum('amount');
        $expectedOutstanding = (float) max(0, round((float) $loan->total_payable - $totalRepaid, 2));

        expect((float) $loan->total_paid)->toBe($totalRepaid);
        expect((float) $loan->outstanding_balance)->toBe($expectedOutstanding);

        // Repayments never exceed the principal
        expect($totalRepaid)->toBeLessThanOrEqual((float) $loan->total_payable + 0.01);
    }

    // Scenario 3: fully repaid loan must be COMPLETED
    $fullyRepaid = WidowLoan::whereHas('widow', fn ($q) => $q->where('reg_no', 'UAT-WID-003'))->first();
    expect($fullyRepaid)->not->toBeNull();
    expect($fullyRepaid->status)->toBe(WidowLoanStatus::COMPLETED);
    expect((float) $fullyRepaid->outstanding_balance)->toBe(0.0);
});

// ─── UAT household identity / name convergence ──────────────────────────────

test('every UAT deceased has a non-empty displayed full name', function () {
    seedUatOnce();

    $blank = Deceased::where('reg_no', 'like', 'UAT-DEC-%')
        ->where(fn ($q) => $q->whereNull('full_name')->orWhere('full_name', ''))
        ->count();

    expect($blank)->toBe(0);

    foreach (Deceased::where('reg_no', 'like', 'UAT-DEC-%')->get() as $deceased) {
        expect(trim((string) $deceased->display_name))->not->toBeEmpty();
    }
});

test('every UAT widow has a non-empty displayed full name', function () {
    seedUatOnce();

    $blank = Widow::where('reg_no', 'like', 'UAT-WID-%')
        ->where(fn ($q) => $q->whereNull('full_name')->orWhere('full_name', ''))
        ->count();

    expect($blank)->toBe(0);

    foreach (Widow::where('reg_no', 'like', 'UAT-WID-%')->get() as $widow) {
        expect(trim((string) $widow->display_name))->not->toBeEmpty();
    }
});

test('every UAT orphan has a non-empty displayed full name', function () {
    seedUatOnce();

    $blank = Orphan::where('reg_no', 'like', 'UAT-ORP-%')
        ->where(fn ($q) => $q->whereNull('full_name')->orWhere('full_name', ''))
        ->count();

    expect($blank)->toBe(0);

    foreach (Orphan::where('reg_no', 'like', 'UAT-ORP-%')->get() as $orphan) {
        expect(trim((string) $orphan->display_name))->not->toBeEmpty();
    }
});

test('representative deterministic UAT names are exactly stable', function () {
    seedUatOnce();

    expect(Deceased::where('reg_no', 'UAT-DEC-001')->first()->full_name)->toBe('Adamu Bello');
    expect(Widow::where('reg_no', 'UAT-WID-001')->first()->full_name)->toBe('Aisha Bello');
    expect(Orphan::where('reg_no', 'UAT-ORP-001')->first()->full_name)->toBe('Musa Bello');

    // Re-running must not change the canonical names.
    seedUatOnce();

    expect(Deceased::where('reg_no', 'UAT-DEC-001')->first()->full_name)->toBe('Adamu Bello');
    expect(Widow::where('reg_no', 'UAT-WID-001')->first()->full_name)->toBe('Aisha Bello');
    expect(Orphan::where('reg_no', 'UAT-ORP-001')->first()->full_name)->toBe('Musa Bello');
});

test('re-running UatDemoSeeder repairs blanked UAT identity fields without duplicating records', function () {
    seedUatOnce();

    $deceasedCount = Deceased::where('reg_no', 'like', 'UAT-DEC-%')->count();
    $widowCount = Widow::where('reg_no', 'like', 'UAT-WID-%')->count();
    $orphanCount = Orphan::where('reg_no', 'like', 'UAT-ORP-%')->count();

    // Deliberately blank the displayed identity column (full_name is the
    // nullable column that drives the Filament name columns; first/last are
    // NOT NULL so we leave them intact).
    Deceased::where('reg_no', 'UAT-DEC-001')->update(['full_name' => '']);
    Widow::where('reg_no', 'UAT-WID-001')->update(['full_name' => '']);
    Orphan::where('reg_no', 'UAT-ORP-001')->update(['full_name' => '']);

    expect(Deceased::where('reg_no', 'UAT-DEC-001')->first()->full_name)->toBe('');
    expect(Widow::where('reg_no', 'UAT-WID-001')->first()->full_name)->toBe('');
    expect(Orphan::where('reg_no', 'UAT-ORP-001')->first()->full_name)->toBe('');

    // Re-run: the seeder must converge the UAT-owned records back to canonical.
    seedUatOnce();

    expect(Deceased::where('reg_no', 'UAT-DEC-001')->first()->full_name)->toBe('Adamu Bello');
    expect(Widow::where('reg_no', 'UAT-WID-001')->first()->full_name)->toBe('Aisha Bello');
    expect(Orphan::where('reg_no', 'UAT-ORP-001')->first()->full_name)->toBe('Musa Bello');

    // Row counts unchanged (no duplicates).
    expect(Deceased::where('reg_no', 'like', 'UAT-DEC-%')->count())->toBe($deceasedCount);
    expect(Widow::where('reg_no', 'like', 'UAT-WID-%')->count())->toBe($widowCount);
    expect(Orphan::where('reg_no', 'like', 'UAT-ORP-%')->count())->toBe($orphanCount);
});

// ─── Legacy placeholder household cleanup ────────────────────────────────────

test('UatDemoSeeder removes zero placeholder deceased names', function () {
    // Create legacy placeholder records exactly like WelfarePackageSeeder did:
    // one referenced (with a welfare beneficiary) and one unreferenced.
    $zone = Zone::first();

    $referenced = Deceased::create([
        'first_name' => 'DeceasedFirst 3',
        'last_name' => 'DeceasedLast 3',
        'nin' => '12345678903',
        'reg_no' => 'DEC-00003',
        'guardian_name' => 'Guardian',
        'guardian_phone' => '08012345678',
        'vulnerability_status' => \App\Enums\VulnerabilityStatus::B,
        'date_registered' => now()->toDateString(),
        'zone_id' => $zone->id,
    ]);

    WelfareBeneficiary::create([
        'welfare_package_id' => \App\Models\WelfarePackage::factory()->create()->id,
        'deceased_id' => $referenced->id,
        'suggested_by' => User::factory()->create()->id,
        'status' => \App\Enums\BeneficiaryStatus::PENDING,
        'collection_status' => \App\Enums\CollectionStatus::NOT_COLLECTED,
    ]);

    $unreferenced = Deceased::create([
        'first_name' => 'DeceasedFirst 1',
        'last_name' => 'DeceasedLast 1',
        'nin' => '12345678901',
        'reg_no' => 'DEC-00001',
        'guardian_name' => 'Guardian',
        'guardian_phone' => '08012345678',
        'vulnerability_status' => \App\Enums\VulnerabilityStatus::B,
        'date_registered' => now()->toDateString(),
        'zone_id' => $zone->id,
    ]);

    seedUatOnce();

    // No placeholder names remain anywhere in the Deceased module.
    expect(Deceased::where('first_name', 'like', 'DeceasedFirst%')->count())->toBe(0);
    expect(Deceased::where('last_name', 'like', 'DeceasedLast%')->count())->toBe(0);

    // Referenced record was renamed (preserving reg_no + welfare link).
    $renamed = Deceased::where('reg_no', 'DEC-00003')->first();
    expect($renamed)->not->toBeNull();
    expect($renamed->first_name)->toBe('Kabiru');
    expect($renamed->last_name)->toBe('Danladi');
    expect($renamed->full_name)->toBe('Kabiru Danladi');
    expect(\App\Models\WelfareBeneficiary::where('deceased_id', $renamed->id)->count())->toBe(1);

    // Unreferenced record was removed.
    expect(Deceased::where('reg_no', 'DEC-00001')->exists())->toBeFalse();

    // Idempotent: a second run changes nothing.
    $totalBefore = Deceased::count();
    seedUatOnce();
    expect(Deceased::count())->toBe($totalBefore);
    expect(Deceased::where('reg_no', 'DEC-00003')->first()->full_name)->toBe('Kabiru Danladi');
});

// ─── Generic baseline inventory convergence ──────────────────────────────────

test('UatDemoSeeder removes generic Item N / Category N baseline fixtures', function () {
    // Reproduce the WelfarePackageSeeder generic baseline fixtures.
    $admin = User::create([
        'name' => 'Fixture Admin',
        'email' => 'fixture-admin@example.com',
        'password' => \Illuminate\Support\Facades\Hash::make('password'),
        'status' => \App\Enums\UserStatus::ACTIVE,
    ]);
    $admin->assignRole('admin');

    $categories = [];
    for ($i = 1; $i <= 5; $i++) {
        $categories[$i] = \App\Models\Category::create([
            'name' => "Category {$i}",
            'description' => "Description for Category {$i}",
            'user_id' => $admin->id,
        ]);
    }

    $items = [];
    for ($i = 1; $i <= 10; $i++) {
        $items[$i] = \App\Models\Item::create([
            'name' => "Item {$i}",
            'description' => "Description for Item {$i}",
            'category_id' => $categories[($i % 5) + 1]->id,
            'user_id' => $admin->id,
        ]);
    }

    // Reference Item 1 via a welfare package item (relationship preservation).
    $package = \App\Models\WelfarePackage::create([
        'name' => 'Baseline Pkg',
        'start_date' => now()->subDays(2)->toDateString(),
        'end_date' => now()->addDays(10)->toDateString(),
        'status' => \App\Enums\WelfarePackageStatus::DRAFT,
        'created_by' => $admin->id,
    ]);
    \App\Models\WelfarePackageItem::create([
        'welfare_package_id' => $package->id,
        'item_id' => $items[1]->id,
        'category_id' => $items[1]->category_id,
        'quantity_per_family' => 1,
    ]);

    seedUatOnce();

    // No generic fixtures remain.
    expect(\App\Models\Item::where('name', 'like', 'Item %')->count())->toBe(0);
    expect(\App\Models\Category::where('name', 'like', 'Category%')->count())->toBe(0);

    // Referenced Item 1 was renamed and its welfare package item link preserved.
    $renamedItem = \App\Models\Item::find($items[1]->id);
    expect($renamedItem)->not->toBeNull();
    expect($renamedItem->name)->toBe('Premium Rice (25kg Bag)');
    expect(\App\Models\WelfarePackageItem::where('item_id', $items[1]->id)->count())->toBe(1);

    // Unreferenced items (e.g. Item 3) were removed.
    expect(\App\Models\Item::find($items[3]->id))->toBeNull();

    // Idempotent: a second run changes nothing.
    $itemCount = \App\Models\Item::count();
    $categoryCount = \App\Models\Category::count();
    seedUatOnce();
    expect(\App\Models\Item::count())->toBe($itemCount);
    expect(\App\Models\Category::count())->toBe($categoryCount);
    expect(\App\Models\Item::where('name', 'like', 'Item %')->count())->toBe(0);
});

test('stock availability reconciliation is internally consistent after UAT seeding', function () {
    seedUatOnce();

    $service = app(\App\Services\Inventory\StockAvailabilityService::class);
    $metrics = $service->getItemStockMetrics();

    $onHandTotal = 0;
    foreach ($metrics as $m) {
        $onHandTotal += $m['on_hand'];
    }

    // Total on-hand from the ledger must equal the sum of the deterministic
    // UAT opening balances (888) minus any welfare issues (8).
    $ledgerTotal = \App\Models\StockMovement::sum('quantity');
    expect($onHandTotal)->toBe((int) $ledgerTotal);

    // No generic fixtures remain in the availability view.
    expect($metrics->contains(fn ($m) => str_starts_with($m['name'], 'Item ')))->toBeFalse();
    expect($metrics->contains(fn ($m) => str_starts_with($m['name'], 'Category')))->toBeFalse();
});
