<?php

use App\Models\BankAccount;
use App\Models\Deceased;
use App\Models\EducationFeeInvoice;
use App\Models\Institution;
use App\Models\Orphan;
use App\Models\OrphanEducation;
use App\Models\User;
use App\Services\EducationFeeInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('calculates the orphan education balance without including voided or cancelled invoices', function () {
    // Create a User to associate with the bank accounts
    $user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);

    // 1. Setup a bank account for education payments
    $bank = BankAccount::create([
        'account_name' => 'Education Main Account',
        'account_number' => '1234567890',
        'opening_balance' => 500000,
        'ledger_balance' => 500000,
        'usage' => BankAccount::USAGE_GENERAL,
        'user_id' => $user->id,
    ]);

    $childBank = BankAccount::create([
        'account_name' => 'Education Sub Account',
        'account_number' => '1234567891',
        'opening_balance' => 0,
        'ledger_balance' => 0,
        'usage' => BankAccount::USAGE_EDUCATION,
        'parent_bank_account_id' => $bank->id,
        'user_id' => $user->id,
    ]);

    // Credit child bank account to perform operations
    $childBank->credit(100000);

    // 2. Setup Deceased and Orphan
    $deceased = Deceased::create([
        'first_name' => 'John',
        'last_name' => 'Doe',
        'nin' => '12345678901',
        'reg_no' => 'DEC-00001',
        'guardian_name' => 'Guardian Name',
        'guardian_phone' => '08012345678',
        'vulnerability_status' => \App\Enums\VulnerabilityStatus::A,
        'date_registered' => now(),
    ]);

    $orphan = Orphan::create([
        'first_name' => 'Baby',
        'last_name' => 'Doe',
        'gender' => \App\Enums\Gender::MALE,
        'birth_date' => now()->subYears(10)->toDateString(),
        'nin' => '98765432101',
        'reg_no' => 'REG-12345',
        'deceased_id' => $deceased->id,
        'address' => 'Test Address',
        'is_eligible' => true,
        'status' => 'active',
    ]);

    // 3. Setup Institution and OrphanEducation
    $institution = Institution::create([
        'name' => 'Test Academy',
        'type' => \App\Enums\InstitutionType::WESTERN,
    ]);

    $education = OrphanEducation::create([
        'orphan_id' => $orphan->id,
        'institution_id' => $institution->id,
        'school_fee' => 50000.00,
        'fee_frequency' => 'termly',
        'is_fee_supported' => true,
        'support_amount' => 50000.00,
        'is_current' => true,
    ]);

    // 4. Create two invoices (Invoice 1 & Invoice 2)
    $invoice1 = EducationFeeInvoice::create([
        'orphan_education_id' => $education->id,
        'amount' => 50000.00,
        'due_date' => now()->addDays(30),
        'period' => 'Term 1 2026',
        'status' => EducationFeeInvoice::STATUS_PENDING,
    ]);

    $invoice2 = EducationFeeInvoice::create([
        'orphan_education_id' => $education->id,
        'amount' => 50000.00,
        'due_date' => now()->addDays(90),
        'period' => 'Term 2 2026',
        'status' => EducationFeeInvoice::STATUS_PENDING,
    ]);

    // The total expected outstanding school fees should be ₦100,000 initially
    expect($education->fresh()->balance)->toEqual(100000.00);

    // 5. Pay Invoice 1
    $service = new EducationFeeInvoiceService();
    $service->recordPayment($invoice1, [
        'amount' => 50000.00,
        'bank_account_id' => $childBank->id,
        'payment_date' => now(),
        'payment_method' => 'transfer',
        'reference' => 'TXN-INV1',
    ]);

    expect($invoice1->fresh()->isPaid())->toBeTrue();
    expect($education->fresh()->balance)->toEqual(50000.00);

    // 6. Void Invoice 2
    $service->void($invoice2, 'Scholarship applied');

    expect($invoice2->fresh()->status)->toEqual(EducationFeeInvoice::STATUS_VOID);

    // 7. Verify the balance of $education.
    // If it's correct, it should be ₦0.00 (since Invoice 1 is PAID and Invoice 2 is VOID).
    // If the bug exists, the balance will be ₦50,000 (because Invoice 2's amount is still summed).
    expect($education->fresh()->balance)->toEqual(0.00);
});
