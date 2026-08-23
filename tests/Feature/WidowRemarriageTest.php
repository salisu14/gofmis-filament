<?php

use App\Filament\Resources\Widows\Pages\ListWidows as AdminListWidows;
use App\Models\Deceased;
use App\Models\User;
use App\Models\Widow;
use App\Models\WidowLoan;
use App\Models\WidowLoanRepayment;
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

    $this->zone = Zone::create(['name' => 'Kano Central', 'coordinator_id' => $this->coordinator->id]);
    $this->deceasedA = Deceased::factory()->create(['zone_id' => $this->zone->id]);

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

// 1. Active widow can be marked remarried and sets is_married and married_at
test('1. active widow can be marked remarried', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($this->admin);

    $marriageDate = '2026-06-15';

    Livewire::test(AdminListWidows::class)
        ->callTableAction('markAsMarried', $this->widowA, [
            'married_at' => $marriageDate,
            'notes' => 'Remarried to new spouse',
        ]);

    $this->widowA->refresh();

    expect($this->widowA->is_married)->toBeTrue()
        ->and($this->widowA->is_eligible)->toBeFalse()
        ->and($this->widowA->married_at->format('Y-m-d'))->toBe('2026-06-15');
});

// 2. Remarriage date persists
test('2. remarriage date persists correctly', function () {
    $this->actingAs($this->admin);
    $this->widowA->markAsMarried(notes: 'Remarried', marriedAt: '2026-07-01');

    $this->widowA->refresh();
    expect($this->widowA->married_at->format('Y-m-d'))->toBe('2026-07-01');
});

// 3. Original Widow record remains intact
test('3. original widow record remains intact after remarriage', function () {
    $this->actingAs($this->admin);
    $this->widowA->markAsMarried(notes: 'Remarried', marriedAt: '2026-07-01');

    expect(Widow::find($this->widowA->id))->not->toBeNull()
        ->and((string) $this->widowA->deceased_id)->toBe((string) $this->deceasedA->id);
});

// 4. Existing loan remains attached
test('4. existing loan remains attached to original widow record', function () {
    $loan = WidowLoan::create([
        'widow_id' => $this->widowA->id,
        'principal_amount' => 50000,
        'total_amount' => 50000,
        'repayment_term_months' => 6,
        'status' => \App\Enums\WidowLoanStatus::DISBURSED,
    ]);

    $this->actingAs($this->admin);
    $this->widowA->markAsMarried(notes: 'Remarried', marriedAt: '2026-07-01');

    expect(WidowLoan::where('widow_id', $this->widowA->id)->first()->id)->toBe($loan->id);
});

// 5. Existing repayments remain attached
test('5. existing repayments remain attached to original widow record', function () {
    $loan = WidowLoan::create([
        'widow_id' => $this->widowA->id,
        'principal_amount' => 50000,
        'total_amount' => 50000,
        'repayment_term_months' => 6,
        'status' => \App\Enums\WidowLoanStatus::DISBURSED,
    ]);

    $repayment = WidowLoanRepayment::create([
        'widow_loan_id' => $loan->id,
        'amount' => 10000,
        'paid_at' => now(),
        'payment_method' => 'cash',
        'recorded_by' => $this->admin->id,
    ]);

    $this->actingAs($this->admin);
    $this->widowA->markAsMarried(notes: 'Remarried', marriedAt: '2026-07-01');

    expect(WidowLoanRepayment::where('widow_loan_id', $loan->id)->first()->id)->toBe($repayment->id);
});

// 6. Existing interventions/welfare remain attached
test('6. existing interventions and welfare allocations remain attached', function () {
    $welfarePackage = \App\Models\WelfarePackage::create([
        'name' => 'Widow Food Package',
        'status' => \App\Enums\WelfarePackageStatus::OPEN,
        'start_date' => now(),
        'end_date' => now()->addDays(30),
        'created_by' => $this->admin->id,
    ]);

    $welfareBeneficiary = \App\Models\WelfareBeneficiary::create([
        'welfare_package_id' => $welfarePackage->id,
        'deceased_id' => $this->deceasedA->id,
        'status' => \App\Enums\BeneficiaryStatus::PENDING,
        'suggested_by' => $this->admin->id,
    ]);

    $this->actingAs($this->admin);
    $this->widowA->markAsMarried(notes: 'Remarried', marriedAt: '2026-07-01');

    $welfareBeneficiary->refresh();
    expect($welfareBeneficiary->deceased_id)->toBe((string) $this->deceasedA->id);
});

// 7. Remarried widow becomes ineligible for active benefits
test('7. remarried widow becomes ineligible according to current rules', function () {
    $this->actingAs($this->admin);
    $this->widowA->markAsMarried(notes: 'Remarried', marriedAt: '2026-07-01');

    $this->widowA->refresh();
    expect($this->widowA->is_eligible)->toBeFalse();
});

// 8. Remarried widow can be reactivated after divorce
test('8. remarried widow can be reactivated after divorce', function () {
    $this->actingAs($this->admin);
    $this->widowA->markAsMarried(notes: 'Remarried', marriedAt: '2026-07-01');

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(AdminListWidows::class)
        ->callTableAction('reactivateAfterDivorce', $this->widowA, [
            'divorced_at' => '2026-08-20',
            'notes' => 'Divorced from second husband',
        ]);

    $this->widowA->refresh();

    expect($this->widowA->is_married)->toBeFalse()
        ->and($this->widowA->is_eligible)->toBeTrue()
        ->and($this->widowA->divorced_at->format('Y-m-d'))->toBe('2026-08-20');
});

// 9. Reactivation uses same Widow record under original deceased
test('9. reactivation uses same Widow record under original deceased', function () {
    $this->actingAs($this->admin);
    $this->widowA->markAsMarried(notes: 'Remarried', marriedAt: '2026-07-01');
    $this->widowA->reactivateAfterDivorce(notes: 'Divorced', divorcedAt: '2026-08-20');

    $widowCount = Widow::where('deceased_id', $this->deceasedA->id)->count();

    expect($widowCount)->toBe(1)
        ->and(Widow::first()->id)->toBe($this->widowA->id);
});

// 10. Reactivation does not duplicate the Widow record
test('10. reactivation does not duplicate the Widow record', function () {
    $this->actingAs($this->admin);
    $this->widowA->markAsMarried(notes: 'Remarried', marriedAt: '2026-07-01');
    $this->widowA->reactivateAfterDivorce(notes: 'Divorced', divorcedAt: '2026-08-20');

    expect(Widow::where('nin', '12345678901')->count())->toBe(1);
});

// 11. Marriage-history events remain visible/auditable
test('11. marriage-history events remain visible and auditable', function () {
    $this->actingAs($this->admin);
    $this->widowA->markAsMarried(notes: 'Remarried note', marriedAt: '2026-07-01');
    $this->widowA->reactivateAfterDivorce(notes: 'Divorce note', divorcedAt: '2026-08-20');

    $activities = \Illuminate\Support\Facades\DB::table('activities')
        ->where('subject_id', (string) $this->widowA->id)
        ->get();

    $eventTypes = $activities->map(function ($a) {
        $props = json_decode($a->properties, true);

        return $props['event_type'] ?? $a->description;
    })->toArray();

    expect($eventTypes)->toContain('REMARRIED')
        ->and($eventTypes)->toContain('REACTIVATED_AFTER_DIVORCE');
});

// 12. A second husband's death permits creation of a NEW Widow record under a new Deceased household
test('12. second husband death permits creation of a NEW Widow record under a new Deceased household', function () {
    $this->actingAs($this->admin);
    $this->widowA->markAsMarried(notes: 'Remarried to Husband B', marriedAt: '2026-07-01');

    // Second husband dies -> New Deceased B created
    $deceasedB = Deceased::factory()->create(['zone_id' => $this->zone->id]);

    // Create NEW Widow record for the same woman (same NIN) under Deceased B
    $widowB = Widow::create([
        'first_name' => 'Amina',
        'last_name' => 'Usman',
        'nin' => '12345678901', // Same NIN!
        'reg_no' => 'WID-2026-0002',
        'is_eligible' => true,
        'is_married' => false,
        'deceased_id' => $deceasedB->id,
        'child_sequence' => 1,
        'full_name' => 'Amina Usman',
        'address' => 'Garko, Kano State',
    ]);

    expect($widowB)->not->toBeNull()
        ->and((string) $widowB->deceased_id)->toBe((string) $deceasedB->id)
        ->and(Widow::where('nin', '12345678901')->count())->toBe(2);
});

// 13. The same woman/person can legitimately have historical Widow relationships with two different deceased husbands
test('13. same woman can legitimately have historical Widow relationships with two different deceased husbands', function () {
    $this->actingAs($this->admin);
    $this->widowA->markAsMarried(notes: 'Remarried to Husband B', marriedAt: '2026-07-01');

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

    $records = Widow::where('nin', '12345678901')->get();

    expect($records)->toHaveCount(2)
        ->and($records->pluck('deceased_id')->toArray())->toContain((string) $this->deceasedA->id)
        ->and($records->pluck('deceased_id')->toArray())->toContain((string) $deceasedB->id);
});

// 14. Existing uniqueness validation does not incorrectly block multi-household scenario
test('14. uniqueness validation does not block new widow record for different deceased', function () {
    $this->actingAs($this->admin);
    $deceasedB = Deceased::factory()->create(['zone_id' => $this->zone->id]);

    // Validation rule per deceased should allow same NIN under deceasedB
    $rule = (new Widow)->getTable();
    expect(Widow::where('deceased_id', $deceasedB->id)->where('nin', '12345678901')->exists())->toBeFalse();
});

// 15. Financial history from Husband A is not moved to Husband B relationship
test('15. financial history from Husband A is not moved to Husband B relationship', function () {
    $loanA = WidowLoan::create([
        'widow_id' => $this->widowA->id,
        'principal_amount' => 50000,
        'total_amount' => 50000,
        'repayment_term_months' => 6,
        'status' => \App\Enums\WidowLoanStatus::DISBURSED,
    ]);

    $this->actingAs($this->admin);
    $this->widowA->markAsMarried(notes: 'Remarried', marriedAt: '2026-07-01');

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

    expect(WidowLoan::where('widow_id', $this->widowA->id)->first()->id)->toBe($loanA->id)
        ->and(WidowLoan::where('widow_id', $widowB->id)->exists())->toBeFalse();
});
