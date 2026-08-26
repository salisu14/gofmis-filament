<?php

namespace Database\Seeders;

use App\Enums\BeneficiaryStatus;
use App\Enums\CollectionStatus;
use App\Enums\StockMovementType;
use App\Enums\WelfarePackageStatus;
use App\Models\Deceased;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\WelfareBeneficiary;
use App\Models\WelfarePackage;
use App\Models\WelfarePackageItem;
use App\Services\BeneficiaryService;
use App\Services\Welfare\WelfareNominationService;
use App\Services\Welfare\WelfarePackageLifecycleService;
use Illuminate\Database\Seeder;

/**
 * Deterministic UAT Welfare packages and nomination/collection history.
 *
 * Uses the canonical services (WelfarePackageLifecycleService,
 * WelfareNominationService, BeneficiaryService) for all state transitions.
 * Direct model writes are used ONLY where historical-state setup (e.g.
 * approving/collecting with back-dated timestamps through services would
 * mutate "now" semantics) makes service calls impractical; in those cases the
 * domain invariants (status ordering, stock ledger effects, eligibility) are
 * still respected explicitly.
 *
 * Idempotency: packages are keyed by a deterministic name; beneficiaries are
 * keyed by (package, deceased). Re-running never duplicates.
 */
class UatWelfareSeeder extends Seeder
{
    protected ?User $admin = null;

    protected ?WelfarePackageLifecycleService $lifecycle = null;

    protected ?WelfareNominationService $nomination = null;

    protected ?BeneficiaryService $beneficiaryService = null;

    public function run(): void
    {
        $this->admin = User::where('email', 'admin@admin.com')->first()
            ?? User::where('email', 'sadmin@admin.com')->first();

        if (! $this->admin) {
            throw new \RuntimeException('UatWelfareSeeder requires an admin user. Run UatHouseholdSeeder first.');
        }

        $this->lifecycle = app(WelfarePackageLifecycleService::class);
        $this->nomination = app(WelfareNominationService::class);
        $this->beneficiaryService = app(BeneficiaryService::class);

        // A. DRAFT package with items, zero nominations
        $this->packageA();

        // B. OPEN package with zero nominations
        $this->packageB();

        // C. OPEN package with pending nominations
        $this->packageC();

        // D. OPEN package with approved/not-collected nominations
        $this->packageD();

        // E. OPEN package with rejected nomination history
        $this->packageE();

        // F. CLOSED package with collected nominations (with ledger effects)
        $this->packageF();

        // G. REOPENED (CLOSED -> OPEN) package with prior nominations
        $this->packageG();

        // H. OPEN package whose stock capacity is deliberately insufficient
        $this->packageH();
    }

    protected function package(string $name, string $startDate, string $endDate): WelfarePackage
    {
        return WelfarePackage::firstOrCreate(
            ['name' => $name],
            [
                'description' => 'UAT deterministic package: '.$name,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => WelfarePackageStatus::DRAFT,
                'created_by' => $this->admin->id,
            ]
        );
    }

    protected function addItem(WelfarePackage $package, string $itemName, int $quantityPerFamily): void
    {
        $item = Item::where('name', $itemName)->first();
        if (! $item) {
            throw new \RuntimeException("Item [{$itemName}] not found. Run UatInventorySeeder first.");
        }

        WelfarePackageItem::firstOrCreate(
            [
                'welfare_package_id' => $package->id,
                'item_id' => $item->id,
                'category_id' => $item->category_id,
            ],
            [
                'quantity_per_family' => $quantityPerFamily,
                'notes' => 'UAT package item',
            ]
        );
    }

    protected function household(string $regNo): Deceased
    {
        $deceased = Deceased::where('reg_no', $regNo)->first();
        if (! $deceased) {
            throw new \RuntimeException("Household [{$regNo}] not found. Run UatHouseholdSeeder first.");
        }

        return $deceased;
    }

    protected function createBeneficiary(WelfarePackage $package, Deceased $deceased, BeneficiaryStatus $status, CollectionStatus $collectionStatus): WelfareBeneficiary
    {
        return WelfareBeneficiary::firstOrCreate(
            [
                'welfare_package_id' => $package->id,
                'deceased_id' => $deceased->id,
            ],
            [
                'suggested_by' => $this->admin->id,
                'status' => $status,
                'collection_status' => $collectionStatus,
            ]
        );
    }

    /**
     * A. DRAFT package with items and zero nominations.
     */
    protected function packageA(): void
    {
        $pkg = $this->package('UAT Welfare Draft Package', now()->addDays(30)->toDateString(), now()->addDays(60)->toDateString());
        $this->addItem($pkg, 'Rice (50kg Bag)', 1);
        $this->addItem($pkg, 'Cooking Oil (5L)', 2);
    }

    /**
     * B. OPEN package with zero nominations.
     */
    protected function packageB(): void
    {
        $pkg = $this->package('UAT Welfare Open No Nominations', now()->subDays(5)->toDateString(), now()->addDays(25)->toDateString());
        $this->addItem($pkg, 'Maize (50kg Bag)', 1);
        $this->addItem($pkg, 'Beans (25kg Bag)', 1);

        if ($pkg->isDraft()) {
            $this->lifecycle->openPackage($pkg);
        }
    }

    /**
     * C. OPEN package with pending nominations.
     */
    protected function packageC(): void
    {
        $pkg = $this->package('UAT Welfare Open Pending', now()->subDays(3)->toDateString(), now()->addDays(20)->toDateString());
        $this->addItem($pkg, 'School Bag', 1);
        $this->addItem($pkg, 'Exercise Books (Pack of 5)', 2);

        if ($pkg->isDraft()) {
            $this->lifecycle->openPackage($pkg);
        }

        if ($pkg->beneficiaries()->count() === 0) {
            $this->nomination->nominate(
                $pkg->id,
                [
                    $this->household('UAT-DEC-001')->id,
                    $this->household('UAT-DEC-003')->id,
                ],
                $this->admin
            );
        }
    }

    /**
     * D. OPEN package with approved/not-collected nominations.
     */
    protected function packageD(): void
    {
        $pkg = $this->package('UAT Welfare Open Approved', now()->subDays(10)->toDateString(), now()->addDays(15)->toDateString());
        $this->addItem($pkg, 'Rice (50kg Bag)', 1);
        $this->addItem($pkg, 'Cooking Oil (5L)', 1);

        if ($pkg->isDraft()) {
            $this->lifecycle->openPackage($pkg);
        }

        $households = [
            $this->household('UAT-DEC-002'),
            $this->household('UAT-DEC-005'),
            $this->household('UAT-DEC-009'),
        ];

        foreach ($households as $deceased) {
            $existing = $pkg->beneficiaries()->where('deceased_id', $deceased->id)->first();
            if ($existing) {
                continue;
            }

            $beneficiary = $this->nomination->nominateSingle($pkg->id, $deceased->id, $this->admin);
            $this->beneficiaryService->approveBeneficiary($beneficiary, $this->admin->id);
        }
    }

    /**
     * E. OPEN package with rejected nomination history.
     */
    protected function packageE(): void
    {
        $pkg = $this->package('UAT Welfare Open Rejected History', now()->subDays(8)->toDateString(), now()->addDays(12)->toDateString());
        $this->addItem($pkg, 'School Uniform', 1);

        if ($pkg->isDraft()) {
            $this->lifecycle->openPackage($pkg);
        }

        // Rejected nomination: an ineligible household (remarried widow).
        $ineligible = $this->household('UAT-DEC-006');
        $existing = $pkg->beneficiaries()->where('deceased_id', $ineligible->id)->first();
        if (! $existing) {
            $beneficiary = $this->createBeneficiary($pkg, $ineligible, BeneficiaryStatus::PENDING, CollectionStatus::NOT_COLLECTED);
            $this->beneficiaryService->rejectBeneficiary($beneficiary, 'Household does not meet welfare eligibility criteria.', $this->admin->id);
        }

        // Also a valid pending nomination so the package is not empty.
        if ($pkg->beneficiaries()->count() === 0) {
            $this->nomination->nominate($pkg->id, [$this->household('UAT-DEC-010')->id], $this->admin);
        }
    }

    /**
     * F. CLOSED package with collected nominations and ledger effects.
     *
     * Historical-state setup: this package's window has already ended, so the
     * canonical nomination/collection services (which enforce the live date
     * window) cannot be used. Direct insertion preserves the domain invariants
     * manually: APPROVED -> COLLECTED ordering, collected_by/collected_at, and
     * matching StockMovement WELFARE_ISSUE ledger rows (idempotent by
     * firstOrCreate on the deterministic reference key).
     */
    protected function packageF(): void
    {
        $pkg = $this->package('UAT Welfare Closed Collected', now()->subDays(40)->toDateString(), now()->subDays(10)->toDateString());
        $this->addItem($pkg, 'Rice (50kg Bag)', 1);
        $this->addItem($pkg, 'Beans (25kg Bag)', 1);

        if ($pkg->isDraft()) {
            $this->lifecycle->openPackage($pkg);
        }

        $households = [
            $this->household('UAT-DEC-001'),
            $this->household('UAT-DEC-002'),
            $this->household('UAT-DEC-004'),
            $this->household('UAT-DEC-011'),
        ];

        foreach ($households as $index => $deceased) {
            $beneficiary = $this->createBeneficiary($pkg, $deceased, BeneficiaryStatus::APPROVED, CollectionStatus::COLLECTED);
            $collectedAt = now()->subDays(20 - $index * 2);

            $beneficiary->update([
                'approved_by' => $this->admin->id,
                'collected_at' => $collectedAt,
                'collected_by' => $this->admin->id,
                'collection_notes' => 'UAT historical distribution',
            ]);

            foreach ($pkg->items as $pkgItem) {
                StockMovement::firstOrCreate(
                    [
                        'item_id' => $pkgItem->item_id,
                        'movement_type' => StockMovementType::WELFARE_ISSUE,
                        'reference_type' => WelfareBeneficiary::class,
                        'reference_id' => $beneficiary->id,
                    ],
                    [
                        'quantity' => -1 * (int) $pkgItem->quantity_per_family,
                        'occurred_at' => $collectedAt,
                        'created_by' => $this->admin->id,
                        'notes' => "Welfare Package Collection ({$pkg->name})",
                    ]
                );
            }
        }

        if (! $pkg->isClosed()) {
            $this->lifecycle->closePackage($pkg);
        }
    }

    /**
     * G. REOPENED (CLOSED -> OPEN) package with prior nominations,
     *    demonstrating composition remains immutable.
     */
    protected function packageG(): void
    {
        $pkg = $this->package('UAT Welfare Reopened Prior Nominations', now()->subDays(35)->toDateString(), now()->addDays(10)->toDateString());
        $this->addItem($pkg, 'School Bag', 1);

        // Build the history: open -> nominate -> close -> reopen.
        if ($pkg->isDraft()) {
            $this->lifecycle->openPackage($pkg);

            $this->nomination->nominate(
                $pkg->id,
                [
                    $this->household('UAT-DEC-007')->id,
                    $this->household('UAT-DEC-013')->id,
                ],
                $this->admin
            );

            $this->lifecycle->closePackage($pkg);
            $this->lifecycle->reopenPackage($pkg);
        }
    }

    /**
     * H. OPEN package whose stock capacity is deliberately insufficient
     *    (item with low opening stock relative to a large quantity_per_family).
     */
    protected function packageH(): void
    {
        $pkg = $this->package('UAT Welfare Insufficient Stock', now()->subDays(2)->toDateString(), now()->addDays(20)->toDateString());

        // Kerosene Stove has opening stock 8; require 5 per family, so a
        // package that needs to serve many households is capacity-limited.
        $this->addItem($pkg, 'Kerosene Stove', 5);

        if ($pkg->isDraft()) {
            $this->lifecycle->openPackage($pkg);
        }
    }
}
