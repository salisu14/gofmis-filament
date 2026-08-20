<?php

use App\Filament\Imprest\Resources\ImprestFundResource\Pages\ListImprestFunds;
use App\Filament\Resources\BankAccounts\Pages\EditBankAccount;
use App\Filament\Resources\BankAccounts\Pages\ListBankAccounts;
use App\Filament\Resources\EducationFeeInvoices\Pages\ListEducationFeeInvoices;
use App\Filament\Resources\Projects\Pages\ListProjects;
use App\Filament\Resources\WidowLoans\Pages\ListWidowLoans;
use App\Filament\Resources\WidowLoans\Pages\ViewWidowLoan;
use App\Models\BankAccount;
use App\Models\Deceased;
use App\Models\User;
use App\Models\Widow;
use App\Models\WidowLoan;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

    $this->zone = Zone::create(['name' => 'UI Smoke Zone', 'address' => '100 Test Way']);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('super_admin');
    $this->actingAs($this->admin);

    $this->bankAccount = BankAccount::create([
        'user_id' => $this->admin->id,
        'account_name' => 'Main Operating Account',
        'account_number' => '5550001112',
        'opening_balance' => 100000.00,
        'ledger_balance' => 100000.00,
        'reserved_balance' => 0.00,
        'usage' => BankAccount::USAGE_GENERAL,
    ]);
});

test('widow loan resource list and view pages render successfully', function () {
    $deceased = Deceased::factory()->create(['zone_id' => $this->zone->id]);
    $widow = Widow::create([
        'deceased_id' => $deceased->id,
        'reg_no' => 'WID-UI-01',
        'first_name' => 'Fatima',
        'last_name' => 'Widow',
        'nin' => '12345678999',
        'address' => '123 St',
        'child_sequence' => 1,
        'is_eligible' => true,
        'is_married' => false,
    ]);

    $loan = WidowLoan::create([
        'widow_id' => $widow->id,
        'bank_account_id' => $this->bankAccount->id,
        'principal_amount' => 50000.00,
        'total_payable' => 50000.00,
        'duration_months' => 6,
        'repayment_frequency' => \App\Enums\LoanRepaymentFrequency::MONTHLY,
        'status' => \App\Enums\WidowLoanStatus::DRAFT,
        'outstanding_balance' => 50000.00,
    ]);

    Livewire::test(ListWidowLoans::class)
        ->assertSuccessful();

    Livewire::test(ViewWidowLoan::class, [
        'record' => $loan->getRouteKey(),
    ])->assertSuccessful();
});

test('bank account resource list and edit pages render successfully', function () {
    Livewire::test(ListBankAccounts::class)
        ->assertSuccessful();

    Livewire::test(EditBankAccount::class, [
        'record' => $this->bankAccount->getRouteKey(),
    ])->assertSuccessful();
});

test('imprest fund resource list page renders successfully', function () {
    \Filament\Facades\Filament::setCurrentPanel(
        \Filament\Facades\Filament::getPanel('imprest')
    );

    Livewire::test(ListImprestFunds::class)
        ->assertSuccessful();
});

test('education fee invoice resource list page renders successfully', function () {
    Livewire::test(ListEducationFeeInvoices::class)
        ->assertSuccessful();
});

test('project resource list page renders successfully', function () {
    Livewire::test(ListProjects::class)
        ->assertSuccessful();
});
