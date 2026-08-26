<?php

namespace Tests\Feature;

use App\Enums\BeneficiaryStatus;
use App\Enums\CollectionStatus;
use App\Enums\UserStatus;
use App\Enums\VulnerabilityStatus;
use App\Enums\WelfarePackageStatus;
use App\Models\Deceased;
use App\Models\User;
use App\Models\WelfareBeneficiary;
use App\Models\WelfarePackage;
use App\Models\Zone;
use App\Services\Welfare\WelfarePackageLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

uses(\Tests\TestCase::class, RefreshDatabase::class);

// ─── Helpers ─────────────────────────────────────────────────────────────────

if (!function_exists('makeAdmin')) {
    function makeAdmin(): User
    {
        $user = User::factory()->create(['status' => UserStatus::ACTIVE]);
        $user->assignRole('admin');
        return $user;
    }
}

if (!function_exists('makeDraftPackage')) {
    function makeDraftPackage(User $admin, bool $withItems = false): WelfarePackage
    {
        $pkg = WelfarePackage::create([
            'name'       => 'Test Package',
            'status'     => WelfarePackageStatus::DRAFT,
            'created_by' => $admin->id,
            'start_date' => now()->toDateString(),
            'end_date'   => now()->addMonth()->toDateString(),
        ]);

        if ($withItems) {
            $category = \App\Models\Category::create([
                'name'       => 'Test Category ' . uniqid(),
                'user_id'    => $admin->id,
            ]);

            $item = \App\Models\Item::create([
                'name'        => 'Test Item ' . uniqid(),
                'category_id' => $category->id,
                'user_id'     => $admin->id,
            ]);

            \App\Models\WelfarePackageItem::create([
                'welfare_package_id'  => $pkg->id,
                'item_id'             => $item->id,
                'category_id'         => $category->id,
                'quantity_per_family' => 1,
            ]);
        }

        return $pkg;
    }
}

if (!function_exists('makeOpenPackage')) {
    function makeOpenPackage(User $admin): WelfarePackage
    {
        $pkg = WelfarePackage::create([
            'name'       => 'Open Package',
            'status'     => WelfarePackageStatus::OPEN,
            'created_by' => $admin->id,
            'start_date' => now()->toDateString(),
            'end_date'   => now()->addMonth()->toDateString(),
        ]);
        return $pkg;
    }
}

if (!function_exists('makeClosedPackage')) {
    function makeClosedPackage(User $admin): WelfarePackage
    {
        $pkg = WelfarePackage::create([
            'name'       => 'Closed Package',
            'status'     => WelfarePackageStatus::CLOSED,
            'created_by' => $admin->id,
            'start_date' => now()->toDateString(),
            'end_date'   => now()->addMonth()->toDateString(),
        ]);
        return $pkg;
    }
}

if (!function_exists('makeClosedPackageWithItems')) {
    function makeClosedPackageWithItems(User $admin): WelfarePackage
    {
        $pkg = WelfarePackage::create([
            'name'       => 'Closed Package With Items',
            'status'     => WelfarePackageStatus::CLOSED,
            'created_by' => $admin->id,
            'start_date' => now()->toDateString(),
            'end_date'   => now()->addMonth()->toDateString(),
        ]);

        $category = \App\Models\Category::create([
            'name'    => 'Closed Test Category ' . uniqid(),
            'user_id' => $admin->id,
        ]);

        $item = \App\Models\Item::create([
            'name'        => 'Closed Test Item ' . uniqid(),
            'category_id' => $category->id,
            'user_id'     => $admin->id,
        ]);

        \App\Models\WelfarePackageItem::create([
            'welfare_package_id'  => $pkg->id,
            'item_id'             => $item->id,
            'category_id'         => $category->id,
            'quantity_per_family' => 1,
        ]);

        return $pkg;
    }
}

if (!function_exists('addNomination')) {
    function addNomination(WelfarePackage $pkg, User $admin): WelfareBeneficiary
    {
        $zone = Zone::create(['name' => 'Test Zone ' . uniqid(), 'code' => 'TZ-' . uniqid()]);
        $deceased = Deceased::create([
            'first_name'           => 'John',
            'last_name'            => 'Doe',
            'nin'                  => 'NIN' . uniqid(),
            'reg_no'               => 'REG-' . uniqid(),
            'guardian_name'        => 'Guardian ' . uniqid(),
            'guardian_phone'       => '08012345678',
            'vulnerability_status' => VulnerabilityStatus::C,
            'date_registered'      => now()->toDateString(),
            'zone_id'              => $zone->id,
        ]);

        return WelfareBeneficiary::create([
            'welfare_package_id' => $pkg->id,
            'deceased_id'        => $deceased->id,
            'suggested_by'       => $admin->id,
            'status'             => BeneficiaryStatus::PENDING,
            'collection_status'  => CollectionStatus::NOT_COLLECTED,
        ]);
    }
}

// ─── Pure enum state machine ──────────────────────────────────────────────────

describe('WelfarePackageStatus state machine', function () {

    it('allows DRAFT → OPEN', function () {
        expect(WelfarePackageStatus::DRAFT->canTransitionTo(WelfarePackageStatus::OPEN))->toBeTrue();
    });

    it('allows OPEN → CLOSED', function () {
        expect(WelfarePackageStatus::OPEN->canTransitionTo(WelfarePackageStatus::CLOSED))->toBeTrue();
    });

    it('allows CLOSED → OPEN (reopen)', function () {
        expect(WelfarePackageStatus::CLOSED->canTransitionTo(WelfarePackageStatus::OPEN))->toBeTrue();
    });

    it('rejects DRAFT → CLOSED', function () {
        expect(WelfarePackageStatus::DRAFT->canTransitionTo(WelfarePackageStatus::CLOSED))->toBeFalse();
    });

    it('rejects OPEN → DRAFT', function () {
        expect(WelfarePackageStatus::OPEN->canTransitionTo(WelfarePackageStatus::DRAFT))->toBeFalse();
    });

    it('rejects CLOSED → DRAFT', function () {
        expect(WelfarePackageStatus::CLOSED->canTransitionTo(WelfarePackageStatus::DRAFT))->toBeFalse();
    });

    it('rejects OPEN → OPEN (self-transition)', function () {
        expect(WelfarePackageStatus::OPEN->canTransitionTo(WelfarePackageStatus::OPEN))->toBeFalse();
    });

    it('rejects CLOSED → CLOSED (self-transition)', function () {
        expect(WelfarePackageStatus::CLOSED->canTransitionTo(WelfarePackageStatus::CLOSED))->toBeFalse();
    });

    it('rejects DRAFT → DRAFT (self-transition)', function () {
        expect(WelfarePackageStatus::DRAFT->canTransitionTo(WelfarePackageStatus::DRAFT))->toBeFalse();
    });

});

// ─── Server-side lifecycle service ───────────────────────────────────────────

describe('WelfarePackageLifecycleService server-side guards', function () {

    beforeEach(function () {
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
        $this->admin   = makeAdmin();
        $this->service = app(WelfarePackageLifecycleService::class);
    });

    // ── openPackage ──────────────────────────────────────────────────────────

    it('opens a DRAFT package that has items', function () {
        $pkg = makeDraftPackage($this->admin, withItems: true);
        $this->service->openPackage($pkg);
        expect($pkg->fresh()->status)->toBe(WelfarePackageStatus::OPEN);
    });

    it('rejects opening a DRAFT package with no items', function () {
        $pkg = makeDraftPackage($this->admin, withItems: false);
        expect(fn () => $this->service->openPackage($pkg))->toThrow(RuntimeException::class);
    });

    it('rejects opening an OPEN package (OPEN → OPEN illegal)', function () {
        $pkg = makeOpenPackage($this->admin);
        expect(fn () => $this->service->openPackage($pkg))->toThrow(RuntimeException::class);
    });

    it('rejects opening a CLOSED package (CLOSED → OPEN via openPackage is illegal; must use reopenPackage)', function () {
        $pkg = makeClosedPackage($this->admin);
        expect(fn () => $this->service->openPackage($pkg))->toThrow(RuntimeException::class);
    });

    // ── closePackage ─────────────────────────────────────────────────────────

    it('closes an OPEN package', function () {
        $pkg = makeOpenPackage($this->admin);
        $this->service->closePackage($pkg);
        expect($pkg->fresh()->status)->toBe(WelfarePackageStatus::CLOSED);
    });

    it('rejects closing a DRAFT package (DRAFT → CLOSED illegal)', function () {
        $pkg = makeDraftPackage($this->admin);
        expect(fn () => $this->service->closePackage($pkg))->toThrow(RuntimeException::class);
    });

    it('rejects closing a CLOSED package (CLOSED → CLOSED illegal)', function () {
        $pkg = makeClosedPackage($this->admin);
        expect(fn () => $this->service->closePackage($pkg))->toThrow(RuntimeException::class);
    });

    // ── reopenPackage ────────────────────────────────────────────────────────

    it('reopens a CLOSED package that has items', function () {
        $pkg = makeClosedPackageWithItems($this->admin);
        $this->service->reopenPackage($pkg);
        expect($pkg->fresh()->status)->toBe(WelfarePackageStatus::OPEN);
    });

    it('rejects reopening a CLOSED package with no items', function () {
        $pkg = makeClosedPackage($this->admin);
        expect(fn () => $this->service->reopenPackage($pkg))->toThrow(RuntimeException::class);
    });

    it('rejects reopening an OPEN package (OPEN → OPEN via reopen illegal)', function () {
        $pkg = makeOpenPackage($this->admin);
        expect(fn () => $this->service->reopenPackage($pkg))->toThrow(RuntimeException::class);
    });

    it('rejects reopening a DRAFT package', function () {
        $pkg = makeDraftPackage($this->admin);
        expect(fn () => $this->service->reopenPackage($pkg))->toThrow(RuntimeException::class);
    });

});

// ─── Model helper contracts ───────────────────────────────────────────────────

describe('WelfarePackage model helper contracts', function () {

    beforeEach(function () {
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
        $this->admin = makeAdmin();
    });

    it('canBeOpened only for DRAFT', function () {
        expect(makeDraftPackage($this->admin)->canBeOpened())->toBeTrue();
        expect(makeOpenPackage($this->admin)->canBeOpened())->toBeFalse();
        expect(makeClosedPackage($this->admin)->canBeOpened())->toBeFalse();
    });

    it('canBeClosed only for OPEN', function () {
        expect(makeDraftPackage($this->admin)->canBeClosed())->toBeFalse();
        expect(makeOpenPackage($this->admin)->canBeClosed())->toBeTrue();
        expect(makeClosedPackage($this->admin)->canBeClosed())->toBeFalse();
    });

    it('canBeReopened only for CLOSED', function () {
        expect(makeDraftPackage($this->admin)->canBeReopened())->toBeFalse();
        expect(makeOpenPackage($this->admin)->canBeReopened())->toBeFalse();
        expect(makeClosedPackage($this->admin)->canBeReopened())->toBeTrue();
    });

    it('isCompositionEditable is true for DRAFT', function () {
        expect(makeDraftPackage($this->admin)->isCompositionEditable())->toBeTrue();
    });

    it('isCompositionEditable is true for OPEN with zero nominations', function () {
        expect(makeOpenPackage($this->admin)->isCompositionEditable())->toBeTrue();
    });

    it('isCompositionEditable is false for OPEN with nominations', function () {
        $pkg = makeOpenPackage($this->admin);
        addNomination($pkg, $this->admin);
        expect($pkg->fresh()->isCompositionEditable())->toBeFalse();
    });

    it('isCompositionEditable is false for CLOSED with zero nominations', function () {
        expect(makeClosedPackage($this->admin)->isCompositionEditable())->toBeFalse();
    });

    it('isCompositionEditable is false for CLOSED with nominations (reopen does not restore editability)', function () {
        $pkg = makeClosedPackageWithItems($this->admin);
        addNomination($pkg, $this->admin);
        // Reopen
        app(WelfarePackageLifecycleService::class)->reopenPackage($pkg);
        expect($pkg->fresh()->isCompositionEditable())->toBeFalse();
    });

    it('hasNominations is false when no beneficiaries exist', function () {
        expect(makeOpenPackage($this->admin)->hasNominations())->toBeFalse();
    });

    it('hasNominations is true after a nomination is added', function () {
        $pkg = makeOpenPackage($this->admin);
        addNomination($pkg, $this->admin);
        expect($pkg->fresh()->hasNominations())->toBeTrue();
    });

});

// ─── Filament action visibility (unit-style via model state) ──────────────────

describe('Welfare Package action visibility matrix', function () {

    beforeEach(function () {
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
        $this->admin = makeAdmin();
    });

    it('DRAFT: Edit visible, Delete visible, Open visible, Close hidden, Reopen hidden', function () {
        $pkg = makeDraftPackage($this->admin);
        expect($pkg->isCompositionEditable())->toBeTrue('Edit should be visible for DRAFT');
        expect($pkg->isDraft() && ! $pkg->hasNominations())->toBeTrue('Delete should be visible for DRAFT with no nominations');
        expect($pkg->canBeOpened())->toBeTrue('Open should be visible for DRAFT');
        expect($pkg->canBeClosed())->toBeFalse('Close should be hidden for DRAFT');
        expect($pkg->canBeReopened())->toBeFalse('Reopen should be hidden for DRAFT');
    });

    it('OPEN with zero nominations: Edit visible, Close visible, Open hidden, Reopen hidden, Delete hidden', function () {
        $pkg = makeOpenPackage($this->admin);
        expect($pkg->isCompositionEditable())->toBeTrue('Edit should be visible for OPEN with no nominations');
        expect($pkg->canBeClosed())->toBeTrue('Close should be visible for OPEN');
        expect($pkg->canBeOpened())->toBeFalse('Open should be hidden for OPEN');
        expect($pkg->canBeReopened())->toBeFalse('Reopen should be hidden for OPEN');
        expect($pkg->isDraft() && ! $pkg->hasNominations())->toBeFalse('Delete should be hidden for OPEN');
    });

    it('OPEN with nominations: Edit hidden, Close visible, Open hidden, Reopen hidden, Delete hidden', function () {
        $pkg = makeOpenPackage($this->admin);
        addNomination($pkg, $this->admin);
        $pkg = $pkg->fresh();
        expect($pkg->isCompositionEditable())->toBeFalse('Edit should be hidden for OPEN with nominations');
        expect($pkg->canBeClosed())->toBeTrue('Close should be visible for OPEN');
        expect($pkg->canBeOpened())->toBeFalse('Open should be hidden for OPEN');
        expect($pkg->canBeReopened())->toBeFalse('Reopen should be hidden for OPEN');
        expect($pkg->isDraft() && ! $pkg->hasNominations())->toBeFalse('Delete should be hidden');
    });

    it('CLOSED: Edit hidden, Open hidden, Close hidden, Reopen visible, Delete hidden', function () {
        $pkg = makeClosedPackage($this->admin);
        expect($pkg->isCompositionEditable())->toBeFalse('Edit should be hidden for CLOSED');
        expect($pkg->canBeOpened())->toBeFalse('Open should be hidden for CLOSED');
        expect($pkg->canBeClosed())->toBeFalse('Close should be hidden for CLOSED');
        expect($pkg->canBeReopened())->toBeTrue('Reopen should be visible for CLOSED');
        expect($pkg->isDraft() && ! $pkg->hasNominations())->toBeFalse('Delete should be hidden for CLOSED');
    });

});
