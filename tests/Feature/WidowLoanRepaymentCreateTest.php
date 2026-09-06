<?php

namespace Tests\Feature;

use App\Enums\WidowLoanStatus;
use App\Filament\Resources\WidowLoanRepayments\Pages\CreateWidowLoanRepayment;
use App\Models\BankAccount;
use App\Models\Deceased;
use App\Models\User;
use App\Models\Widow;
use App\Models\WidowLoan;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WidowLoanRepaymentCreateTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Widow $widow;

    protected WidowLoan $loan;

    protected BankAccount $disbursementAccount;

    protected BankAccount $repaymentAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $zone = Zone::create(['name' => 'Zone A']);
        $deceased = Deceased::factory()->create(['zone_id' => $zone->id]);

        $this->widow = Widow::create([
            'first_name' => 'Zainab',
            'last_name' => 'Suleiman',
            'nin' => '12345678901',
            'reg_no' => 'WID-33333',
            'is_eligible' => true,
            'is_married' => false,
            'deceased_id' => $deceased->id,
            'full_name' => 'Zainab Suleiman',
            'child_sequence' => 1,
        ]);

        // Create main treasury account
        $mainAccount = BankAccount::create([
            'account_name' => 'Main Treasury',
            'account_number' => '1000000000',
            'usage' => BankAccount::USAGE_GENERAL,
            'ledger_balance' => 1000000.00,
            'user_id' => $this->admin->id,
        ]);

        $this->disbursementAccount = BankAccount::create([
            'account_name' => 'WRL Disbursement Account',
            'account_number' => '1000000001',
            'usage' => BankAccount::USAGE_WIDOW_LOAN_DISBURSEMENT,
            'parent_bank_account_id' => $mainAccount->id,
            'ledger_balance' => 500000.00,
            'user_id' => $this->admin->id,
        ]);

        $this->repaymentAccount = BankAccount::create([
            'account_name' => 'WRL Repayment Account',
            'account_number' => '1000000002',
            'usage' => BankAccount::USAGE_WIDOW_LOAN_REPAYMENT,
            'parent_bank_account_id' => $mainAccount->id,
            'ledger_balance' => 0.00,
            'user_id' => $this->admin->id,
        ]);

        $this->loan = WidowLoan::create([
            'widow_id' => $this->widow->id,
            'principal_amount' => 30000.00,
            'total_payable' => 30000.00,
            'outstanding_balance' => 30000.00,
            'status' => WidowLoanStatus::DISBURSED,
            'disbursed_at' => now()->subDays(10),
            'collected_at' => now()->subDays(10),
            'bank_account_id' => $this->disbursementAccount->id,
            'repayment_bank_id' => $this->repaymentAccount->id,
            'purpose' => 'Provision shop restock',
        ]);
    }

    public function test_form_submission_when_loan_has_repayment_bank_id_set(): void
    {
        $this->actingAs($this->admin);

        Livewire::withQueryParams(['widow_loan_id' => $this->loan->id])
            ->test(CreateWidowLoanRepayment::class)
            ->fillForm([
                'amount' => 5000.00,
                'paid_at' => '2026-09-06',
                'payment_method' => 'transfer',
                'notes' => '1st repayment',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('widow_loan_repayments', [
            'widow_loan_id' => $this->loan->id,
            'amount' => 5000.00,
            'payment_method' => 'transfer',
            'bank_account_id' => $this->repaymentAccount->id,
        ]);

        $repayment = \App\Models\WidowLoanRepayment::where('widow_loan_id', $this->loan->id)->first();

        // Verify transaction record created
        $this->assertDatabaseHas('transactions', [
            'bank_account_id' => $this->repaymentAccount->id,
            'transactionable_type' => \App\Models\WidowLoanRepayment::class,
            'transactionable_id' => $repayment->id,
            'type' => 'loan_repayment',
            'amount' => 5000.00,
        ]);

        // Verify ledger balances: repayment account increased, disbursement account untouched
        $this->assertEquals(5000.00, (float) $this->repaymentAccount->fresh()->ledger_balance);
        $this->assertEquals(0.00, (float) $this->disbursementAccount->fresh()->ledger_balance);

        // Verify loan balance decreased
        $this->assertEquals(25000.00, (float) $this->loan->fresh()->outstanding_balance);
    }

    public function test_form_submission_when_loan_has_no_repayment_bank_id_set(): void
    {
        $loanWithoutRepaymentBank = WidowLoan::create([
            'widow_id' => $this->widow->id,
            'principal_amount' => 30000.00,
            'total_payable' => 30000.00,
            'outstanding_balance' => 30000.00,
            'status' => WidowLoanStatus::DISBURSED,
            'disbursed_at' => now()->subDays(10),
            'collected_at' => now()->subDays(10),
            'bank_account_id' => $this->disbursementAccount->id,
            'repayment_bank_id' => null,
            'purpose' => 'Provision shop restock',
        ]);

        $this->actingAs($this->admin);

        Livewire::withQueryParams(['widow_loan_id' => $loanWithoutRepaymentBank->id])
            ->test(CreateWidowLoanRepayment::class)
            ->fillForm([
                'amount' => 5000.00,
                'paid_at' => '2026-09-06',
                'payment_method' => 'transfer',
                'notes' => '1st repayment',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('widow_loan_repayments', [
            'widow_loan_id' => $loanWithoutRepaymentBank->id,
            'amount' => 5000.00,
            'payment_method' => 'transfer',
            'bank_account_id' => $this->repaymentAccount->id,
        ]);
    }

    public function test_cash_repayment_creation_succeeds(): void
    {
        $this->actingAs($this->admin);

        Livewire::withQueryParams(['widow_loan_id' => $this->loan->id])
            ->test(CreateWidowLoanRepayment::class)
            ->fillForm([
                'amount' => 3000.00,
                'paid_at' => '2026-09-06',
                'payment_method' => 'cash',
                'notes' => 'Cash repayment',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('widow_loan_repayments', [
            'widow_loan_id' => $this->loan->id,
            'amount' => 3000.00,
            'payment_method' => 'cash',
            'bank_account_id' => $this->repaymentAccount->id,
        ]);
    }

    public function test_wrong_purpose_bank_account_is_rejected_and_leaves_no_partial_data(): void
    {
        $service = app(\App\Services\WidowLoanService::class);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        try {
            $service->recordRepayment(new \App\Data\Loan\RecordWidowLoanRepaymentData(
                widowLoanId: $this->loan->id,
                amount: 5000.00,
                paidAt: now()->toDateString(),
                bankAccountId: $this->disbursementAccount->id,
                paymentMethod: 'transfer',
                notes: 'Invalid account test'
            ));
        } finally {
            // Verify no partial financial state created
            $this->assertDatabaseMissing('widow_loan_repayments', [
                'widow_loan_id' => $this->loan->id,
                'amount' => 5000.00,
            ]);
            $this->assertEquals(0, \App\Models\Transaction::where('type', 'loan_repayment')->count());
            $this->assertEquals(30000.00, (float) $this->loan->fresh()->outstanding_balance);
        }
    }
}
