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

    $this->parentBankAccount = BankAccount::create([
        'user_id' => $this->admin->id,
        'account_name' => 'Rel Operating Account',
        'account_number' => '9991112224',
        'opening_balance' => 50000.00,
        'ledger_balance' => 50000.00,
        'reserved_balance' => 0.00,
        'usage' => BankAccount::USAGE_GENERAL,
    ]);

    $this->bankAccount = BankAccount::create([
        'user_id' => $this->admin->id,
        'account_name' => 'Rel Education Account',
        'account_number' => '9991112225',
        'parent_bank_account_id' => $this->parentBankAccount->id,
        'opening_balance' => 0.00,
        'ledger_balance' => 50000.00,
        'reserved_balance' => 0.00,
        'usage' => BankAccount::USAGE_EDUCATION,
    ]);
    $this->bankAccount->update(['ledger_balance' => 50000.00]);

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

test('deceased relation managers (Orphans, Widows) create action submission works', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    // Create Orphan via Deceased relation manager
    Livewire::test(\App\Filament\Resources\Deceased\RelationManagers\OrphansRelationManager::class, [
        'ownerRecord' => $this->deceased,
        'pageClass' => \App\Filament\Resources\Deceased\Pages\EditDeceased::class,
    ])
        ->callTableAction('create', data: [
            'first_name' => 'ChildOne',
            'last_name' => 'DeceasedChild',
            'gender' => \App\Enums\Gender::MALE->value,
            'birth_date' => '2018-06-15',
            'nin' => '11122233344',
            'address' => '123 Child St',
            'is_eligible' => true,
        ])
        ->assertHasNoTableActionErrors();

    expect(Orphan::where('first_name', 'ChildOne')->where('deceased_id', $this->deceased->id)->exists())->toBeTrue();

    // Create Widow via Deceased relation manager
    Livewire::test(\App\Filament\Resources\Deceased\RelationManagers\WidowsRelationManager::class, [
        'ownerRecord' => $this->deceased,
        'pageClass' => \App\Filament\Resources\Deceased\Pages\EditDeceased::class,
    ])
        ->callTableAction('create', data: [
            'first_name' => 'WidowOne',
            'last_name' => 'DeceasedWidow',
            'nin' => '99988877711',
            'address' => '456 St',
            'child_sequence' => 2,
            'is_eligible' => true,
            'is_married' => false,
        ])
        ->assertHasNoTableActionErrors();

    expect(Widow::where('first_name', 'WidowOne')->where('deceased_id', $this->deceased->id)->exists())->toBeTrue();
});

test('orphan and widow medical history action submission works', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $illness = \App\Models\Illness::create([
        'name' => 'Malaria Fever',
        'category' => \App\Enums\IllnessCategory::Infectious,
    ]);

    Livewire::test(\App\Filament\Resources\Orphans\RelationManagers\PrescriptionsRelationManager::class, [
        'ownerRecord' => $this->orphan,
        'pageClass' => \App\Filament\Resources\Orphans\Pages\EditOrphan::class,
    ])
        ->callTableAction('create', data: [
            'doctor_name' => 'Dr. Smith',
            'illness_id' => $illness->id,
            'prescription_date' => now()->toDateString(),
            'lab_test_cost' => 5000.00,
            'drug_cost' => 10000.00,
            'note' => 'Full recovery course',
        ])
        ->assertHasNoTableActionErrors();

    expect(\App\Models\Prescription::where('illness_id', $illness->id)->where('prescribable_id', $this->orphan->id)->exists())->toBeTrue();
});

test('education fee invoice payment relation manager record payment action works', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->bankAccount->ledger_balance = 50000.00;
    $this->bankAccount->saveQuietly();

    Livewire::test(\App\Filament\Resources\EducationFeeInvoices\RelationManagers\PaymentsRelationManager::class, [
        'ownerRecord' => $this->invoice,
        'pageClass' => \App\Filament\Resources\EducationFeeInvoices\Pages\EditEducationFeeInvoice::class,
    ])
        ->callTableAction('create', data: [
            'amount' => 5000.00,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'transfer',
            'bank_account_id' => $this->bankAccount->id,
        ])
        ->assertHasNoTableActionErrors();

    expect(\App\Models\EducationFeePayment::where('bank_account_id', $this->bankAccount->id)->exists())->toBeTrue();
});
