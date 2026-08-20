<?php

use App\Models\BankAccount;
use App\Models\Deceased;
use App\Models\EducationFeeInvoice;
use App\Models\Institution;
use App\Models\Orphan;
use App\Models\Project;
use App\Models\User;
use App\Models\Widow;
use App\Models\WidowLoan;
use App\Models\Zone;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

    $this->zone = Zone::create(['name' => 'Rel Test Zone', 'address' => '100 Test St']);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('super_admin');
    $this->actingAs($this->admin);

    $this->deceased = Deceased::factory()->create(['zone_id' => $this->zone->id]);
    $this->orphan = Orphan::create([
        'deceased_id' => $this->deceased->id,
        'reg_no' => 'ORP-REL-01',
        'first_name' => 'Ahmad',
        'last_name' => 'Rel',
        'gender' => \App\Enums\Gender::MALE,
        'date_of_birth' => '2015-01-01',
        'status' => \App\Enums\OrphanStatus::ACTIVE,
        'is_eligible' => true,
    ]);

    $this->widow = Widow::create([
        'deceased_id' => $this->deceased->id,
        'reg_no' => 'WID-REL-01',
        'first_name' => 'Aisha',
        'last_name' => 'Rel',
        'nin' => '12345678902',
        'address' => '123 Rel St',
        'child_sequence' => 1,
        'is_eligible' => true,
        'is_married' => false,
    ]);

    $this->bankAccount = BankAccount::create([
        'user_id' => $this->admin->id,
        'account_name' => 'Rel Operating Account',
        'account_number' => '9991112224',
        'opening_balance' => 50000.00,
        'ledger_balance' => 50000.00,
        'reserved_balance' => 0.00,
        'usage' => BankAccount::USAGE_GENERAL,
    ]);

    $this->project = Project::create([
        'name' => 'Rel Project',
        'type' => \App\Enums\ProjectType::WATER,
        'budget_allocated' => 50000.00,
        'status' => \App\Enums\ProjectStatus::PLANNING,
        'zone_id' => $this->zone->id,
    ]);

    $this->loan = WidowLoan::create([
        'widow_id' => $this->widow->id,
        'bank_account_id' => $this->bankAccount->id,
        'principal_amount' => 30000.00,
        'total_payable' => 30000.00,
        'duration_months' => 3,
        'repayment_frequency' => \App\Enums\LoanRepaymentFrequency::MONTHLY,
        'status' => \App\Enums\WidowLoanStatus::DISBURSED,
        'disbursed_at' => now(),
    ]);

    $this->institution = Institution::create([
        'name' => 'Rel Academy',
        'type' => \App\Enums\InstitutionType::WESTERN,
        'address' => '123 Rel Rd',
    ]);

    $this->invoice = EducationFeeInvoice::create([
        'orphan_education_id' => \App\Models\OrphanEducation::create([
            'orphan_id' => $this->orphan->id,
            'institution_id' => $this->institution->id,
            'academic_year' => '2025/2026',
            'level' => 'Secondary',
        ])->id,
        'invoice_number' => 'INV-REL-001',
        'term' => 'Term 1',
        'period' => '2025/2026 Term 1',
        'academic_year' => '2025/2026',
        'due_date' => now()->addDays(30),
        'amount' => 15000.00,
        'status' => 'pending',
    ]);
});

test('deceased relation managers (Orphans, Widows) render successfully', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(\App\Filament\Resources\Deceased\RelationManagers\OrphansRelationManager::class, [
        'ownerRecord' => $this->deceased,
        'pageClass' => \App\Filament\Resources\Deceased\Pages\EditDeceased::class,
    ])->assertSuccessful();

    Livewire::test(\App\Filament\Resources\Deceased\RelationManagers\WidowsRelationManager::class, [
        'ownerRecord' => $this->deceased,
        'pageClass' => \App\Filament\Resources\Deceased\Pages\EditDeceased::class,
    ])->assertSuccessful();
});

test('prescription relation managers for Orphans and Widows render successfully', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(\App\Filament\Resources\Orphans\RelationManagers\PrescriptionsRelationManager::class, [
        'ownerRecord' => $this->orphan,
        'pageClass' => \App\Filament\Resources\Orphans\Pages\ViewOrphan::class,
    ])->assertSuccessful();

    Livewire::test(\App\Filament\Resources\Widows\RelationManagers\PrescriptionsRelationManager::class, [
        'ownerRecord' => $this->widow,
        'pageClass' => \App\Filament\Resources\Widows\Pages\ViewWidow::class,
    ])->assertSuccessful();
});

test('widow loan relation managers (Schedules, Repayments) render successfully', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(\App\Filament\Resources\WidowLoans\RelationManagers\SchedulesRelationManager::class, [
        'ownerRecord' => $this->loan,
        'pageClass' => \App\Filament\Resources\WidowLoans\Pages\ViewWidowLoan::class,
    ])->assertSuccessful();

    Livewire::test(\App\Filament\Resources\WidowLoans\RelationManagers\RepaymentsRelationManager::class, [
        'ownerRecord' => $this->loan,
        'pageClass' => \App\Filament\Resources\WidowLoans\Pages\ViewWidowLoan::class,
    ])->assertSuccessful();
});

test('project relation managers (Beneficiaries, Media, Milestones) render successfully', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(\App\Filament\Resources\Projects\RelationManagers\BeneficiariesRelationManager::class, [
        'ownerRecord' => $this->project,
        'pageClass' => \App\Filament\Resources\Projects\Pages\EditProject::class,
    ])->assertSuccessful();

    Livewire::test(\App\Filament\Resources\Projects\RelationManagers\MediaRelationManager::class, [
        'ownerRecord' => $this->project,
        'pageClass' => \App\Filament\Resources\Projects\Pages\EditProject::class,
    ])->assertSuccessful();

    Livewire::test(\App\Filament\Resources\Projects\RelationManagers\MilestonesRelationManager::class, [
        'ownerRecord' => $this->project,
        'pageClass' => \App\Filament\Resources\Projects\Pages\EditProject::class,
    ])->assertSuccessful();
});

test('education fee invoice payments relation manager renders successfully', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(\App\Filament\Resources\EducationFeeInvoices\RelationManagers\PaymentsRelationManager::class, [
        'ownerRecord' => $this->invoice,
        'pageClass' => \App\Filament\Resources\EducationFeeInvoices\Pages\EditEducationFeeInvoice::class,
    ])->assertSuccessful();
});

test('bank account transactions relation manager renders successfully', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(\App\Filament\Resources\BankAccounts\RelationManagers\TransactionsRelationManager::class, [
        'ownerRecord' => $this->bankAccount,
        'pageClass' => \App\Filament\Resources\BankAccounts\Pages\EditBankAccount::class,
    ])->assertSuccessful();
});

test('zone relation managers (Deceased, CoordinatorHistory) render successfully', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(\App\Filament\Resources\Zones\RelationManagers\DeceasedRelationManager::class, [
        'ownerRecord' => $this->zone,
        'pageClass' => \App\Filament\Resources\Zones\Pages\EditZone::class,
    ])->assertSuccessful();

    Livewire::test(\App\Filament\Resources\Zones\RelationManagers\CoordinatorHistoryRelationManager::class, [
        'ownerRecord' => $this->zone,
        'pageClass' => \App\Filament\Resources\Zones\Pages\EditZone::class,
    ])->assertSuccessful();
});
