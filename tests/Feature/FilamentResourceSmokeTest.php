<?php

use App\Models\BankAccount;
use App\Models\Deceased;
use App\Models\Institution;
use App\Models\Orphan;
use App\Models\OrphanEducation;
use App\Models\Project;
use App\Models\User;
use App\Models\Widow;
use App\Models\WidowLoan;
use App\Models\Zone;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

    $this->zone = Zone::create(['name' => 'Smoke Test Zone', 'address' => '100 Test St']);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('super_admin');
    $this->actingAs($this->admin);

    // Create minimal reference records for edit/view page rendering
    $this->deceased = Deceased::factory()->create(['zone_id' => $this->zone->id]);
    $this->orphan = Orphan::create([
        'deceased_id' => $this->deceased->id,
        'reg_no' => 'ORP-SMK-01',
        'first_name' => 'Adam',
        'last_name' => 'Smoke',
        'gender' => \App\Enums\Gender::MALE,
        'date_of_birth' => '2015-05-10',
        'status' => \App\Enums\OrphanStatus::ACTIVE,
        'is_eligible' => true,
    ]);
    $this->widow = Widow::create([
        'deceased_id' => $this->deceased->id,
        'reg_no' => 'WID-SMK-01',
        'first_name' => 'Fatima',
        'last_name' => 'Smoke',
        'nin' => '12345678901',
        'address' => '123 Test St',
        'child_sequence' => 1,
        'is_eligible' => true,
        'is_married' => false,
    ]);

    $this->bankAccount = BankAccount::create([
        'user_id' => $this->admin->id,
        'account_name' => 'Smoke Operating Account',
        'account_number' => '9991112223',
        'opening_balance' => 100000.00,
        'ledger_balance' => 100000.00,
        'reserved_balance' => 0.00,
        'usage' => BankAccount::USAGE_GENERAL,
    ]);

    $this->project = Project::create([
        'name' => 'Smoke Project',
        'type' => \App\Enums\ProjectType::WATER,
        'budget_allocated' => 50000.00,
        'status' => \App\Enums\ProjectStatus::PLANNING,
        'zone_id' => $this->zone->id,
    ]);

    $this->loan = WidowLoan::create([
        'widow_id' => $this->widow->id,
        'bank_account_id' => $this->bankAccount->id,
        'principal_amount' => 50000.00,
        'total_payable' => 50000.00,
        'duration_months' => 6,
        'repayment_frequency' => \App\Enums\LoanRepaymentFrequency::MONTHLY,
        'status' => \App\Enums\WidowLoanStatus::DISBURSED,
        'disbursed_at' => now(),
    ]);

    $this->institution = Institution::create([
        'name' => 'Smoke Academy',
        'type' => \App\Enums\InstitutionType::WESTERN,
        'address' => '123 School Rd',
    ]);

    $this->orphanEducation = OrphanEducation::create([
        'orphan_id' => $this->orphan->id,
        'institution_id' => $this->institution->id,
        'academic_year' => '2025/2026',
        'level' => 'Secondary',
    ]);
});

test('admin panel core beneficiary resources (Deceased, Orphan, Widow) list, view, edit render without errors', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    // Deceased
    Livewire::test(\App\Filament\Resources\Deceased\Pages\ListDeceaseds::class)->assertSuccessful();
    Livewire::test(\App\Filament\Resources\Deceased\Pages\CreateDeceased::class)->assertSuccessful();
    Livewire::test(\App\Filament\Resources\Deceased\Pages\ViewDeceased::class, ['record' => $this->deceased->getRouteKey()])->assertSuccessful();
    Livewire::test(\App\Filament\Resources\Deceased\Pages\EditDeceased::class, ['record' => $this->deceased->getRouteKey()])->assertSuccessful();

    // Orphan
    Livewire::test(\App\Filament\Resources\Orphans\Pages\ListOrphans::class)->assertSuccessful();
    Livewire::test(\App\Filament\Resources\Orphans\Pages\CreateOrphan::class)->assertSuccessful();
    Livewire::test(\App\Filament\Resources\Orphans\Pages\ViewOrphan::class, ['record' => $this->orphan->getRouteKey()])->assertSuccessful();
    Livewire::test(\App\Filament\Resources\Orphans\Pages\EditOrphan::class, ['record' => $this->orphan->getRouteKey()])->assertSuccessful();

    // Widow
    Livewire::test(\App\Filament\Resources\Widows\Pages\ListWidows::class)->assertSuccessful();
    Livewire::test(\App\Filament\Resources\Widows\Pages\CreateWidow::class)->assertSuccessful();
    Livewire::test(\App\Filament\Resources\Widows\Pages\ViewWidow::class, ['record' => $this->widow->getRouteKey()])->assertSuccessful();
    Livewire::test(\App\Filament\Resources\Widows\Pages\EditWidow::class, ['record' => $this->widow->getRouteKey()])->assertSuccessful();
});

test('admin panel financial resources (Bank Accounts, Loans, Transactions) render without errors', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    // Bank Account
    Livewire::test(\App\Filament\Resources\BankAccounts\Pages\ListBankAccounts::class)->assertSuccessful();
    Livewire::test(\App\Filament\Resources\BankAccounts\Pages\CreateBankAccount::class)->assertSuccessful();
    Livewire::test(\App\Filament\Resources\BankAccounts\Pages\EditBankAccount::class, ['record' => $this->bankAccount->getRouteKey()])->assertSuccessful();

    // Widow Loans
    Livewire::test(\App\Filament\Resources\WidowLoans\Pages\ListWidowLoans::class)->assertSuccessful();
    Livewire::test(\App\Filament\Resources\WidowLoans\Pages\CreateWidowLoan::class)->assertSuccessful();
    Livewire::test(\App\Filament\Resources\WidowLoans\Pages\ViewWidowLoan::class, ['record' => $this->loan->getRouteKey()])->assertSuccessful();
    Livewire::test(\App\Filament\Resources\WidowLoans\Pages\EditWidowLoan::class, ['record' => $this->loan->getRouteKey()])->assertSuccessful();

    // Transactions
    Livewire::test(\App\Filament\Resources\Transactions\Pages\ListTransactions::class)->assertSuccessful();
});

test('admin panel education and project resources render without errors', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    // Projects
    Livewire::test(\App\Filament\Resources\Projects\Pages\ListProjects::class)->assertSuccessful();
    Livewire::test(\App\Filament\Resources\Projects\Pages\CreateProject::class)->assertSuccessful();
    Livewire::test(\App\Filament\Resources\Projects\Pages\ViewProject::class, ['record' => $this->project->getRouteKey()])->assertSuccessful();
    Livewire::test(\App\Filament\Resources\Projects\Pages\EditProject::class, ['record' => $this->project->getRouteKey()])->assertSuccessful();

    // Orphan Education
    Livewire::test(\App\Filament\Resources\OrphanEducation\Pages\ListOrphanEducation::class)->assertSuccessful();
    Livewire::test(\App\Filament\Resources\OrphanEducation\Pages\CreateOrphanEducation::class)->assertSuccessful();
    Livewire::test(\App\Filament\Resources\OrphanEducation\Pages\ViewOrphanEducation::class, ['record' => $this->orphanEducation->getRouteKey()])->assertSuccessful();
    Livewire::test(\App\Filament\Resources\OrphanEducation\Pages\EditOrphanEducation::class, ['record' => $this->orphanEducation->getRouteKey()])->assertSuccessful();
});

test('coordinator panel resources render without errors', function () {
    Filament::setCurrentPanel(Filament::getPanel('coordinator'));

    // Loan Requests
    Livewire::test(\App\Filament\Coordinator\Resources\LoanRequestResource\Pages\ListLoanRequests::class)->assertSuccessful();
    Livewire::test(\App\Filament\Coordinator\Resources\LoanRequestResource\Pages\CreateLoanRequest::class)->assertSuccessful();
    Livewire::test(\App\Filament\Coordinator\Resources\LoanRequestResource\Pages\ViewLoanRequest::class, ['record' => $this->loan->getRouteKey()])->assertSuccessful();
    Livewire::test(\App\Filament\Coordinator\Resources\LoanRequestResource\Pages\EditLoanRequest::class, ['record' => $this->loan->getRouteKey()])->assertSuccessful();

    // Healthcare Requests
    Livewire::test(\App\Filament\Coordinator\Resources\HealthcareRequestResource\Pages\ListHealthcareRequests::class)->assertSuccessful();
    Livewire::test(\App\Filament\Coordinator\Resources\HealthcareRequestResource\Pages\CreateHealthcareRequest::class)->assertSuccessful();

    // Welfare Requests
    Livewire::test(\App\Filament\Coordinator\Resources\WelfareRequestResource\Pages\ListWelfareRequests::class)->assertSuccessful();
    Livewire::test(\App\Filament\Coordinator\Resources\WelfareRequestResource\Pages\CreateWelfareRequest::class)->assertSuccessful();

    // Core Coordinator Resources
    Livewire::test(\App\Filament\Coordinator\Resources\DeceasedResource\Pages\ListDeceaseds::class)->assertSuccessful();
    Livewire::test(\App\Filament\Coordinator\Resources\OrphanResource\Pages\ListOrphans::class)->assertSuccessful();
    Livewire::test(\App\Filament\Coordinator\Resources\WidowResource\Pages\ListWidows::class)->assertSuccessful();
    Livewire::test(\App\Filament\Coordinator\Resources\ProjectResource\Pages\ListProjects::class)->assertSuccessful();
});

test('imprest panel resources render without errors', function () {
    Filament::setCurrentPanel(Filament::getPanel('imprest'));

    Livewire::test(\App\Filament\Imprest\Resources\ImprestFundResource\Pages\ListImprestFunds::class)->assertSuccessful();
    Livewire::test(\App\Filament\Imprest\Resources\ImprestReconciliationResource\Pages\ListImprestReconciliations::class)->assertSuccessful();
    Livewire::test(\App\Filament\Imprest\Resources\ImprestReplenishmentResource\Pages\ListImprestReplenishments::class)->assertSuccessful();
    Livewire::test(\App\Filament\Imprest\Resources\ImprestTransactionResource\Pages\ListImprestTransactions::class)->assertSuccessful();
});
