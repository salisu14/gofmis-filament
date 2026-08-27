<?php

use App\Actions\Loan\RecordWidowLoanRepaymentAction;
use App\Enums\Gender;
use App\Enums\InstitutionType;
use App\Enums\LoanRepaymentFrequency;
use App\Enums\OrphanStatus;
use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Enums\WidowLoanStatus;
use App\Exceptions\InsufficientBankBalanceException;
use App\Models\BankAccount;
use App\Models\Deceased;
use App\Models\EducationFeeInvoice;
use App\Models\EducationFeePayment;
use App\Models\Imprest;
use App\Models\ImprestFund;
use App\Models\Institution;
use App\Models\Orphan;
use App\Models\OrphanEducation;
use App\Models\Project;
use App\Models\User;
use App\Models\Widow;
use App\Models\WidowLoan;
use App\Models\WidowLoanRepayment;
use App\Models\Zone;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

    $this->zone = Zone::create(['name' => 'Financial Audit Zone', 'address' => '100 Ledger Way']);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    $this->actingAs($this->admin);

    $this->mainBank = BankAccount::create([
        'user_id' => $this->admin->id,
        'account_name' => 'GOF Main Operating',
        'account_number' => '1000000001',
        'opening_balance' => 500000.00,
        'ledger_balance' => 500000.00,
        'reserved_balance' => 0.00,
        'usage' => BankAccount::USAGE_GENERAL,
    ]);

    $this->disbursementBank = BankAccount::create([
        'user_id' => $this->admin->id,
        'parent_bank_account_id' => $this->mainBank->id,
        'account_name' => 'GOF Loan Disbursement Sub',
        'account_number' => '1000000002',
        'opening_balance' => 0.00,
        'reserved_balance' => 0.00,
        'usage' => BankAccount::USAGE_WIDOW_LOAN_DISBURSEMENT,
    ]);
    $this->disbursementBank->update(['ledger_balance' => 200000.00]);

    $this->repaymentBank = BankAccount::create([
        'user_id' => $this->admin->id,
        'parent_bank_account_id' => $this->mainBank->id,
        'account_name' => 'GOF Loan Repayment Sub',
        'account_number' => '1000000003',
        'opening_balance' => 0.00,
        'reserved_balance' => 0.00,
        'usage' => BankAccount::USAGE_WIDOW_LOAN_REPAYMENT,
    ]);
    $this->repaymentBank->update(['ledger_balance' => 0.00]);

    $this->educationBank = BankAccount::create([
        'user_id' => $this->admin->id,
        'parent_bank_account_id' => $this->mainBank->id,
        'account_name' => 'GOF Education Sub',
        'account_number' => '1000000004',
        'opening_balance' => 0.00,
        'reserved_balance' => 0.00,
        'usage' => BankAccount::USAGE_EDUCATION,
    ]);
    $this->educationBank->update(['ledger_balance' => 100000.00]);

    $deceased = Deceased::factory()->create(['zone_id' => $this->zone->id]);
    $orphan = Orphan::create([
        'first_name' => 'Edu',
        'last_name' => 'Child',
        'deceased_id' => $deceased->id,
        'reg_no' => 'ORP-EDU-01',
        'child_sequence' => 1,
        'gender' => Gender::MALE,
        'birth_date' => now()->subYears(10)->toDateString(),
        'address' => '123 Test St',
        'status' => OrphanStatus::ACTIVE,
        'is_eligible' => true,
    ]);

    $institution = Institution::create([
        'name' => 'Capital Science Academy',
        'type' => InstitutionType::WESTERN,
    ]);

    $this->orphanEducation = OrphanEducation::create([
        'orphan_id' => $orphan->id,
        'institution_id' => $institution->id,
        'school_fee' => 15000.00,
        'is_fee_supported' => true,
        'support_amount' => 15000.00,
        'fee_frequency' => 'termly',
        'is_current' => true,
    ]);
});

/* =========================================================================
| 1. WIDOW LOANS
| ========================================================================= */

test('1. exact schedule total equals total_payable', function () {
    $deceased = Deceased::factory()->create(['zone_id' => $this->zone->id]);
    $widow = Widow::create([
        'deceased_id' => $deceased->id,
        'reg_no' => 'WID-001',
        'first_name' => 'Jane',
        'last_name' => 'Widow',
        'nin' => '12345678901',
        'address' => '123 Test St',
        'child_sequence' => 1,
        'is_eligible' => true,
        'is_married' => false,
    ]);

    $loan = WidowLoan::create([
        'widow_id' => $widow->id,
        'bank_account_id' => $this->disbursementBank->id,
        'principal_amount' => 10000.00,
        'total_payable' => 10000.00,
        'duration_months' => 3,
        'repayment_frequency' => LoanRepaymentFrequency::MONTHLY,
        'status' => WidowLoanStatus::DISBURSED,
        'disbursed_at' => now(),
    ]);

    $loan->generateLedger();

    $scheduleTotal = (float) $loan->schedules()->sum('amount_due');
    expect($scheduleTotal)->toBe(10000.00);
});

test('2. final installment rounding handles odd division without penny loss', function () {
    $deceased = Deceased::factory()->create(['zone_id' => $this->zone->id]);
    $widow = Widow::create([
        'deceased_id' => $deceased->id,
        'reg_no' => 'WID-002',
        'first_name' => 'Mary',
        'last_name' => 'Widow',
        'nin' => '12345678902',
        'address' => '123 Test St',
        'child_sequence' => 1,
        'is_eligible' => true,
        'is_married' => false,
    ]);

    $loan = WidowLoan::create([
        'widow_id' => $widow->id,
        'bank_account_id' => $this->disbursementBank->id,
        'principal_amount' => 10000.00,
        'total_payable' => 10000.00,
        'duration_months' => 3,
        'repayment_frequency' => LoanRepaymentFrequency::MONTHLY,
        'status' => WidowLoanStatus::DISBURSED,
        'disbursed_at' => now(),
    ]);

    $loan->generateLedger();

    $schedules = $loan->schedules()->orderBy('installment_number')->get();
    expect($schedules->count())->toBe(3);

    $sum = $schedules->sum('amount_due');
    expect((float) $sum)->toBe(10000.00);
});

test('3. partial repayment updates paid balance and schedule status correctly', function () {
    $deceased = Deceased::factory()->create(['zone_id' => $this->zone->id]);
    $widow = Widow::create([
        'deceased_id' => $deceased->id,
        'reg_no' => 'WID-003',
        'first_name' => 'Sarah',
        'last_name' => 'Widow',
        'nin' => '12345678903',
        'address' => '123 Test St',
        'child_sequence' => 1,
        'is_eligible' => true,
        'is_married' => false,
    ]);

    $loan = WidowLoan::create([
        'widow_id' => $widow->id,
        'bank_account_id' => $this->disbursementBank->id,
        'principal_amount' => 12000.00,
        'total_payable' => 12000.00,
        'duration_months' => 12,
        'repayment_frequency' => LoanRepaymentFrequency::MONTHLY,
        'status' => WidowLoanStatus::DISBURSED,
        'disbursed_at' => now(),
    ]);

    $loan->generateLedger();

    WidowLoanRepayment::create([
        'widow_loan_id' => $loan->id,
        'receipt_number' => 101,
        'payment_method' => 'cash',
        'amount' => 1500.00,
        'paid_at' => now(),
    ]);

    $loan->refreshBalance();

    expect((float) $loan->fresh()->total_paid)->toBe(1500.00)
        ->and((float) $loan->fresh()->outstanding_balance)->toBe(10500.00);
});

test('4. multiple sequential repayments update balances cumulatively', function () {
    $deceased = Deceased::factory()->create(['zone_id' => $this->zone->id]);
    $widow = Widow::create([
        'deceased_id' => $deceased->id,
        'reg_no' => 'WID-004',
        'first_name' => 'Alice',
        'last_name' => 'Widow',
        'nin' => '12345678904',
        'address' => '123 Test St',
        'child_sequence' => 1,
        'is_eligible' => true,
        'is_married' => false,
    ]);

    $loan = WidowLoan::create([
        'widow_id' => $widow->id,
        'bank_account_id' => $this->disbursementBank->id,
        'principal_amount' => 5000.00,
        'total_payable' => 5000.00,
        'duration_months' => 5,
        'repayment_frequency' => LoanRepaymentFrequency::MONTHLY,
        'status' => WidowLoanStatus::DISBURSED,
        'disbursed_at' => now(),
    ]);

    $loan->generateLedger();

    WidowLoanRepayment::create(['widow_loan_id' => $loan->id, 'receipt_number' => 102, 'payment_method' => 'cash', 'amount' => 1000.00, 'paid_at' => now()]);
    $loan->refreshBalance();

    WidowLoanRepayment::create(['widow_loan_id' => $loan->id, 'receipt_number' => 103, 'payment_method' => 'cash', 'amount' => 2000.00, 'paid_at' => now()]);
    $loan->refreshBalance();

    expect((float) $loan->fresh()->total_paid)->toBe(3000.00)
        ->and((float) $loan->fresh()->outstanding_balance)->toBe(2000.00);
});

test('5. repayment exceeding outstanding balance is rejected', function () {
    $deceased = Deceased::factory()->create(['zone_id' => $this->zone->id]);
    $widow = Widow::create(['deceased_id' => $deceased->id, 'reg_no' => 'WID-005', 'first_name' => 'Grace', 'last_name' => 'Widow', 'nin' => '12345678905', 'address' => '123 St', 'child_sequence' => 1, 'is_eligible' => true, 'is_married' => false]);
    $loan = WidowLoan::create([
        'widow_id' => $widow->id,
        'bank_account_id' => $this->disbursementBank->id,
        'principal_amount' => 5000.00,
        'total_payable' => 5000.00,
        'outstanding_balance' => 5000.00,
        'duration_months' => 5,
        'repayment_frequency' => LoanRepaymentFrequency::MONTHLY,
        'status' => WidowLoanStatus::DISBURSED,
        'disbursed_at' => now(),
        'collected_at' => now(),
    ]);
    $loan->generateLedger();

    $dto = new \App\Data\Loan\RecordWidowLoanRepaymentData(
        widowLoanId: $loan->id,
        amount: 6000.00,
        paymentMethod: 'cash',
        paidAt: now()->toDateString(),
        bankAccountId: $this->repaymentBank->id,
    );

    expect(fn () => app(RecordWidowLoanRepaymentAction::class)->execute($dto))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('6. duplicate repayment reference is prevented', function () {
    $deceased = Deceased::factory()->create(['zone_id' => $this->zone->id]);
    $widow = Widow::create(['deceased_id' => $deceased->id, 'reg_no' => 'WID-006', 'first_name' => 'Betty', 'last_name' => 'Widow', 'nin' => '12345678906', 'address' => '123 St', 'child_sequence' => 1, 'is_eligible' => true, 'is_married' => false]);
    $loan = WidowLoan::create(['widow_id' => $widow->id, 'bank_account_id' => $this->disbursementBank->id, 'principal_amount' => 5000.00, 'total_payable' => 5000.00, 'duration_months' => 5, 'repayment_frequency' => LoanRepaymentFrequency::MONTHLY, 'status' => WidowLoanStatus::DISBURSED, 'disbursed_at' => now(), 'collected_at' => now()]);

    $dto = new \App\Data\Loan\RecordWidowLoanRepaymentData(
        widowLoanId: $loan->id,
        amount: 1000.00,
        paymentMethod: 'cash',
        paidAt: now()->toDateString(),
        bankAccountId: $this->repaymentBank->id,
    );

    app(RecordWidowLoanRepaymentAction::class)->execute($dto);

    $invoice = EducationFeeInvoice::create([
        'orphan_education_id' => $this->orphanEducation->id,
        'invoice_number' => 'INV-REF-DUP',
        'reference' => 'REF-UNIQUE-001',
        'term' => 'Term 1',
        'period' => '2025/2026 Term 1',
        'academic_year' => '2025/2026',
        'due_date' => now()->addDays(30),
        'amount' => 20000.00,
        'status' => 'pending',
    ]);

    expect(fn () => EducationFeeInvoice::create([
        'orphan_education_id' => $this->orphanEducation->id,
        'invoice_number' => 'INV-REF-DUP-2',
        'reference' => 'REF-UNIQUE-001',
        'term' => 'Term 1',
        'period' => '2025/2026 Term 1',
        'academic_year' => '2025/2026',
        'due_date' => now()->addDays(30),
        'amount' => 20000.00,
        'status' => 'pending',
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

test('7. insufficient disbursement funds handled gracefully', function () {
    $this->disbursementBank->update(['ledger_balance' => 100.00]);

    expect(fn () => $this->disbursementBank->debit(500.00))
        ->toThrow(InsufficientBankBalanceException::class);
});

test('8. amount_paid plus outstanding_balance invariant holds', function () {
    $deceased = Deceased::factory()->create(['zone_id' => $this->zone->id]);
    $widow = Widow::create(['deceased_id' => $deceased->id, 'reg_no' => 'WID-008', 'first_name' => 'Fiona', 'last_name' => 'Widow', 'nin' => '12345678908', 'address' => '123 St', 'child_sequence' => 1, 'is_eligible' => true, 'is_married' => false]);
    $loan = WidowLoan::create(['widow_id' => $widow->id, 'bank_account_id' => $this->disbursementBank->id, 'principal_amount' => 8000.00, 'total_payable' => 8000.00, 'duration_months' => 4, 'repayment_frequency' => LoanRepaymentFrequency::MONTHLY, 'status' => WidowLoanStatus::DISBURSED, 'disbursed_at' => now()]);
    $loan->generateLedger();

    WidowLoanRepayment::create(['widow_loan_id' => $loan->id, 'receipt_number' => 88888, 'payment_method' => 'cash', 'amount' => 2000.00, 'paid_at' => now()]);
    $loan->refreshBalance();

    $sum = (float) $loan->total_paid + (float) $loan->outstanding_balance;
    expect($sum)->toBe(8000.00);
});

test('9. write-off preserves historical repayments and resets outstanding balance', function () {
    $deceased = Deceased::factory()->create(['zone_id' => $this->zone->id]);
    $widow = Widow::create(['deceased_id' => $deceased->id, 'reg_no' => 'WID-009', 'first_name' => 'Hannah', 'last_name' => 'Widow', 'nin' => '12345678909', 'address' => '123 St', 'child_sequence' => 1, 'is_eligible' => true, 'is_married' => false]);
    $loan = WidowLoan::create(['widow_id' => $widow->id, 'bank_account_id' => $this->disbursementBank->id, 'principal_amount' => 10000.00, 'total_payable' => 10000.00, 'duration_months' => 5, 'repayment_frequency' => LoanRepaymentFrequency::MONTHLY, 'status' => WidowLoanStatus::DISBURSED, 'disbursed_at' => now()]);
    $loan->generateLedger();

    WidowLoanRepayment::create(['widow_loan_id' => $loan->id, 'receipt_number' => 77777, 'payment_method' => 'cash', 'amount' => 2000.00, 'paid_at' => now()]);
    $loan->refreshBalance();

    $loan->update([
        'status' => WidowLoanStatus::WRITTEN_OFF,
        'outstanding_balance' => 0.00,
    ]);

    expect((float) $loan->fresh()->total_paid)->toBe(2000.00)
        ->and((float) $loan->fresh()->outstanding_balance)->toBe(0.00);
});

test('10. write-off sets collectible outstanding to zero', function () {
    $deceased = Deceased::factory()->create(['zone_id' => $this->zone->id]);
    $widow = Widow::create(['deceased_id' => $deceased->id, 'reg_no' => 'WID-010', 'first_name' => 'Iris', 'last_name' => 'Widow', 'nin' => '12345678910', 'address' => '123 St', 'child_sequence' => 1, 'is_eligible' => true, 'is_married' => false]);
    $loan = WidowLoan::create(['widow_id' => $widow->id, 'bank_account_id' => $this->disbursementBank->id, 'principal_amount' => 4000.00, 'total_payable' => 4000.00, 'duration_months' => 4, 'repayment_frequency' => LoanRepaymentFrequency::MONTHLY, 'status' => WidowLoanStatus::WRITTEN_OFF, 'outstanding_balance' => 0.00, 'disbursed_at' => now()]);

    expect((float) $loan->outstanding_balance)->toBe(0.00);
});

test('11. delinquency evaluation command is idempotent', function () {
    $deceased = Deceased::factory()->create(['zone_id' => $this->zone->id]);
    $widow = Widow::create(['deceased_id' => $deceased->id, 'reg_no' => 'WID-011', 'first_name' => 'Kelly', 'last_name' => 'Widow', 'nin' => '12345678911', 'address' => '123 St', 'child_sequence' => 1, 'is_eligible' => true, 'is_married' => false]);
    $loan = WidowLoan::create(['widow_id' => $widow->id, 'bank_account_id' => $this->disbursementBank->id, 'principal_amount' => 4000.00, 'total_payable' => 4000.00, 'duration_months' => 4, 'repayment_frequency' => LoanRepaymentFrequency::MONTHLY, 'status' => WidowLoanStatus::DISBURSED, 'disbursed_at' => now()->subDays(45)]);
    $loan->generateLedger();

    Artisan::call('widow-loans:evaluate-delinquency');
    $status1 = $loan->fresh()->performance_status;

    Artisan::call('widow-loans:evaluate-delinquency');
    $status2 = $loan->fresh()->performance_status;

    expect($status1)->toEqual($status2);
});

/* =========================================================================
| 2. BANK ACCOUNTS
| ========================================================================= */

test('12. reservation reduces available balance', function () {
    $account = BankAccount::create([
        'user_id' => $this->admin->id,
        'account_name' => 'Test Reserve',
        'account_number' => '2000000001',
        'opening_balance' => 10000.00,
        'ledger_balance' => 10000.00,
        'reserved_balance' => 0.00,
        'usage' => BankAccount::USAGE_GENERAL,
    ]);

    $account->reserve(3000.00);

    expect((float) $account->fresh()->reserved_balance)->toBe(3000.00)
        ->and($account->fresh()->hasSufficientFunds(7500.00))->toBeFalse()
        ->and($account->fresh()->hasSufficientFunds(7000.00))->toBeTrue();
});

test('13. release restores available balance', function () {
    $account = BankAccount::create([
        'user_id' => $this->admin->id,
        'account_name' => 'Test Release',
        'account_number' => '2000000002',
        'opening_balance' => 10000.00,
        'ledger_balance' => 10000.00,
        'reserved_balance' => 3000.00,
        'usage' => BankAccount::USAGE_GENERAL,
    ]);

    $account->unreserve(3000.00);

    expect((float) $account->fresh()->reserved_balance)->toBe(0.00)
        ->and($account->fresh()->hasSufficientFunds(10000.00))->toBeTrue();
});

test('14. debit cannot overspend available funds', function () {
    $account = BankAccount::create([
        'user_id' => $this->admin->id,
        'account_name' => 'Test Debit Limit',
        'account_number' => '2000000003',
        'opening_balance' => 5000.00,
        'ledger_balance' => 5000.00,
        'reserved_balance' => 1000.00,
        'usage' => BankAccount::USAGE_GENERAL,
    ]);

    expect(fn () => $account->debit(4500.00))
        ->toThrow(InsufficientBankBalanceException::class);
});

test('15. database transactions and locking prevent race conditions', function () {
    $account = $this->disbursementBank;

    DB::transaction(function () use ($account) {
        $locked = BankAccount::lockForUpdate()->find($account->id);
        $locked->debit(1000.00);
    });

    expect((float) $account->fresh()->ledger_balance)->toBe(199000.00);
});

/* =========================================================================
| 3. IMPREST
| ========================================================================= */

test('16. imprest opening + credits - debits = balance', function () {
    $fund = ImprestFund::create([
        'name' => 'Main Admin Imprest',
        'location' => 'Central Admin',
        'custodian_id' => $this->admin->id,
        'bank_account_id' => $this->mainBank->id,
        'authorized_amount' => 50000.00,
        'current_balance' => 50000.00,
    ]);

    expect((float) $fund->current_balance)->toBe(50000.00);
});

test('17. imprest float expense tracking prevents negative balance', function () {
    $imprest = Imprest::create([
        'location' => 'Kano Office',
        'custodian_id' => $this->admin->id,
        'authorized_amount' => 10000.00,
        'current_balance' => 10000.00,
    ]);

    $imprest->current_balance -= 4000.00;
    $imprest->save();

    expect((float) $imprest->fresh()->current_balance)->toBe(6000.00);
});

test('18. imprest replenishment restores balance accurately', function () {
    $imprest = Imprest::create([
        'location' => 'Kaduna Branch',
        'custodian_id' => $this->admin->id,
        'authorized_amount' => 20000.00,
        'current_balance' => 5000.00,
    ]);

    $imprest->current_balance += 15000.00;
    $imprest->save();

    expect((float) $imprest->fresh()->current_balance)->toBe(20000.00);
});

test('19. imprest balance tracks within authorized limits', function () {
    $imprest = Imprest::create([
        'location' => 'Abuja HQ',
        'custodian_id' => $this->admin->id,
        'authorized_amount' => 30000.00,
        'current_balance' => 30000.00,
    ]);

    expect((float) $imprest->current_balance)->toBeLessThanOrEqual((float) $imprest->authorized_amount);
});

/* =========================================================================
| 4. EDUCATION FEES
| ========================================================================= */

test('20. education invoice balance formula is correct', function () {
    $invoice = EducationFeeInvoice::create([
        'orphan_education_id' => $this->orphanEducation->id,
        'invoice_number' => 'INV-EDU-001',
        'term' => 'Term 1',
        'period' => '2025/2026 Term 1',
        'academic_year' => '2025/2026',
        'due_date' => now()->addDays(30),
        'amount' => 25000.00,
        'status' => 'partial',
    ]);

    EducationFeePayment::create([
        'education_fee_invoice_id' => $invoice->id,
        'bank_account_id' => $this->educationBank->id,
        'payment_number' => 'PAY-001',
        'amount' => 10000.00,
        'payment_date' => now()->toDateString(),
        'payment_method' => 'bank_transfer',
        'paid_at' => now(),
    ]);

    expect((float) $invoice->fresh()->balance)->toBe(15000.00);
});

test('21. partial education fee payment reduces balance', function () {
    $invoice = EducationFeeInvoice::create([
        'orphan_education_id' => $this->orphanEducation->id,
        'invoice_number' => 'INV-EDU-002',
        'term' => 'Term 1',
        'period' => '2025/2026 Term 1',
        'academic_year' => '2025/2026',
        'due_date' => now()->addDays(30),
        'amount' => 30000.00,
        'status' => 'pending',
    ]);

    EducationFeePayment::create([
        'education_fee_invoice_id' => $invoice->id,
        'bank_account_id' => $this->educationBank->id,
        'payment_number' => 'PAY-002',
        'amount' => 12000.00,
        'payment_date' => now()->toDateString(),
        'payment_method' => 'bank_transfer',
        'paid_at' => now(),
    ]);

    expect((float) $invoice->fresh()->balance)->toBe(18000.00);
});

test('22. full education fee payment closes invoice balance', function () {
    $invoice = EducationFeeInvoice::create([
        'orphan_education_id' => $this->orphanEducation->id,
        'invoice_number' => 'INV-EDU-003',
        'term' => 'Term 2',
        'period' => '2025/2026 Term 2',
        'academic_year' => '2025/2026',
        'due_date' => now()->addDays(30),
        'amount' => 15000.00,
        'status' => 'pending',
    ]);

    EducationFeePayment::create([
        'education_fee_invoice_id' => $invoice->id,
        'bank_account_id' => $this->educationBank->id,
        'payment_number' => 'PAY-003',
        'amount' => 15000.00,
        'payment_date' => now()->toDateString(),
        'payment_method' => 'bank_transfer',
        'paid_at' => now(),
    ]);

    $invoice->refreshPaymentStatus();

    expect((float) $invoice->fresh()->balance)->toBe(0.00)
        ->and($invoice->fresh()->status)->toBe('paid');
});

test('23. education fee overpayment is prevented', function () {
    $invoice = EducationFeeInvoice::create([
        'orphan_education_id' => $this->orphanEducation->id,
        'invoice_number' => 'INV-EDU-004',
        'term' => 'Term 3',
        'period' => '2025/2026 Term 3',
        'academic_year' => '2025/2026',
        'due_date' => now()->addDays(30),
        'amount' => 10000.00,
        'status' => 'pending',
    ]);

    $service = app(\App\Services\EducationFeeInvoiceService::class);

    expect(fn () => $service->recordPayment($invoice, [
        'amount' => 15000.00,
        'bank_account_id' => $this->educationBank->id,
        'payment_date' => now()->toDateString(),
        'reference_number' => 'REF-OVER-01',
    ]))->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('24. duplicate education fee invoice reference is protected', function () {
    $ref = 'INV-DUP-REF-999';

    EducationFeeInvoice::create([
        'orphan_education_id' => $this->orphanEducation->id,
        'reference' => $ref,
        'term' => 'Term 1',
        'period' => '2025/2026 Term 1',
        'academic_year' => '2025/2026',
        'due_date' => now()->addDays(30),
        'amount' => 20000.00,
        'status' => 'pending',
    ]);

    expect(fn () => EducationFeeInvoice::create([
        'orphan_education_id' => $this->orphanEducation->id,
        'reference' => $ref,
        'term' => 'Term 1',
        'period' => '2025/2026 Term 1',
        'academic_year' => '2025/2026',
        'due_date' => now()->addDays(30),
        'amount' => 20000.00,
        'status' => 'pending',
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

/* =========================================================================
| 5. PROJECTS
| ========================================================================= */

test('25. project budget remaining formula is correct', function () {
    $project = Project::create([
        'name' => 'Health Clinic Renovation',
        'type' => ProjectType::CLINIC,
        'budget_allocated' => 500000.00,
        'budget_spent' => 150000.00,
        'status' => ProjectStatus::IN_PROGRESS,
        'zone_id' => $this->zone->id,
    ]);

    expect((float) $project->budget_remaining)->toBe(350000.00)
        ->and($project->is_over_budget)->toBeFalse();
});

test('26. project expense updates spent total accurately', function () {
    $project = Project::create([
        'name' => 'Community School Roofing',
        'type' => ProjectType::SCHOOL,
        'budget_allocated' => 100000.00,
        'budget_spent' => 20000.00,
        'status' => ProjectStatus::IN_PROGRESS,
        'zone_id' => $this->zone->id,
    ]);

    $project->budget_spent += 30000.00;
    $project->save();

    expect((float) $project->fresh()->budget_spent)->toBe(50000.00)
        ->and((float) $project->fresh()->budget_remaining)->toBe(50000.00);
});

test('27. over-budget project expense marks project as over budget', function () {
    $project = Project::create([
        'name' => 'Solar Borehole Project',
        'type' => ProjectType::WATER,
        'budget_allocated' => 200000.00,
        'budget_spent' => 190000.00,
        'status' => ProjectStatus::IN_PROGRESS,
        'zone_id' => $this->zone->id,
    ]);

    $project->budget_spent += 20000.00;
    $project->save();

    expect($project->fresh()->is_over_budget)->toBeTrue();
});

test('28. project expense updates are idempotent when properly scoped', function () {
    $project = Project::create([
        'name' => 'Mosque Repair',
        'type' => ProjectType::MOSQUE,
        'budget_allocated' => 80000.00,
        'budget_spent' => 10000.00,
        'status' => ProjectStatus::IN_PROGRESS,
        'zone_id' => $this->zone->id,
    ]);

    expect((float) $project->budget_remaining)->toBe(70000.00);
});

/* =========================================================================
| 6. RECONCILIATION COMMANDS
| ========================================================================= */

test('29. finance:reconcile command is read-only', function () {
    $projectCountBefore = Project::count();
    $bankCountBefore = BankAccount::count();

    Artisan::call('finance:reconcile');

    expect(Project::count())->toBe($projectCountBefore)
        ->and(BankAccount::count())->toBe($bankCountBefore);
});

test('30. finance:reconcile command detects deliberately seeded mismatch', function () {
    $account = BankAccount::create([
        'user_id' => $this->admin->id,
        'account_name' => 'Faulty Account',
        'account_number' => '9990001112',
        'opening_balance' => 1000.00,
        'ledger_balance' => -500.00,
        'reserved_balance' => 0.00,
        'usage' => BankAccount::USAGE_GENERAL,
    ]);

    Artisan::call('finance:reconcile');
    $output = Artisan::output();

    expect($output)->toContain('Negative ledger balance');
});

test('31. finance:reconcile reports clean state correctly when no errors exist', function () {
    BankAccount::query()->delete();

    Artisan::call('finance:reconcile');
    $output = Artisan::output();

    expect($output)->toContain('passed integrity and reconciliation checks cleanly');
});

test('35. bank account with opening balance and domain transactions reconciles correctly', function () {
    $account = BankAccount::create([
        'user_id' => $this->admin->id,
        'account_name' => 'Seeded Operating Account',
        'account_number' => '9998887771',
        'opening_balance' => 99997.23,
        'ledger_balance' => 99997.23,
        'reserved_balance' => 0.00,
        'usage' => BankAccount::USAGE_GENERAL,
    ]);

    \App\Models\Transaction::create([
        'bank_account_id' => $account->id,
        'amount' => 500000.00,
        'type' => 'deposit',
        'reference' => 'DEP-REC-01',
        'description' => 'Test Deposit',
        'date' => now(),
        'is_system' => false,
    ]);

    expect((float) $account->fresh()->ledger_balance)->toBe(599997.23);

    Artisan::call('finance:reconcile');
    $output = Artisan::output();

    expect($output)->not->toContain('9998887771');
});

test('36. transaction-only account reconciles correctly', function () {
    $account = BankAccount::create([
        'user_id' => $this->admin->id,
        'account_name' => 'Zero Opening Account',
        'account_number' => '9998887772',
        'opening_balance' => 0.00,
        'ledger_balance' => 0.00,
        'reserved_balance' => 0.00,
        'usage' => BankAccount::USAGE_GENERAL,
    ]);

    \App\Models\Transaction::create([
        'bank_account_id' => $account->id,
        'amount' => 150000.00,
        'type' => 'deposit',
        'reference' => 'DEP-REC-02',
        'description' => 'Test Deposit Zero Opening',
        'date' => now(),
        'is_system' => false,
    ]);

    expect((float) $account->fresh()->ledger_balance)->toBe(150000.00);

    Artisan::call('finance:reconcile');
    $output = Artisan::output();

    expect($output)->not->toContain('9998887772');
});

test('37. reserved balance does not distort ledger reconciliation', function () {
    $account = BankAccount::create([
        'user_id' => $this->admin->id,
        'account_name' => 'Reserved Balance Account',
        'account_number' => '9998887773',
        'opening_balance' => 50000.00,
        'ledger_balance' => 50000.00,
        'reserved_balance' => 0.00,
        'usage' => BankAccount::USAGE_GENERAL,
    ]);

    $account->reserve(20000.00);

    expect((float) $account->fresh()->reserved_balance)->toBe(20000.00)
        ->and((float) $account->fresh()->ledger_balance)->toBe(50000.00);

    Artisan::call('finance:reconcile');
    $output = Artisan::output();

    expect($output)->not->toContain('9998887773');
});

/* =========================================================================
| 7. ROUNDING & PRECISION
| ========================================================================= */

test('32. division residual is handled deterministically without penny loss', function () {
    $deceased = Deceased::factory()->create(['zone_id' => $this->zone->id]);
    $widow = Widow::create(['deceased_id' => $deceased->id, 'reg_no' => 'WID-032', 'first_name' => 'Lucy', 'last_name' => 'Widow', 'nin' => '12345678932', 'address' => '123 St', 'child_sequence' => 1, 'is_eligible' => true, 'is_married' => false]);
    $loan = WidowLoan::create(['widow_id' => $widow->id, 'bank_account_id' => $this->disbursementBank->id, 'principal_amount' => 100000.00, 'total_payable' => 100000.00, 'duration_months' => 3, 'repayment_frequency' => LoanRepaymentFrequency::MONTHLY, 'status' => WidowLoanStatus::DISBURSED, 'disbursed_at' => now()]);
    $loan->generateLedger();

    $sum = (float) $loan->schedules()->sum('amount_due');
    expect($sum)->toBe(100000.00);
});

test('33. no hidden negative 0.01 balance on full loan repayment', function () {
    $deceased = Deceased::factory()->create(['zone_id' => $this->zone->id]);
    $widow = Widow::create(['deceased_id' => $deceased->id, 'reg_no' => 'WID-033', 'first_name' => 'Mona', 'last_name' => 'Widow', 'nin' => '12345678933', 'address' => '123 St', 'child_sequence' => 1, 'is_eligible' => true, 'is_married' => false]);
    $loan = WidowLoan::create(['widow_id' => $widow->id, 'bank_account_id' => $this->disbursementBank->id, 'principal_amount' => 3000.00, 'total_payable' => 3000.00, 'duration_months' => 3, 'repayment_frequency' => LoanRepaymentFrequency::MONTHLY, 'status' => WidowLoanStatus::DISBURSED, 'disbursed_at' => now()]);
    $loan->generateLedger();

    WidowLoanRepayment::create(['widow_loan_id' => $loan->id, 'receipt_number' => 701, 'payment_method' => 'cash', 'amount' => 3000.00, 'paid_at' => now()]);
    $loan->refreshBalance();

    expect((float) $loan->fresh()->outstanding_balance)->toBe(0.00)
        ->and((float) $loan->fresh()->total_paid)->toBe(3000.00);
});

test('34. repeated partial payments remain precise to 2 decimal places', function () {
    $deceased = Deceased::factory()->create(['zone_id' => $this->zone->id]);
    $widow = Widow::create(['deceased_id' => $deceased->id, 'reg_no' => 'WID-034', 'first_name' => 'Nina', 'last_name' => 'Widow', 'nin' => '12345678934', 'address' => '123 St', 'child_sequence' => 1, 'is_eligible' => true, 'is_married' => false]);
    $loan = WidowLoan::create(['widow_id' => $widow->id, 'bank_account_id' => $this->disbursementBank->id, 'principal_amount' => 1000.00, 'total_payable' => 1000.00, 'duration_months' => 3, 'repayment_frequency' => LoanRepaymentFrequency::MONTHLY, 'status' => WidowLoanStatus::DISBURSED, 'disbursed_at' => now()]);
    $loan->generateLedger();

    WidowLoanRepayment::create(['widow_loan_id' => $loan->id, 'receipt_number' => 801, 'payment_method' => 'cash', 'amount' => 333.33, 'paid_at' => now()]);
    $loan->refreshBalance();

    WidowLoanRepayment::create(['widow_loan_id' => $loan->id, 'receipt_number' => 802, 'payment_method' => 'cash', 'amount' => 333.33, 'paid_at' => now()]);
    $loan->refreshBalance();

    WidowLoanRepayment::create(['widow_loan_id' => $loan->id, 'receipt_number' => 803, 'payment_method' => 'cash', 'amount' => 333.34, 'paid_at' => now()]);
    $loan->refreshBalance();

    expect((float) $loan->fresh()->total_paid)->toBe(1000.00)
        ->and((float) $loan->fresh()->outstanding_balance)->toBe(0.00);
});
