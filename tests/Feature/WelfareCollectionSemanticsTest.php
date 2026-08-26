<?php

namespace Tests\Feature;

use App\Enums\BeneficiaryStatus;
use App\Enums\CollectionStatus;
use App\Enums\UserStatus;
use App\Enums\VulnerabilityStatus;
use App\Enums\WelfarePackageStatus;
use App\Filament\Coordinator\Resources\WelfareRequestResource\Pages\ListWelfareRequests;
use App\Models\Deceased;
use App\Models\User;
use App\Models\WelfareBeneficiary;
use App\Models\WelfarePackage;
use App\Models\Zone;
use App\Services\BeneficiaryService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);

    $this->zoneA = Zone::create(['name' => 'Zone North', 'code' => 'ZN-01']);
    $this->zoneB = Zone::create(['name' => 'Zone South', 'code' => 'ZS-02']);

    $this->admin = User::factory()->create([
        'status' => UserStatus::ACTIVE,
    ]);
    $this->admin->assignRole('admin');

    $this->coordinator = User::factory()->create([
        'status' => UserStatus::ACTIVE,
    ]);
    $this->coordinator->assignRole('coordinator');
    $this->zoneA->update(['coordinator_id' => $this->coordinator->id]);

    $this->package = WelfarePackage::create([
        'name' => 'Ramadan Care 2026',
        'description' => 'Food basket distribution',
        'start_date' => now()->subDays(2),
        'end_date' => now()->addDays(10),
        'status' => WelfarePackageStatus::OPEN,
        'created_by' => $this->admin->id,
    ]);

    $this->deceased1 = Deceased::create([
        'first_name' => 'Kabiru',
        'last_name' => 'Garba',
        'nin' => '50000000001',
        'reg_no' => 'DEC-C001',
        'guardian_name' => 'Guardian Kabiru',
        'guardian_phone' => '08012345678',
        'date_registered' => now()->subMonths(1),
        'date_of_death' => now()->subMonths(2),
        'zone_id' => $this->zoneA->id,
        'vulnerability_status' => VulnerabilityStatus::A,
    ]);

    \App\Models\Widow::create([
        'first_name' => 'Widow Kabiru',
        'last_name' => 'Garba',
        'nin' => '60000000001',
        'reg_no' => 'WID-C001',
        'child_sequence' => 1,
        'deceased_id' => $this->deceased1->id,
        'is_eligible' => true,
        'is_married' => false,
    ]);

    $this->deceased2 = Deceased::create([
        'first_name' => 'Usman',
        'last_name' => 'Bello',
        'nin' => '50000000002',
        'reg_no' => 'DEC-C002',
        'guardian_name' => 'Guardian Usman',
        'guardian_phone' => '08012345678',
        'date_registered' => now()->subMonths(1),
        'date_of_death' => now()->subMonths(2),
        'zone_id' => $this->zoneA->id,
        'vulnerability_status' => VulnerabilityStatus::B,
    ]);

    \App\Models\Widow::create([
        'first_name' => 'Widow Usman',
        'last_name' => 'Bello',
        'nin' => '60000000002',
        'reg_no' => 'WID-C002',
        'child_sequence' => 1,
        'deceased_id' => $this->deceased2->id,
        'is_eligible' => true,
        'is_married' => false,
    ]);
});

// 1 & 2 & 3. Livewire table filter "Not Yet Collected" does not throw HTTP 500
test('1. livewire not_collected table filter executes without HTTP 500 error', function () {
    Filament::setCurrentPanel(Filament::getPanel('coordinator'));
    $this->actingAs($this->coordinator);

    $pendingNotCollected = WelfareBeneficiary::create([
        'welfare_package_id' => $this->package->id,
        'deceased_id' => $this->deceased1->id,
        'status' => BeneficiaryStatus::PENDING,
        'collection_status' => CollectionStatus::NOT_COLLECTED,
        'suggested_by' => $this->coordinator->id,
    ]);

    $approvedCollected = WelfareBeneficiary::create([
        'welfare_package_id' => $this->package->id,
        'deceased_id' => $this->deceased2->id,
        'status' => BeneficiaryStatus::APPROVED,
        'collection_status' => CollectionStatus::COLLECTED,
        'collected_at' => now(),
        'collected_by' => $this->coordinator->id,
        'suggested_by' => $this->coordinator->id,
    ]);

    Livewire::test(ListWelfareRequests::class)
        ->filterTable('not_collected', true)
        ->assertCanSeeTableRecords([$pendingNotCollected])
        ->assertCanNotSeeTableRecords([$approvedCollected]);
});

// 4 & 5 & 6. Pending + NOT_COLLECTED, Approved + NOT_COLLECTED, Approved + COLLECTED semantics
test('4. pending + NOT_COLLECTED and approved + NOT_COLLECTED appear in not_collected filter, approved + COLLECTED is excluded', function () {
    $pendingNotCollected = WelfareBeneficiary::create([
        'welfare_package_id' => $this->package->id,
        'deceased_id' => $this->deceased1->id,
        'status' => BeneficiaryStatus::PENDING,
        'collection_status' => CollectionStatus::NOT_COLLECTED,
        'suggested_by' => $this->coordinator->id,
    ]);

    $approvedNotCollected = WelfareBeneficiary::create([
        'welfare_package_id' => $this->package->id,
        'deceased_id' => $this->deceased2->id,
        'status' => BeneficiaryStatus::APPROVED,
        'collection_status' => CollectionStatus::NOT_COLLECTED,
        'suggested_by' => $this->coordinator->id,
    ]);

    $approvedCollected = WelfareBeneficiary::create([
        'welfare_package_id' => $this->package->id,
        'deceased_id' => Deceased::create([
            'first_name' => 'Other',
            'last_name' => 'Head',
            'nin' => '50000000003',
            'reg_no' => 'DEC-C003',
            'guardian_name' => 'Guardian Other',
            'guardian_phone' => '08012345678',
            'date_registered' => now()->subMonths(1),
            'date_of_death' => now()->subMonths(2),
            'zone_id' => $this->zoneA->id,
            'vulnerability_status' => VulnerabilityStatus::C,
        ])->id,
        'status' => BeneficiaryStatus::APPROVED,
        'collection_status' => CollectionStatus::COLLECTED,
        'collected_at' => now(),
        'collected_by' => $this->coordinator->id,
        'suggested_by' => $this->coordinator->id,
    ]);

    $results = WelfareBeneficiary::notCollected()->get();

    expect($results->pluck('id')->toArray())
        ->toContain($pendingNotCollected->id)
        ->toContain($approvedNotCollected->id)
        ->not->toContain($approvedCollected->id);
});

// 7 & 11 & 12. Collection Domain Invariants
test('7. collection invariants enforce that only approved records can be collected and duplicates throw exception', function () {
    $pending = WelfareBeneficiary::create([
        'welfare_package_id' => $this->package->id,
        'deceased_id' => $this->deceased1->id,
        'status' => BeneficiaryStatus::PENDING,
        'collection_status' => CollectionStatus::NOT_COLLECTED,
        'suggested_by' => $this->coordinator->id,
    ]);
    expect($pending->canBeCollected())->toBeFalse();

    $service = app(BeneficiaryService::class);

    // Attempt to collect pending record fails
    expect(fn () => $service->collectPackage($pending, 'Notes', $this->admin->id))
        ->toThrow(\RuntimeException::class);

    // Approve record
    $pending->markAsApproved($this->admin->id);
    expect($pending->refresh()->canBeCollected())->toBeTrue();

    // Mark collected succeeds and updates timestamp
    $collected = $service->collectPackage($pending, 'Delivered', $this->admin->id);
    expect($collected->isCollected())->toBeTrue()
        ->and($collected->collected_at)->not->toBeNull()
        ->and($collected->canBeCollected())->toBeFalse();

    // Duplicate collection attempt fails
    expect(fn () => $service->collectPackage($collected, 'Second try', $this->admin->id))
        ->toThrow(\RuntimeException::class);
});

// 8 & 9 & 10. Display state helper correctness
test('8. isCollected helper evaluates false for NOT_COLLECTED and true for COLLECTED', function () {
    $notCol = WelfareBeneficiary::create([
        'welfare_package_id' => $this->package->id,
        'deceased_id' => $this->deceased1->id,
        'status' => BeneficiaryStatus::APPROVED,
        'collection_status' => CollectionStatus::NOT_COLLECTED,
        'suggested_by' => $this->coordinator->id,
    ]);

    $col = WelfareBeneficiary::create([
        'welfare_package_id' => $this->package->id,
        'deceased_id' => $this->deceased2->id,
        'status' => BeneficiaryStatus::APPROVED,
        'collection_status' => CollectionStatus::COLLECTED,
        'collected_at' => now(),
        'collected_by' => $this->coordinator->id,
        'suggested_by' => $this->coordinator->id,
    ]);

    expect($notCol->isCollected())->toBeFalse()
        ->and($notCol->collected_at)->toBeNull()
        ->and($col->isCollected())->toBeTrue()
        ->and($col->collected_at)->not->toBeNull();
});

// 13. Coordinator zone isolation in filters
test('13. coordinator cannot see or filter cross-zone beneficiaries', function () {
    Filament::setCurrentPanel(Filament::getPanel('coordinator'));
    $this->actingAs($this->coordinator);

    $outOfZoneDeceased = Deceased::create([
        'first_name' => 'Out',
        'last_name' => 'Zone',
        'nin' => '50000000099',
        'reg_no' => 'DEC-C099',
        'guardian_name' => 'Guardian Out',
        'guardian_phone' => '08012345678',
        'date_registered' => now()->subMonths(1),
        'date_of_death' => now()->subMonths(2),
        'zone_id' => $this->zoneB->id,
        'vulnerability_status' => VulnerabilityStatus::A,
    ]);

    $outOfZoneBeneficiary = WelfareBeneficiary::create([
        'welfare_package_id' => $this->package->id,
        'deceased_id' => $outOfZoneDeceased->id,
        'status' => BeneficiaryStatus::APPROVED,
        'collection_status' => CollectionStatus::NOT_COLLECTED,
        'suggested_by' => $this->admin->id,
    ]);

    Livewire::test(ListWelfareRequests::class)
        ->assertCanNotSeeTableRecords([$outOfZoneBeneficiary]);
});

// 14 & 15. Combined filters
test('14. combined status, package, and not_collected filters work seamlessly together', function () {
    Filament::setCurrentPanel(Filament::getPanel('coordinator'));
    $this->actingAs($this->coordinator);

    $targetRecord = WelfareBeneficiary::create([
        'welfare_package_id' => $this->package->id,
        'deceased_id' => $this->deceased1->id,
        'status' => BeneficiaryStatus::APPROVED,
        'collection_status' => CollectionStatus::NOT_COLLECTED,
        'suggested_by' => $this->coordinator->id,
    ]);

    Livewire::test(ListWelfareRequests::class)
        ->filterTable('welfare_package_id', $this->package->id)
        ->filterTable('status', 'approved')
        ->filterTable('not_collected', true)
        ->assertCanSeeTableRecords([$targetRecord]);
});
