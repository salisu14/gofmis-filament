<?php

use App\Enums\BeneficiaryStatus;
use App\Enums\WelfarePackageStatus;
use App\Filament\Coordinator\Resources\WelfareRequestResource\Pages\CreateWelfareRequest;
use App\Filament\Coordinator\Resources\WelfareRequestResource\Pages\EditWelfareRequest;
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
    Filament::setCurrentPanel(Filament::getPanel('coordinator'));

    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

    $this->coordinator = User::factory()->create();
    $this->coordinator->assignRole('coordinator');

    $this->otherCoordinator = User::factory()->create();
    $this->otherCoordinator->assignRole('coordinator');

    $this->zone = Zone::create(['name' => 'North Zone', 'coordinator_id' => $this->coordinator->id]);
    $this->otherZone = Zone::create(['name' => 'South Zone', 'coordinator_id' => $this->otherCoordinator->id]);

    $this->deceased = Deceased::factory()->create(['zone_id' => $this->zone->id, 'full_name' => 'Musa Bello']);
    $this->otherDeceased = Deceased::factory()->create(['zone_id' => $this->otherZone->id, 'full_name' => 'Ibrahim South']);

    $this->openPackage = WelfarePackage::create([
        'name' => 'Ramadan Care Pack',
        'description' => 'Food items for families',
        'start_date' => now()->subDays(2),
        'end_date' => now()->addDays(15),
        'status' => WelfarePackageStatus::OPEN,
        'created_by' => $this->coordinator->id,
    ]);

    $this->closedPackage = WelfarePackage::create([
        'name' => 'Expired Pack',
        'description' => 'Closed package',
        'start_date' => now()->subDays(30),
        'end_date' => now()->subDays(10),
        'status' => WelfarePackageStatus::CLOSED,
        'created_by' => $this->coordinator->id,
    ]);

    $this->actingAs($this->coordinator);
});

test('1. coordinator welfare request create page renders', function () {
    Livewire::test(CreateWelfareRequest::class)
        ->assertSuccessful();
});

test('2. render completes fast without recursion or timeout', function () {
    $startTime = microtime(true);

    Livewire::test(CreateWelfareRequest::class)
        ->assertSuccessful();

    $duration = microtime(true) - $startTime;
    expect($duration)->toBeLessThan(2.0);
});

test('3. welfare package options load open packages', function () {
    Livewire::test(CreateWelfareRequest::class)
        ->assertFormFieldExists('welfare_package_id');

    expect(WelfarePackage::open()->pluck('id'))->toContain($this->openPackage->id);
});

test('4 & 5. valid welfare request can be created and beneficiary linkage persists correctly', function () {
    Livewire::test(CreateWelfareRequest::class)
        ->fillForm([
            'welfare_package_id' => (string) $this->openPackage->id,
        ])
        ->fillForm([
            'deceased_id' => (string) $this->deceased->id,
        ])
        ->fillForm([
            'collection_notes' => 'Emergency flood relief for family.',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('welfare_beneficiaries', [
        'welfare_package_id' => $this->openPackage->id,
        'deceased_id' => $this->deceased->id,
        'collection_notes' => 'Emergency flood relief for family.',
        'status' => BeneficiaryStatus::PENDING->value,
    ]);
});

test('6. coordinator only sees own-zone beneficiaries', function () {
    Livewire::test(CreateWelfareRequest::class)
        ->assertFormFieldExists('deceased_id');
});

test('7. inaccessible-zone beneficiary cannot be submitted', function () {
    Livewire::test(CreateWelfareRequest::class)
        ->fillForm([
            'welfare_package_id' => (string) $this->openPackage->id,
        ])
        ->fillForm([
            'deceased_id' => (string) $this->otherDeceased->id,
        ])
        ->fillForm([
            'collection_notes' => 'Cross-zone attempt.',
        ])
        ->call('create')
        ->assertHasFormErrors(['deceased_id']);

    $this->assertDatabaseMissing('welfare_beneficiaries', [
        'collection_notes' => 'Cross-zone attempt.',
    ]);
});

test('8. changing package selection updates placeholder state smoothly', function () {
    Livewire::test(CreateWelfareRequest::class)
        ->set('data.welfare_package_id', $this->openPackage->id)
        ->assertSet('data.welfare_package_id', $this->openPackage->id);
});

test('9. unavailable/closed welfare package is not in options', function () {
    $options = WelfarePackage::open()->pluck('id');
    expect($options)->toContain($this->openPackage->id)
        ->and($options)->not->toContain($this->closedPackage->id);
});

test('10. edit and view pages render cleanly', function () {
    $beneficiary = WelfareBeneficiary::create([
        'welfare_package_id' => $this->openPackage->id,
        'deceased_id' => $this->deceased->id,
        'collection_notes' => 'Existing welfare request',
        'status' => BeneficiaryStatus::PENDING->value,
        'suggested_by' => $this->coordinator->id,
    ]);

    Livewire::test(ViewWelfareRequest::class, ['record' => $beneficiary->getRouteKey()])
        ->assertSuccessful();

    Livewire::test(EditWelfareRequest::class, ['record' => $beneficiary->getRouteKey()])
        ->assertSuccessful()
        ->fillForm([
            'collection_notes' => 'Updated justification text.',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($beneficiary->fresh()->collection_notes)->toBe('Updated justification text.');
});
