<?php

use App\Enums\Gender;
use App\Enums\WidowLoanStatus;
use App\Filament\Coordinator\Resources\LoanRequestResource\Pages\CreateLoanRequest;
use App\Filament\Coordinator\Resources\LoanRequestResource\Pages\ListLoanRequests;
use App\Models\Deceased;
use App\Models\User;
use App\Models\Widow;
use App\Models\WidowLoan;
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

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->zone = Zone::create(['name' => 'North Zone', 'coordinator_id' => $this->coordinator->id]);
    $this->otherZone = Zone::create(['name' => 'South Zone', 'coordinator_id' => $this->otherCoordinator->id]);

    $this->deceased = Deceased::factory()->create(['zone_id' => $this->zone->id]);
    $this->otherDeceased = Deceased::factory()->create(['zone_id' => $this->otherZone->id]);

    $this->widow = Widow::create([
        'deceased_id' => $this->deceased->id,
        'first_name' => 'Fatimah',
        'last_name' => 'Aliyu',
        'nin' => '12345678901',
        'reg_no' => 'WID-00001',
        'child_sequence' => 1,
        'gender' => Gender::FEMALE,
        'is_eligible' => true,
        'is_married' => false,
    ]);

    $this->otherWidow = Widow::create([
        'deceased_id' => $this->otherDeceased->id,
        'first_name' => 'Halima',
        'last_name' => 'Usman',
        'nin' => '12345678902',
        'reg_no' => 'WID-00002',
        'child_sequence' => 1,
        'gender' => Gender::FEMALE,
        'is_eligible' => true,
        'is_married' => false,
    ]);

    $this->actingAs($this->coordinator);
});

test('1. coordinator can render Loan Request create page', function () {
    Livewire::test(CreateLoanRequest::class)
        ->assertSuccessful();
});

test('2. coordinator can create valid loan request for own-zone widow', function () {
    Livewire::test(CreateLoanRequest::class)
        ->fillForm([
            'widow_id' => (string) $this->widow->id,
            'principal_amount' => 50000,
            'duration_months' => 6,
            'repayment_frequency' => 'weekly',
            'purpose' => 'Small trading business capital',
            'notes' => 'Verified beneficiary by coordinator',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('widow_loans', [
        'widow_id' => $this->widow->id,
        'principal_amount' => 50000.00,
        'duration_months' => 6,
        'repayment_frequency' => 'weekly',
        'purpose' => 'Small trading business capital',
        'status' => WidowLoanStatus::DRAFT->value,
    ]);
});

test('3. coordinator cannot select other-zone widow for loan request', function () {
    Livewire::test(CreateLoanRequest::class)
        ->fillForm([
            'widow_id' => (string) $this->otherWidow->id,
            'principal_amount' => 50000,
            'duration_months' => 6,
            'repayment_frequency' => 'weekly',
            'purpose' => 'Cross-zone loan request attempt',
        ])
        ->call('create')
        ->assertHasFormErrors(['widow_id']);
});

test('4. coordinator can submit draft loan request for approval', function () {
    $loan = WidowLoan::create([
        'widow_id' => $this->widow->id,
        'principal_amount' => 50000,
        'duration_months' => 6,
        'repayment_frequency' => 'weekly',
        'purpose' => 'Trading business',
        'status' => WidowLoanStatus::DRAFT,
        'outstanding_balance' => 0,
    ]);

    Livewire::test(ListLoanRequests::class)
        ->callTableAction('submit', $loan)
        ->assertHasNoTableActionErrors();

    expect($loan->fresh()->status)->toBe(WidowLoanStatus::PENDING);
});
