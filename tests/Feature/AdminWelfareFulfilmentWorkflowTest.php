<?php

use App\Enums\BeneficiaryStatus;
use App\Enums\CollectionStatus;
use App\Filament\Coordinator\Resources\WelfareRequestResource;
use App\Filament\Coordinator\Resources\WelfareRequestResource\Pages\EditWelfareRequest;
use App\Filament\Coordinator\Resources\WelfareRequestResource\Pages\ViewWelfareRequest;
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
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

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

    $this->openPackage = WelfarePackage::create([
        'name' => 'Ramadan Food Package 2026',
        'description' => 'Rice, Sugar, Oil & Flour distribution',
        'is_open' => true,
        'start_date' => now()->subDays(5),
        'end_date' => now()->addDays(20),
        'created_by' => $this->admin->id,
    ]);

    $this->service = app(BeneficiaryService::class);
});

test('1. end-to-end welfare request -> admin approval -> collection fulfilment -> coordinator outcome visibility', function () {
    // 1. Coordinator submits Welfare Request for open package
    $beneficiary = WelfareBeneficiary::create([
        'welfare_package_id' => $this->openPackage->id,
        'deceased_id' => $this->deceased->id,
        'status' => BeneficiaryStatus::PENDING,
        'collection_status' => CollectionStatus::NOT_COLLECTED,
        'collection_notes' => 'Urgent food relief required for family',
        'suggested_by' => $this->coordinator->id,
    ]);

    expect($beneficiary->status)->toBe(BeneficiaryStatus::PENDING);

    // 2. Admin Approval via Service
    $this->service->approveBeneficiary($beneficiary, $this->admin->id);
    $beneficiary = $beneficiary->fresh();

    expect($beneficiary->status)->toBe(BeneficiaryStatus::APPROVED)
        ->and($beneficiary->approved_by)->toBe($this->admin->id)
        ->and($beneficiary->collection_status)->toBe(CollectionStatus::NOT_COLLECTED);

    // 3. Mark Collected (Fulfilment) via Service
    $this->service->collectPackage($beneficiary, 'Handed over package at Kano central warehouse', $this->admin->id);
    $beneficiary = $beneficiary->fresh();

    expect($beneficiary->collection_status)->toBe(CollectionStatus::COLLECTED)
        ->and($beneficiary->collected_at)->not->toBeNull()
        ->and($beneficiary->collected_by)->toBe($this->admin->id)
        ->and($beneficiary->collection_notes)->toBe('Handed over package at Kano central warehouse');

    // 4. Coordinator sees final operational outcome read-only
    Filament::setCurrentPanel(Filament::getPanel('coordinator'));
    $this->actingAs($this->coordinator);

    Livewire::test(ViewWelfareRequest::class, ['record' => $beneficiary->getRouteKey()])
        ->assertSuccessful()
        ->assertSee($this->deceased->full_name);
});

test('2. duplicate collection attempt throws exception and preserves original collection metadata', function () {
    $beneficiary = WelfareBeneficiary::create([
        'welfare_package_id' => $this->openPackage->id,
        'deceased_id' => $this->deceased->id,
        'status' => BeneficiaryStatus::APPROVED,
        'collection_status' => CollectionStatus::NOT_COLLECTED,
        'suggested_by' => $this->coordinator->id,
    ]);

    // First collection succeeds
    $this->service->collectPackage($beneficiary, 'Initial collection', $this->admin->id);
    $beneficiary = $beneficiary->fresh();

    // Second collection attempt must fail
    expect(fn () => $this->service->collectPackage($beneficiary, 'Duplicate attempt', $this->admin->id))
        ->toThrow(RuntimeException::class);

    $beneficiary = $beneficiary->fresh();
    expect($beneficiary->collection_status)->toBe(CollectionStatus::COLLECTED)
        ->and($beneficiary->collection_notes)->toBe('Initial collection');
});

test('3. collected or non-pending welfare record cannot be deleted', function () {
    $beneficiary = WelfareBeneficiary::create([
        'welfare_package_id' => $this->openPackage->id,
        'deceased_id' => $this->deceased->id,
        'status' => BeneficiaryStatus::APPROVED,
        'collection_status' => CollectionStatus::COLLECTED,
        'collected_at' => now(),
        'collected_by' => $this->admin->id,
        'suggested_by' => $this->coordinator->id,
    ]);

    expect(fn () => $beneficiary->delete())->toThrow(DomainException::class);
});

test('4. direct navigation to edit page for non-pending record is denied with forbidden status', function () {
    Filament::setCurrentPanel(Filament::getPanel('coordinator'));
    $this->actingAs($this->coordinator);

    $approvedBeneficiary = WelfareBeneficiary::create([
        'welfare_package_id' => $this->openPackage->id,
        'deceased_id' => $this->deceased->id,
        'status' => BeneficiaryStatus::APPROVED,
        'collection_status' => CollectionStatus::NOT_COLLECTED,
        'suggested_by' => $this->coordinator->id,
    ]);

    Livewire::test(EditWelfareRequest::class, ['record' => $approvedBeneficiary->getRouteKey()])
        ->assertForbidden();
});

test('5. coordinator cannot view or edit welfare request belonging to another zone', function () {
    Filament::setCurrentPanel(Filament::getPanel('coordinator'));
    $this->actingAs($this->coordinator);

    $otherZoneBeneficiary = WelfareBeneficiary::create([
        'welfare_package_id' => $this->openPackage->id,
        'deceased_id' => $this->otherDeceased->id,
        'status' => BeneficiaryStatus::PENDING,
        'suggested_by' => $this->otherCoordinator->id,
    ]);

    expect(WelfareRequestResource::canView($otherZoneBeneficiary))->toBeFalse()
        ->and(WelfareRequestResource::canEdit($otherZoneBeneficiary))->toBeFalse();

    expect(fn () => Livewire::test(ViewWelfareRequest::class, ['record' => $otherZoneBeneficiary->getRouteKey()]))
        ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

    expect(fn () => Livewire::test(EditWelfareRequest::class, ['record' => $otherZoneBeneficiary->getRouteKey()]))
        ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
});
