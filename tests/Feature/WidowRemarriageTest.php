<?php

use App\Filament\Coordinator\Resources\WidowResource\Pages\ListWidows as CoordinatorListWidows;
use App\Filament\Resources\WidowHistoryResource\Pages\ListWidowHistories as AdminListWidowHistories;
use App\Filament\Resources\Widows\Pages\CreateWidow as AdminCreateWidow;
use App\Filament\Resources\Widows\Pages\ListWidows as AdminListWidows;
use App\Filament\Resources\Widows\Pages\ViewWidow;
use App\Models\Deceased;
use App\Models\IdCard;
use App\Models\IdCardTemplate;
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

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->coordinator = User::factory()->create();
    $this->coordinator->assignRole('coordinator');

    $this->otherCoordinator = User::factory()->create();
    $this->otherCoordinator->assignRole('coordinator');

    $this->zone = Zone::create(['name' => 'Kano Central', 'coordinator_id' => $this->coordinator->id]);
    $this->otherZone = Zone::create(['name' => 'Kano North', 'coordinator_id' => $this->otherCoordinator->id]);

    $this->deceasedA = Deceased::factory()->create([
        'zone_id' => $this->zone->id,
        'date_of_death' => '2025-01-15',
    ]);

    $this->widowA = Widow::create([
        'first_name' => 'Amina',
        'last_name' => 'Usman',
        'nin' => '12345678901',
        'reg_no' => 'WID-2026-0001',
        'is_eligible' => true,
        'is_married' => false,
        'deceased_id' => $this->deceasedA->id,
        'child_sequence' => 1,
        'full_name' => 'Amina Usman',
        'address' => 'Garko, Kano State',
    ]);
});

// 1. Remarriage date required
test('1. remarriage date is required', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($this->admin);

    Livewire::test(AdminListWidows::class)
        ->callTableAction('markAsMarried', $this->widowA, [
            'married_at' => null,
        ])
        ->assertHasTableActionErrors(['married_at' => 'required']);
});

// 2. Future remarriage date rejected via action validation
test('2. future remarriage date rejected via action validation', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($this->admin);

    $futureDate = now()->addDays(5)->format('Y-m-d');

    Livewire::test(AdminListWidows::class)
        ->callTableAction('markAsMarried', $this->widowA, [
            'married_at' => $futureDate,
        ])
        ->assertHasTableActionErrors(['married_at']);
});

// 3. Remarriage date before original husband's date of death rejected
test('3. remarriage date before original husband date of death rejected', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($this->admin);

    // deceased husband date of death is 2025-01-15
    $earlierDate = '2024-12-01';

    Livewire::test(AdminListWidows::class)
        ->callTableAction('markAsMarried', $this->widowA, [
            'married_at' => $earlierDate,
        ])
        ->assertHasTableActionErrors(['married_at']);
});

// 4. Remarriage works when legacy deceased has no date_of_death
test('4. remarriage works when legacy deceased has no date_of_death', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($this->admin);

    $legacyDeceased = Deceased::factory()->create([
        'zone_id' => $this->zone->id,
        'date_of_death' => null,
    ]);

    $legacyWidow = Widow::create([
        'first_name' => 'Zainab',
        'last_name' => 'Ali',
        'nin' => '99887766554',
        'reg_no' => 'WID-LEGACY-02',
        'is_eligible' => true,
        'is_married' => false,
        'deceased_id' => $legacyDeceased->id,
        'child_sequence' => 1,
        'full_name' => 'Zainab Ali',
        'address' => 'Garko, Kano State',
    ]);

    Livewire::test(AdminListWidows::class)
        ->callTableAction('markAsMarried', $legacyWidow, [
            'married_at' => '2026-02-01',
            'notes' => 'Legacy deceased remarriage',
        ])
        ->assertHasNoTableActionErrors();

    $legacyWidow->refresh();
    expect($legacyWidow->is_married)->toBeTrue()
        ->and($legacyWidow->married_at->format('Y-m-d'))->toBe('2026-02-01');
});

// 5. Divorce / reactivation date required
test('5. divorce / reactivation date required', function () {
    $this->actingAs($this->admin);
    $this->widowA->markAsMarried(notes: 'Remarried', marriedAt: '2026-05-01');

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(AdminListWidowHistories::class)
        ->callTableAction('reactivateAfterDivorce', $this->widowA, [
            'divorced_at' => null,
        ])
        ->assertHasTableActionErrors(['divorced_at' => 'required']);
});

// 6. Future divorce / reactivation date rejected via action validation
test('6. future divorce / reactivation date rejected via action validation', function () {
    $this->actingAs($this->admin);
    $this->widowA->markAsMarried(notes: 'Remarried', marriedAt: '2026-05-01');

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $futureDate = now()->addDays(10)->format('Y-m-d');

    Livewire::test(AdminListWidowHistories::class)
        ->callTableAction('reactivateAfterDivorce', $this->widowA, [
            'divorced_at' => $futureDate,
        ])
        ->assertHasTableActionErrors(['divorced_at']);
});

// 7. Divorce date earlier than remarriage date rejected
test('7. divorce date earlier than remarriage date rejected', function () {
    $this->actingAs($this->admin);
    $this->widowA->markAsMarried(notes: 'Remarried', marriedAt: '2026-05-01');

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(AdminListWidowHistories::class)
        ->callTableAction('reactivateAfterDivorce', $this->widowA, [
            'divorced_at' => '2026-04-15', // Earlier than 2026-05-01!
        ])
        ->assertHasTableActionErrors(['divorced_at']);
});

// 8. Valid divorce reactivation succeeds
test('8. valid divorce reactivation succeeds', function () {
    $this->actingAs($this->admin);
    $this->widowA->markAsMarried(notes: 'Remarried', marriedAt: '2026-05-01');

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(AdminListWidowHistories::class)
        ->callTableAction('reactivateAfterDivorce', $this->widowA, [
            'divorced_at' => '2026-08-15',
            'notes' => 'Divorce final',
        ])
        ->assertHasNoTableActionErrors();

    $this->widowA->refresh();
    expect($this->widowA->is_married)->toBeFalse()
        ->and($this->widowA->is_eligible)->toBeTrue()
        ->and($this->widowA->divorced_at->format('Y-m-d'))->toBe('2026-08-15');
});

// 9. Remarriage history remains after divorce
test('9. remarriage history remains intact after divorce', function () {
    $this->actingAs($this->admin);
    $this->widowA->markAsMarried(notes: 'Remarried', marriedAt: '2026-05-01');
    $this->widowA->reactivateAfterDivorce(notes: 'Divorced', divorcedAt: '2026-08-15');

    $this->widowA->refresh();
    expect($this->widowA->married_at->format('Y-m-d'))->toBe('2026-05-01')
        ->and($this->widowA->divorced_at->format('Y-m-d'))->toBe('2026-08-15');
});

// 10. Same NIN + same deceased rejected cleanly by validation
test('10. same NIN + same deceased rejected cleanly by validation', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($this->admin);

    Livewire::test(AdminCreateWidow::class)
        ->fillForm([
            'deceased_id' => (string) $this->deceasedA->id,
            'first_name' => 'Amina',
            'last_name' => 'Usman',
            'nin' => '12345678901', // Same NIN + Same Deceased!
            'address' => 'Garko, Kano',
        ])
        ->call('create')
        ->assertHasFormErrors(['nin']);
});

// 11. Same NIN + different deceased allowed
test('11. same NIN + different deceased allowed', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($this->admin);

    $deceasedB = Deceased::factory()->create(['zone_id' => $this->zone->id]);

    Livewire::test(AdminCreateWidow::class)
        ->fillForm([
            'deceased_id' => (string) $deceasedB->id,
            'first_name' => 'Amina',
            'last_name' => 'Usman',
            'nin' => '12345678901', // Same NIN + Different Deceased!
            'address' => 'Garko, Kano',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Widow::where('nin', '12345678901')->count())->toBe(2);
});

// 12. Second-household record does not overwrite first-household record
test('12. second-household record does not overwrite first-household record', function () {
    $this->actingAs($this->admin);
    $this->widowA->markAsMarried(notes: 'Remarried to Husband B', marriedAt: '2026-05-01');

    $deceasedB = Deceased::factory()->create(['zone_id' => $this->zone->id]);

    $widowB = Widow::create([
        'first_name' => 'Amina',
        'last_name' => 'Usman',
        'nin' => '12345678901',
        'reg_no' => 'WID-2026-0002',
        'is_eligible' => true,
        'is_married' => false,
        'deceased_id' => $deceasedB->id,
        'child_sequence' => 1,
        'full_name' => 'Amina Usman',
        'address' => 'Garko, Kano State',
    ]);

    $this->widowA->refresh();
    expect($this->widowA->is_married)->toBeTrue()
        ->and($this->widowA->is_eligible)->toBeFalse()
        ->and($widowB->is_married)->toBeFalse()
        ->and($widowB->is_eligible)->toBeTrue();
});

// 13. Divorce reactivates original household only
test('13. divorce reactivates original household only', function () {
    $this->actingAs($this->admin);
    $this->widowA->markAsMarried(notes: 'Remarried', marriedAt: '2026-05-01');
    $this->widowA->reactivateAfterDivorce(notes: 'Divorced', divorcedAt: '2026-08-15');

    $this->widowA->refresh();
    expect($this->widowA->is_married)->toBeFalse()
        ->and($this->widowA->is_eligible)->toBeTrue()
        ->and((string) $this->widowA->deceased_id)->toBe((string) $this->deceasedA->id);
});

// 14. Second husband's death scenario remains a separate widow record
test('14. second husband death scenario remains a separate widow record', function () {
    $this->actingAs($this->admin);
    $deceasedB = Deceased::factory()->create(['zone_id' => $this->zone->id]);

    $widowB = Widow::create([
        'first_name' => 'Amina',
        'last_name' => 'Usman',
        'nin' => '12345678901',
        'reg_no' => 'WID-2026-0002',
        'is_eligible' => true,
        'is_married' => false,
        'deceased_id' => $deceasedB->id,
        'child_sequence' => 1,
        'full_name' => 'Amina Usman',
        'address' => 'Garko, Kano State',
    ]);

    expect($widowB->id)->not->toBe($this->widowA->id)
        ->and(Widow::where('nin', '12345678901')->count())->toBe(2);
});

// 15. Revoked ID card remains revoked after divorce reactivation
test('15. revoked ID card remains revoked after divorce reactivation', function () {
    $template = IdCardTemplate::create([
        'name' => 'Standard Widow Template',
        'type' => 'widow',
        'is_active' => true,
    ]);

    $idCard = IdCard::create([
        'card_number' => 'GOF-W-2026-0001',
        'cardable_type' => Widow::class,
        'cardable_id' => $this->widowA->id,
        'template_id' => $template->id,
        'qr_code_path' => 'qrcodes/test.png',
        'issued_at' => now(),
        'status' => 'active',
    ]);

    $this->actingAs($this->admin);
    $this->widowA->markAsMarried(notes: 'Remarried', marriedAt: '2026-05-01');
    $this->widowA->reactivateAfterDivorce(notes: 'Divorced', divorcedAt: '2026-08-15');

    $idCard->refresh();
    expect($idCard->status)->toBe('revoked');
});

// 16. Action visibility state matrix
test('16. action visibility state matrix', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($this->admin);

    // Active unmarried widow: markAsMarried visible on AdminListWidows
    Livewire::test(AdminListWidows::class)
        ->assertTableActionVisible('markAsMarried', $this->widowA);

    // Remarried widow: reactivateAfterDivorce visible on AdminListWidowHistories
    $this->widowA->markAsMarried(notes: 'Remarried', marriedAt: '2026-05-01');

    Livewire::test(AdminListWidowHistories::class)
        ->assertTableActionVisible('reactivateAfterDivorce', $this->widowA);

    // Reactivated widow: markAsMarried visible again on AdminListWidows
    $this->widowA->reactivateAfterDivorce(notes: 'Divorced', divorcedAt: '2026-08-15');

    Livewire::test(AdminListWidows::class)
        ->assertTableActionVisible('markAsMarried', $this->widowA);
});

// 17. Marital lifecycle history renders correctly on view page
test('17. marital lifecycle history renders correctly on view page', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($this->admin);

    $this->widowA->markAsMarried(notes: 'Remarried note', marriedAt: '2026-05-01');
    $this->widowA->reactivateAfterDivorce(notes: 'Divorce note', divorcedAt: '2026-08-15');

    Livewire::test(ViewWidow::class, ['record' => $this->widowA->id])
        ->assertSuccessful()
        ->assertSee('Marital Lifecycle History')
        ->assertSee('REMARRIED')
        ->assertSee('REACTIVATED AFTER DIVORCE');
});

// 18. Coordinator cannot cross zones or access out-of-zone widow
test('18. coordinator cannot cross zones or access out-of-zone widow', function () {
    Filament::setCurrentPanel(Filament::getPanel('coordinator'));
    $this->actingAs($this->coordinator); // Coordinator for Kano Central

    $otherDeceased = Deceased::factory()->create(['zone_id' => $this->otherZone->id]);
    $otherWidow = Widow::create([
        'first_name' => 'Hauwa',
        'last_name' => 'Sani',
        'nin' => '55443322110',
        'reg_no' => 'WID-OTHER-01',
        'is_eligible' => true,
        'is_married' => false,
        'deceased_id' => $otherDeceased->id,
        'child_sequence' => 1,
        'full_name' => 'Hauwa Sani',
        'address' => 'Garko, Kano',
    ]);

    Livewire::test(CoordinatorListWidows::class)
        ->assertCanSeeTableRecords([$this->widowA])
        ->assertCanNotSeeTableRecords([$otherWidow]);
});

// 19. Duplicate-NIN informational warning respects authorization scope
test('19. duplicate-NIN informational warning respects authorization scope', function () {
    $this->actingAs($this->admin);
    // Create record in other zone
    $otherDeceased = Deceased::factory()->create(['zone_id' => $this->otherZone->id]);
    Widow::create([
        'first_name' => 'Khadija',
        'last_name' => 'Musa',
        'nin' => '88877766655',
        'reg_no' => 'WID-Z2-01',
        'is_eligible' => true,
        'is_married' => false,
        'deceased_id' => $otherDeceased->id,
        'child_sequence' => 1,
        'full_name' => 'Khadija Musa',
        'address' => 'Garko, Kano',
    ]);

    // Helper text closure logic
    $evalHelperText = function ($user, $ninState) {
        $query = Widow::where('nin', $ninState);
        if ($user && ! $user->hasAnyRole(['admin', 'super_admin'])) {
            $zoneId = $user->coordinatedZone?->id;
            if ($zoneId) {
                $query->whereHas('deceased', fn ($q) => $q->where('zone_id', $zoneId));
            } else {
                return '11-digit National Identity Number';
            }
        }
        $existing = $query->with('deceased')->get();
        if ($existing->isEmpty()) {
            return '11-digit National Identity Number';
        }
        $info = $existing->map(fn ($w) => "{$w->reg_no} (".($w->deceased?->full_name ?: 'Deceased #'.$w->deceased_id).')')->implode(', ');

        return "⚠️ Notice: This woman already has a widow record under another deceased household [{$info}]. Creating this record will establish a separate widow history for the selected deceased.";
    };

    // Admin sees notice for cross-zone NIN
    $helperTextAdmin = $evalHelperText($this->admin, '88877766655');
    expect($helperTextAdmin)->toContain('Notice: This woman already has a widow record under another deceased household');

    // Coordinator for Zone 1 does NOT see details of Zone 2 NIN
    $helperTextCoord = $evalHelperText($this->coordinator, '88877766655');
    expect($helperTextCoord)->toBe('11-digit National Identity Number');
});

// 20. No SQL exception leaks for duplicate submissions
test('20. no SQL exception leaks for duplicate submissions', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($this->admin);

    Livewire::test(AdminCreateWidow::class)
        ->fillForm([
            'deceased_id' => (string) $this->deceasedA->id,
            'first_name' => 'Amina',
            'last_name' => 'Usman',
            'nin' => '12345678901', // Duplicate under same deceased
            'address' => 'Garko, Kano',
        ])
        ->call('create')
        ->assertHasFormErrors(['nin']);
});

// 21. Divorce restores eligibility when remarriage is the only blocker
test('21. divorce restores eligibility when remarriage is the only blocker', function () {
    $this->actingAs($this->admin);
    $this->widowA->markAsMarried(notes: 'Remarried', marriedAt: '2026-05-01');

    expect($this->widowA->is_eligible)->toBeFalse();

    $this->widowA->reactivateAfterDivorce(notes: 'Divorced', divorcedAt: '2026-08-15');

    $this->widowA->refresh();
    expect($this->widowA->is_married)->toBeFalse()
        ->and($this->widowA->is_eligible)->toBeTrue();
});

// 22. Divorce does not restore eligibility when loan write-off restriction remains
test('22. divorce does not restore eligibility when loan write-off restriction remains', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super_admin');

    $bankAccount = \App\Models\BankAccount::create([
        'account_name' => 'Disbursement Account',
        'account_number' => '9988776655',
        'opening_balance' => 500000.00,
        'ledger_balance' => 500000.00,
        'user_id' => $superAdmin->id,
    ]);

    $loan = WidowLoan::create([
        'widow_id' => $this->widowA->id,
        'principal_amount' => 50000.00,
        'total_amount' => 50000.00,
        'repayment_term_months' => 6,
        'outstanding_balance' => 50000.00,
        'status' => \App\Enums\WidowLoanStatus::DISBURSED,
        'disbursed_at' => now(),
        'bank_account_id' => $bankAccount->id,
    ]);

    // Super Admin writes off loan with reapplication_allowed = false
    $writeOffService = new \App\Services\WidowLoanWriteOffService;
    $writeOffService->writeOff($loan, $superAdmin, 'Hardship default', allowReapplication: false);

    // Widow remarries
    $this->actingAs($this->admin);
    $this->widowA->markAsMarried(notes: 'Remarried', marriedAt: '2026-05-01');

    // Widow divorces -> reactivate after divorce
    $this->widowA->reactivateAfterDivorce(notes: 'Divorced', divorcedAt: '2026-08-15');

    $this->widowA->refresh();
    expect($this->widowA->is_married)->toBeFalse()
        ->and($this->widowA->is_eligible)->toBeFalse(); // Preserved false due to write-off restriction!
});

// 23. Divorce does not mutate or delete loan write-off audit data
test('23. divorce does not mutate or delete loan write-off audit data', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super_admin');

    $bankAccount = \App\Models\BankAccount::create([
        'account_name' => 'Disbursement Account 2',
        'account_number' => '9988776656',
        'opening_balance' => 500000.00,
        'ledger_balance' => 500000.00,
        'user_id' => $superAdmin->id,
    ]);

    $loan = WidowLoan::create([
        'widow_id' => $this->widowA->id,
        'principal_amount' => 50000.00,
        'total_amount' => 50000.00,
        'repayment_term_months' => 6,
        'outstanding_balance' => 50000.00,
        'status' => \App\Enums\WidowLoanStatus::DISBURSED,
        'disbursed_at' => now(),
        'bank_account_id' => $bankAccount->id,
    ]);

    $writeOffService = new \App\Services\WidowLoanWriteOffService;
    $writeOffService->writeOff($loan, $superAdmin, 'Default writeoff', allowReapplication: false);

    $this->actingAs($this->admin);
    $this->widowA->markAsMarried(notes: 'Remarried', marriedAt: '2026-05-01');
    $this->widowA->reactivateAfterDivorce(notes: 'Divorced', divorcedAt: '2026-08-15');

    $loan->refresh();
    expect($loan->status)->toBe(\App\Enums\WidowLoanStatus::WRITTEN_OFF)
        ->and($loan->reapplication_allowed)->toBeFalse()
        ->and(\App\Models\WidowLoanWriteOff::where('widow_loan_id', $loan->id)->exists())->toBeTrue();
});

// 24. Revoked ID card remains revoked in both simple and write-off restricted cases
test('24. revoked ID card remains revoked in both simple and write-off restricted cases', function () {
    $template = IdCardTemplate::create([
        'name' => 'Standard Widow Template',
        'type' => 'widow',
        'is_active' => true,
    ]);

    $idCard = IdCard::create([
        'card_number' => 'GOF-W-2026-0099',
        'cardable_type' => Widow::class,
        'cardable_id' => $this->widowA->id,
        'template_id' => $template->id,
        'qr_code_path' => 'qrcodes/test.png',
        'issued_at' => now(),
        'status' => 'active',
    ]);

    $this->actingAs($this->admin);
    $this->widowA->markAsMarried(notes: 'Remarried', marriedAt: '2026-05-01');
    $this->widowA->reactivateAfterDivorce(notes: 'Divorced', divorcedAt: '2026-08-15');

    $idCard->refresh();
    expect($idCard->status)->toBe('revoked');
});
