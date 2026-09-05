<?php

use App\Enums\Gender;
use App\Enums\WidowLoanStatus;
use App\Filament\Coordinator\Resources\LoanRequestResource\Pages\CreateLoanRequest;
use App\Filament\Coordinator\Resources\LoanRequestResource\Pages\ListLoanRequests;
use App\Filament\Resources\WidowLoans\Pages\ListWidowLoans;
use App\Filament\Resources\WidowLoans\Pages\ViewWidowLoan;
use App\Models\BankAccount;
use App\Models\Deceased;
use App\Models\User;
use App\Models\Widow;
use App\Models\WidowLoan;
use App\Models\Zone;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('coordinator'));

    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

    $this->coordinator = User::factory()->create();
    $this->coordinator->assignRole('coordinator');

    $this->otherCoordinator = User::factory()->create();
    $this->otherCoordinator->assignRole('coordinator');

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->superAdmin = User::factory()->create();
    $this->superAdmin->assignRole('super_admin');

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

    $this->mainAccount = BankAccount::create([
        'account_name' => 'Main Operating Account',
        'account_number' => '0000000000',
        'bank_name' => 'First Bank',
        'usage' => 'general',
        'ledger_balance' => 1000000.00,
        'reserved_balance' => 0.00,
        'user_id' => $this->admin->id,
    ]);

    $this->disbursingAccount = BankAccount::create([
        'account_name' => 'Widow Loan Disbursement Fund',
        'account_number' => '1111111111',
        'bank_name' => 'First Bank',
        'usage' => BankAccount::USAGE_WIDOW_LOAN_DISBURSEMENT,
        'reserved_balance' => 0.00,
        'parent_bank_account_id' => $this->mainAccount->id,
        'user_id' => $this->admin->id,
    ]);
    $this->disbursingAccount->update(['ledger_balance' => 500000.00]);

    $this->actingAs($this->coordinator);
});

test('1. coordinator is blocked from rendering Loan Request create page', function () {
    $this->get(\App\Filament\Coordinator\Resources\LoanRequestResource\Pages\CreateLoanRequest::getUrl())
         ->assertForbidden();
});

test('2. coordinator is blocked from rendering Loan Request list page', function () {
    $this->get(\App\Filament\Coordinator\Resources\LoanRequestResource::getUrl('index'))
         ->assertForbidden();
});



