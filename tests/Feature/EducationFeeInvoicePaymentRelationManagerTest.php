<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Deceased;
use App\Models\EducationFeeInvoice;
use App\Models\Institution;
use App\Models\Orphan;
use App\Models\OrphanEducation;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class EducationFeeInvoicePaymentRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $unauthorizedUser;

    protected Zone $zone;

    protected Deceased $deceased;

    protected Orphan $orphan;

    protected Institution $institution;

    protected OrphanEducation $education;

    protected BankAccount $bankAccount;

    protected EducationFeeInvoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        \Spatie\Permission\Models\Role::firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'web'],
            ['uuid' => (string) \Illuminate\Support\Str::uuid()]
        );

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->unauthorizedUser = User::factory()->create();

        $this->zone = Zone::create(['name' => 'Kano Central Zone', 'code' => 'KCZ']);

        $this->deceased = Deceased::create([
            'first_name' => 'Deceased',
            'last_name' => 'Parent',
            'nin' => '12345678901',
            'reg_no' => 'DEC-KCZ-001',
            'guardian_name' => 'Guardian',
            'guardian_phone' => '08012345678',
            'vulnerability_status' => 'A',
            'date_registered' => now(),
            'zone_id' => $this->zone->id,
        ]);

        $this->orphan = Orphan::create([
            'deceased_id' => $this->deceased->id,
            'first_name' => 'Orphan',
            'last_name' => 'Student',
            'date_of_birth' => now()->subYears(10),
            'gender' => \App\Enums\Gender::MALE->value,
            'reg_no' => 'ORP-KCZ-001',
            'is_eligible' => true,
            'status' => 'active',
        ]);

        $this->institution = Institution::create([
            'name' => 'Kano Model School',
            'code' => 'KMS-01',
            'type' => \App\Enums\InstitutionType::WESTERN->value,
        ]);

        $this->education = OrphanEducation::create([
            'orphan_id' => $this->orphan->id,
            'institution_id' => $this->institution->id,
            'education_level' => 'primary',
            'class_level' => 'Primary 4',
            'academic_year' => '2025/2026',
        ]);

        $parentBank = BankAccount::create([
            'account_name' => 'General Treasury',
            'account_number' => '1234567890',
            'bank_name' => 'First Bank',
            'user_id' => $this->admin->id,
            'ledger_balance' => 100000.00,
        ]);

        $this->bankAccount = BankAccount::create([
            'account_name' => 'Education Dedicated Account',
            'account_number' => '1234567891',
            'bank_name' => 'First Bank',
            'user_id' => $this->admin->id,
            'parent_bank_account_id' => $parentBank->id,
            'usage' => BankAccount::USAGE_EDUCATION,
        ]);
        $this->bankAccount->updateQuietly(['ledger_balance' => 100000.00]);

        $this->invoice = EducationFeeInvoice::create([
            'invoice_number' => 'INV-001',
            'orphan_education_id' => $this->education->id,
            'amount' => 50000.00,
            'amount_paid' => 0.00,
            'period' => '2025/2026 Term 1',
            'status' => 'unpaid',
            'due_date' => now()->addMonth(),
        ]);
    }

    public function test_1_relation_manager_does_not_declare_related_resource(): void
    {
        $relationManager = Livewire::actingAs($this->admin)
            ->test(\App\Filament\Resources\EducationFeeInvoices\RelationManagers\PaymentsRelationManager::class, [
                'ownerRecord' => $this->invoice,
                'pageClass' => \App\Filament\Resources\EducationFeeInvoices\Pages\EditEducationFeeInvoice::class,
            ]);

        $instance = $relationManager->instance();
        expect($instance->getRelatedResource())->toBeNull();
    }

    public function test_2_create_action_renders_its_own_payment_fields(): void
    {
        $relationManager = Livewire::actingAs($this->admin)
            ->test(\App\Filament\Resources\EducationFeeInvoices\RelationManagers\PaymentsRelationManager::class, [
                'ownerRecord' => $this->invoice,
                'pageClass' => \App\Filament\Resources\EducationFeeInvoices\Pages\EditEducationFeeInvoice::class,
            ])
            ->assertSuccessful();

        $instance = $relationManager->instance();
        $formSchema = $instance->form(\Filament\Schemas\Schema::make($instance));
        $componentNames = collect($formSchema->getFlatComponents())
            ->map(fn ($c) => method_exists($c, 'getName') ? $c->getName() : null)
            ->filter()
            ->values()
            ->toArray();

        expect($componentNames)->toContain('bank_account_id');
        expect($componentNames)->toContain('amount');
        expect($componentNames)->toContain('payment_method');
        expect($componentNames)->not->toContain('orphan_education_id');
        expect($componentNames)->not->toContain('education_fee_invoice_id');
    }

    public function test_3_valid_payment_creates_exactly_one_row_and_binds_invoice(): void
    {
        Livewire::actingAs($this->admin)
            ->test(\App\Filament\Resources\EducationFeeInvoices\RelationManagers\PaymentsRelationManager::class, [
                'ownerRecord' => $this->invoice,
                'pageClass' => \App\Filament\Resources\EducationFeeInvoices\Pages\EditEducationFeeInvoice::class,
            ])
            ->callTableAction('create', data: [
                'bank_account_id' => $this->bankAccount->id,
                'amount' => 20000.00,
                'payment_date' => now()->format('Y-m-d'),
                'payment_method' => 'transfer',
            ])
            ->assertHasNoTableActionErrors();

        expect($this->invoice->payments()->count())->toBe(1);

        $payment = $this->invoice->payments()->first();
        expect($payment->education_fee_invoice_id)->toBe($this->invoice->id);
        expect($payment->amount)->toEqual(20000.00);
    }

    public function test_4_payment_debits_selected_bank_account_correctly(): void
    {
        $initialBalance = (float) $this->bankAccount->fresh()->ledger_balance;

        Livewire::actingAs($this->admin)
            ->test(\App\Filament\Resources\EducationFeeInvoices\RelationManagers\PaymentsRelationManager::class, [
                'ownerRecord' => $this->invoice,
                'pageClass' => \App\Filament\Resources\EducationFeeInvoices\Pages\EditEducationFeeInvoice::class,
            ])
            ->callTableAction('create', data: [
                'bank_account_id' => $this->bankAccount->id,
                'amount' => 20000.00,
                'payment_date' => now()->format('Y-m-d'),
                'payment_method' => 'transfer',
            ]);

        expect((float) $this->bankAccount->fresh()->ledger_balance)->toBe($initialBalance - 20000.00);
    }

    public function test_5_invoice_paid_total_outstanding_and_status_update_correctly(): void
    {
        Livewire::actingAs($this->admin)
            ->test(\App\Filament\Resources\EducationFeeInvoices\RelationManagers\PaymentsRelationManager::class, [
                'ownerRecord' => $this->invoice,
                'pageClass' => \App\Filament\Resources\EducationFeeInvoices\Pages\EditEducationFeeInvoice::class,
            ])
            ->callTableAction('create', data: [
                'bank_account_id' => $this->bankAccount->id,
                'amount' => 50000.00,
                'payment_date' => now()->format('Y-m-d'),
                'payment_method' => 'transfer',
            ]);

        $freshInvoice = $this->invoice->fresh();
        expect($freshInvoice->paid_amount)->toEqual(50000.00);
        expect($freshInvoice->balance)->toEqual(0.00);
        expect($freshInvoice->status)->toBe('paid');
    }

    public function test_6_overpayment_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        app(\App\Services\EducationFeeInvoiceService::class)->recordPayment($this->invoice, [
            'bank_account_id' => $this->bankAccount->id,
            'amount' => 60000.00,
            'payment_date' => now(),
            'payment_method' => 'transfer',
        ]);
    }

    public function test_7_create_action_hidden_when_invoice_is_finalized(): void
    {
        $this->invoice->update(['status' => 'paid']);

        Livewire::actingAs($this->admin)
            ->test(\App\Filament\Resources\EducationFeeInvoices\RelationManagers\PaymentsRelationManager::class, [
                'ownerRecord' => $this->invoice->fresh(),
                'pageClass' => \App\Filament\Resources\EducationFeeInvoices\Pages\EditEducationFeeInvoice::class,
            ])
            ->assertTableActionHidden('create');
    }

    public function test_8_failed_transaction_leaves_bank_and_invoice_unchanged(): void
    {
        $initialBankBalance = (float) $this->bankAccount->fresh()->ledger_balance;
        $initialPaidAmount = (float) $this->invoice->fresh()->paid_amount;

        try {
            app(\App\Services\EducationFeeInvoiceService::class)->recordPayment($this->invoice, [
                'bank_account_id' => $this->bankAccount->id,
                'amount' => 999999.00, // Exceeds both balance and bank funds
                'payment_date' => now(),
                'payment_method' => 'transfer',
            ]);
        } catch (ValidationException $e) {
            // Expected
        }

        expect((float) $this->bankAccount->fresh()->ledger_balance)->toBe($initialBankBalance);
        expect((float) $this->invoice->fresh()->paid_amount)->toBe($initialPaidAmount);
        expect($this->invoice->payments()->count())->toBe(0);
    }
}
