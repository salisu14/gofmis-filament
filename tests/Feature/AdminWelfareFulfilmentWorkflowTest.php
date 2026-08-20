<?php

use App\Enums\BeneficiaryStatus;
use App\Filament\Coordinator\Resources\WelfareRequestResource\Pages\ViewWelfareRequest;
use App\Models\Deceased;
use App\Models\User;
use App\Models\WelfareBeneficiary;
use App\Models\WelfarePackage;
use App\Models\Zone;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

    $this->coordinator = User::factory()->create();
    $this->coordinator->assignRole('coordinator');

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->zone = Zone::create(['name' => 'North Zone', 'coordinator_id' => $this->coordinator->id]);

    $this->deceased = Deceased::factory()->create(['zone_id' => $this->zone->id]);

    $this->openPackage = WelfarePackage::create([
        'name' => 'Ramadan Food Package 2026',
        'description' => 'Rice, Sugar, Oil & Flour distribution',
        'is_open' => true,
        'start_date' => now()->subDays(5),
        'end_date' => now()->addDays(20),
        'created_by' => $this->admin->id,
    ]);

    $this->closedPackage = WelfarePackage::create([
        'name' => 'Winter Relief Package 2025',
        'description' => 'Blankets & Jackets',
        'is_open' => false,
        'start_date' => now()->subYear(),
        'end_date' => now()->subMonths(6),
        'created_by' => $this->admin->id,
    ]);
});

test('1. end-to-end welfare request -> admin approval -> collection fulfilment -> coordinator outcome visibility', function () {
    // 1. Coordinator submits Welfare Request for open package
    $beneficiary = WelfareBeneficiary::create([
        'welfare_package_id' => $this->openPackage->id,
        'deceased_id' => $this->deceased->id,
        'status' => BeneficiaryStatus::PENDING,
        'collection_status' => \App\Enums\CollectionStatus::NOT_COLLECTED,
        'collection_notes' => 'Urgent food relief required for family',
        'suggested_by' => $this->coordinator->id,
    ]);

    expect($beneficiary->status)->toBe(BeneficiaryStatus::PENDING);

    // 2. Admin Approval
    $beneficiary->update(['status' => BeneficiaryStatus::APPROVED]);
    expect($beneficiary->fresh()->status)->toBe(BeneficiaryStatus::APPROVED);
    expect($beneficiary->fresh()->collection_status)->toBe(\App\Enums\CollectionStatus::NOT_COLLECTED);

    // 3. Mark Collected (Fulfilment)
    $beneficiary->markAsCollected('Handed over package at Kano central warehouse', $this->admin->id);
    $beneficiary = $beneficiary->fresh();

    expect($beneficiary->collection_status)->toBe(\App\Enums\CollectionStatus::COLLECTED);
    expect($beneficiary->collected_at)->not->toBeNull();
    expect($beneficiary->collected_by)->toBe($this->admin->id);

    // 4. Coordinator sees final operational outcome read-only
    Filament::setCurrentPanel(Filament::getPanel('coordinator'));
    $this->actingAs($this->coordinator);

    Livewire::test(ViewWelfareRequest::class, ['record' => $beneficiary->getRouteKey()])
        ->assertSuccessful()
        ->assertSee($this->deceased->full_name);
});
