<?php

use App\Enums\WidowLoanScheduleStatus;
use App\Enums\WidowLoanStatus;
use App\Models\BankAccount;
use App\Models\Deceased;
use App\Models\User;
use App\Models\Widow;
use App\Models\WidowLoan;
use App\Models\WidowLoanSchedule;
use App\Models\WidowLoanWriteOff;
use App\Models\Zone;
use App\Services\WidowLoanWriteOffService;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    // Seed roles and permissions
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

    $this->superAdmin = User::factory()->create();
    $this->superAdmin->assignRole('super_admin');

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->nonAdmin = User::factory()->create();
    $this->nonAdmin->assignRole('coordinator');

    $this->zone = Zone::create(['name' => 'Test Zone']);

    $this->deceased = Deceased::factory()->create([
        'zone_id' => $this->zone->id,
    ]);

    $this->widow = Widow::create([
        'first_name' => 'Test',
        'last_name' => 'Widow',
        'nin' => '98765432101',
        'reg_no' => 'WID-11111',
        'is_eligible' => true,
        'is_married' => false,
        'deceased_id' => $this->deceased->id,
        'full_name' => 'Test Widow',
        'child_sequence' => 1,
    ]);

    $this->bankAccount = BankAccount::create([
        'account_name' => 'Widow Disb Account',
        'account_number' => '1234567890',
        'opening_balance' => 500000.00,
        'ledger_balance' => 500000.00,
        'user_id' => $this->superAdmin->id,
    ]);

    // Create a default loan in disbursed status
    $this->loan = WidowLoan::create([
        'widow_id' => $this->widow->id,
        'principal_amount' => 100000.00,
        'total_payable' => 100000.00,
        'outstanding_balance' => 100000.00,
        'total_paid' => 0.00,
        'status' => WidowLoanStatus::DISBURSED,
        'disbursed_at' => now(),
        'bank_account_id' => $this->bankAccount->id,
        'purpose' => 'Business',
    ]);

    // Create 4 schedule lines
    for ($i = 1; $i <= 4; $i++) {
        WidowLoanSchedule::create([
            'widow_loan_id' => $this->loan->id,
            'installment_number' => $i,
            'amount_due' => 25000.00,
            'due_date' => now()->addWeeks($i),
            'is_paid' => false,
            'status' => WidowLoanScheduleStatus::PENDING,
        ]);
    }
});

test('only super administrators can write off a loan', function () {
    $service = new WidowLoanWriteOffService;

    expect(fn () => $service->writeOff($this->loan, $this->nonAdmin, 'Hardship'))
        ->toThrow(\Exception::class, 'Unauthorized: Only super administrators can write off loans.');

    expect(fn () => $service->writeOff($this->loan, $this->admin, 'Hardship'))
        ->toThrow(\Exception::class, 'Unauthorized: Only super administrators can write off loans.');

    $result = $service->writeOff($this->loan, $this->superAdmin, 'Genuine medical emergency');
    expect($result->status)->toBe(WidowLoanStatus::WRITTEN_OFF);
});

test('write-off is rejected if loan is not in disbursed state or has zero outstanding balance', function () {
    $service = new WidowLoanWriteOffService;

    // Try a draft loan
    $draftLoan = WidowLoan::create([
        'widow_id' => $this->widow->id,
        'principal_amount' => 50000.00,
        'total_payable' => 50000.00,
        'outstanding_balance' => 50000.00,
        'status' => WidowLoanStatus::DRAFT,
        'bank_account_id' => $this->bankAccount->id,
        'purpose' => 'Business',
    ]);

    expect(fn () => $service->writeOff($draftLoan, $this->superAdmin, 'Hardship'))
        ->toThrow(\Exception::class, 'Only disbursed loans can be written off.');

    // Try a loan with zero outstanding balance
    $zeroBalanceLoan = WidowLoan::create([
        'widow_id' => $this->widow->id,
        'principal_amount' => 50000.00,
        'total_payable' => 50000.00,
        'outstanding_balance' => 0.00,
        'status' => WidowLoanStatus::DISBURSED,
        'disbursed_at' => now(),
        'bank_account_id' => $this->bankAccount->id,
        'purpose' => 'Business',
    ]);

    expect(fn () => $service->writeOff($zeroBalanceLoan, $this->superAdmin, 'Hardship'))
        ->toThrow(\Exception::class, 'This loan has no outstanding balance to write off.');
});

test('write-off successfully writes off outstanding balance, creates record, and leaves paid amount intact', function () {
    $service = new WidowLoanWriteOffService;

    // Mark 1st schedule as paid, reduce outstanding balance to simulate payments made
    $this->loan->schedules()->first()->update([
        'is_paid' => true,
        'status' => WidowLoanScheduleStatus::PAID,
    ]);
    $this->loan->update([
        'total_paid' => 25000.00,
        'outstanding_balance' => 75000.00,
    ]);

    $refreshedLoan = $service->writeOff(
        $this->loan,
        $this->superAdmin,
        'Hardship verified by zone coordinator',
        'Verification conducted via phone call and document review.',
        allowReapplication: true,
        documentPath: 'loan-write-offs/evidence.pdf'
    );

    expect($refreshedLoan->status)->toBe(WidowLoanStatus::WRITTEN_OFF);
    expect((float) $refreshedLoan->outstanding_balance)->toBe(0.00);
    expect((float) $refreshedLoan->total_paid)->toBe(25000.00);
    expect((float) $refreshedLoan->amount_written_off)->toBe(75000.00);
    expect($refreshedLoan->written_off_by)->toBe($this->superAdmin->id);

    // Assert write-off record exists
    $writeOff = WidowLoanWriteOff::where('widow_loan_id', $this->loan->id)->first();
    expect($writeOff)->not->toBeNull();
    expect((float) $writeOff->original_outstanding_balance)->toBe(75000.00);
    expect((float) $writeOff->amount_written_off)->toBe(75000.00);
    expect($writeOff->write_off_reason)->toBe('Hardship verified by zone coordinator');
    expect($writeOff->write_off_verification_notes)->toBe('Verification conducted via phone call and document review.');
    expect($writeOff->write_off_document_path)->toBe('loan-write-offs/evidence.pdf');
    expect($writeOff->authorized_by)->toBe($this->superAdmin->id);
});

test('write-off waives unpaid schedule lines but keeps paid schedule lines intact', function () {
    $service = new WidowLoanWriteOffService;

    // Mark 2 schedules as paid, 2 as unpaid
    $schedules = $this->loan->schedules()->orderBy('installment_number')->get();
    $schedules[0]->update(['is_paid' => true, 'status' => WidowLoanScheduleStatus::PAID]);
    $schedules[1]->update(['is_paid' => true, 'status' => WidowLoanScheduleStatus::PAID]);

    $this->loan->update([
        'total_paid' => 50000.00,
        'outstanding_balance' => 50000.00,
    ]);

    $service->writeOff($this->loan, $this->superAdmin, 'Hardship');

    $refreshedSchedules = $this->loan->schedules()->orderBy('installment_number')->get();
    expect($refreshedSchedules[0]->status)->toBe(WidowLoanScheduleStatus::PAID);
    expect($refreshedSchedules[1]->status)->toBe(WidowLoanScheduleStatus::PAID);
    expect($refreshedSchedules[2]->status)->toBe(WidowLoanScheduleStatus::WAIVED);
    expect($refreshedSchedules[3]->status)->toBe(WidowLoanScheduleStatus::WAIVED);
});

test('widow eligibility check respects reapplication_allowed flag after write-off', function () {
    $service = new WidowLoanWriteOffService;

    // Scenario A: Write-off WITH reapplication allowed
    $service->writeOff($this->loan, $this->superAdmin, 'Hardship', allowReapplication: true);
    $this->loan->refresh();
    expect($this->widow->canApplyForLoan())->toBeTrue();

    // Reset widow and create a new loan to test Scenario B
    $this->widow->widowLoans()->delete();
    $loan2 = WidowLoan::create([
        'widow_id' => $this->widow->id,
        'principal_amount' => 50000.00,
        'total_payable' => 50000.00,
        'outstanding_balance' => 50000.00,
        'status' => WidowLoanStatus::DISBURSED,
        'disbursed_at' => now(),
        'bank_account_id' => $this->bankAccount->id,
        'purpose' => 'Business',
    ]);

    // Scenario B: Write-off WITHOUT reapplication allowed
    $service->writeOff($loan2, $this->superAdmin, 'Hardship', allowReapplication: false);
    $this->loan->refresh();
    expect($this->widow->canApplyForLoan())->toBeFalse();
});

test('write-off document downloading is secure and rejects unauthorized users', function () {
    Storage::disk('local')->put('loan-write-offs/doc.pdf', 'evidence content');

    $writeOff = WidowLoanWriteOff::create([
        'widow_loan_id' => $this->loan->id,
        'original_outstanding_balance' => 100000.00,
        'amount_written_off' => 100000.00,
        'write_off_reason' => 'Hardship',
        'write_off_document_path' => 'loan-write-offs/doc.pdf',
        'authorized_by' => $this->superAdmin->id,
        'authorized_at' => now(),
    ]);

    // Unauthenticated user
    $this->getJson(route('loans.write-off-document.download', $writeOff))
        ->assertStatus(401);

    // Normal coordinator user
    $this->actingAs($this->nonAdmin)
        ->get(route('loans.write-off-document.download', $writeOff))
        ->assertStatus(403);

    // Admin user
    $this->actingAs($this->admin)
        ->get(route('loans.write-off-document.download', $writeOff))
        ->assertStatus(200);

    // Super Admin user
    $this->actingAs($this->superAdmin)
        ->get(route('loans.write-off-document.download', $writeOff))
        ->assertStatus(200);
});
