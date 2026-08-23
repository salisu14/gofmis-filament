<?php

use App\Enums\Gender;
use App\Enums\OrphanStatus;
use App\Filament\Coordinator\Resources\OrphanHistoryResource\Pages\ListOrphanHistories as CoordinatorListOrphanHistories;
use App\Filament\Coordinator\Resources\WidowHistoryResource\Pages\ListWidowHistories as CoordinatorListWidowHistories;
use App\Filament\Coordinator\Resources\WidowResource\Pages\ListWidows as CoordinatorListWidows;
use App\Filament\Resources\OrphanHistoryResource\Pages\ListOrphanHistories as AdminListOrphanHistories;
use App\Filament\Resources\Orphans\Pages\ListOrphans as AdminListOrphans;
use App\Filament\Resources\WidowHistoryResource\Pages\ListWidowHistories as AdminListWidowHistories;
use App\Filament\Resources\Widows\Pages\ListWidows as AdminListWidows;
use App\Models\Deceased;
use App\Models\Orphan;
use App\Models\User;
use App\Models\Widow;
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

    $this->activeWidow = Widow::create([
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

    $this->remarriedWidow = Widow::create([
        'first_name' => 'Fatima',
        'last_name' => 'Sani',
        'nin' => '98765432109',
        'reg_no' => 'WID-2026-0002',
        'is_eligible' => false,
        'is_married' => true,
        'married_at' => '2026-02-01',
        'deceased_id' => $this->deceasedA->id,
        'child_sequence' => 2,
        'full_name' => 'Fatima Sani',
        'address' => 'Garko, Kano State',
    ]);

    $this->activeOrphan = Orphan::create([
        'first_name' => 'Ibrahim',
        'last_name' => 'Usman',
        'gender' => Gender::MALE,
        'birth_date' => now()->subYears(10)->format('Y-m-d'),
        'deceased_id' => $this->deceasedA->id,
        'child_sequence' => 1,
        'is_eligible' => true,
        'status' => OrphanStatus::ACTIVE,
        'nin' => '11122233344',
        'reg_no' => 'ORP-2026-0001',
    ]);

    $this->archivedOrphan = Orphan::create([
        'first_name' => 'Kabiru',
        'last_name' => 'Usman',
        'gender' => Gender::MALE,
        'birth_date' => now()->subYears(19)->format('Y-m-d'), // Overaged!
        'deceased_id' => $this->deceasedA->id,
        'child_sequence' => 2,
        'is_eligible' => false,
        'status' => OrphanStatus::ARCHIVED,
        'rejection_reason' => 'Archived: male orphan is 18 years or older.',
        'nin' => '55566677788',
        'reg_no' => 'ORP-2026-0002',
    ]);
});

// 1. Active widow appears in main Widows list
test('1. active widow appears in main Widows list', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($this->admin);

    Livewire::test(AdminListWidows::class)
        ->assertCanSeeTableRecords([$this->activeWidow])
        ->assertCanNotSeeTableRecords([$this->remarriedWidow]);
});

// 2. Remarried widow disappears from main list
test('2. remarried widow disappears from main list', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($this->admin);

    $this->activeWidow->markAsMarried(notes: 'Remarried', marriedAt: '2026-05-01');

    Livewire::test(AdminListWidows::class)
        ->assertCanNotSeeTableRecords([$this->activeWidow]);
});

// 3. Remarried widow appears in Widow History
test('3. remarried widow appears in Widow History', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($this->admin);

    Livewire::test(AdminListWidowHistories::class)
        ->assertCanSeeTableRecords([$this->remarriedWidow])
        ->assertCanNotSeeTableRecords([$this->activeWidow]);
});

// 4. Divorced/reactivated widow returns to main list
test('4. divorced/reactivated widow returns to main list', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($this->admin);

    $this->remarriedWidow->reactivateAfterDivorce(notes: 'Divorced', divorcedAt: '2026-08-15');

    Livewire::test(AdminListWidows::class)
        ->assertCanSeeTableRecords([$this->remarriedWidow]);
});

// 5. Reactivated widow no longer appears in Widow History list
test('5. reactivated widow no longer appears in Widow History list', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($this->admin);

    $this->remarriedWidow->reactivateAfterDivorce(notes: 'Divorced', divorcedAt: '2026-08-15');

    Livewire::test(AdminListWidowHistories::class)
        ->assertCanNotSeeTableRecords([$this->remarriedWidow]);
});

// 6. Historical remarriage/divorce dates remain intact
test('6. historical remarriage/divorce dates remain intact', function () {
    $this->actingAs($this->admin);

    $this->remarriedWidow->reactivateAfterDivorce(notes: 'Divorced', divorcedAt: '2026-08-15');

    $this->remarriedWidow->refresh();
    expect($this->remarriedWidow->married_at->format('Y-m-d'))->toBe('2026-02-01')
        ->and($this->remarriedWidow->divorced_at->format('Y-m-d'))->toBe('2026-08-15');
});

// 7. Same NIN under a second deceased household is supported
test('7. same NIN under a second deceased household is supported', function () {
    $this->actingAs($this->admin);

    $deceasedB = Deceased::factory()->create(['zone_id' => $this->zone->id]);

    $widowB = Widow::create([
        'first_name' => 'Amina',
        'last_name' => 'Usman',
        'nin' => '12345678901', // Same NIN as activeWidow!
        'reg_no' => 'WID-2026-0099',
        'is_eligible' => true,
        'is_married' => false,
        'deceased_id' => $deceasedB->id,
        'child_sequence' => 1,
        'full_name' => 'Amina Usman',
        'address' => 'Garko, Kano State',
    ]);

    expect(Widow::where('nin', '12345678901')->count())->toBe(2);
});

// 8. Old widow household remains historical while new widow household is operational
test('8. old widow household remains historical while new widow household is operational', function () {
    $this->actingAs($this->admin);

    // Old household remarried
    $this->activeWidow->markAsMarried(notes: 'Remarried', marriedAt: '2026-05-01');

    // New household created
    $deceasedB = Deceased::factory()->create(['zone_id' => $this->zone->id]);
    $widowB = Widow::create([
        'first_name' => 'Amina',
        'last_name' => 'Usman',
        'nin' => '12345678901',
        'reg_no' => 'WID-2026-0099',
        'is_eligible' => true,
        'is_married' => false,
        'deceased_id' => $deceasedB->id,
        'child_sequence' => 1,
        'full_name' => 'Amina Usman',
        'address' => 'Garko, Kano State',
    ]);

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(AdminListWidows::class)
        ->assertCanSeeTableRecords([$widowB])
        ->assertCanNotSeeTableRecords([$this->activeWidow]);

    Livewire::test(AdminListWidowHistories::class)
        ->assertCanSeeTableRecords([$this->activeWidow])
        ->assertCanNotSeeTableRecords([$widowB]);
});

// 9. Coordinator sees only own-zone current widows
test('9. coordinator sees only own-zone current widows', function () {
    $otherDeceased = Deceased::factory()->create(['zone_id' => $this->otherZone->id]);
    $otherWidow = Widow::create([
        'first_name' => 'Zainab',
        'last_name' => 'Musa',
        'nin' => '77766655544',
        'reg_no' => 'WID-OTHER-01',
        'is_eligible' => true,
        'is_married' => false,
        'deceased_id' => $otherDeceased->id,
        'child_sequence' => 1,
        'full_name' => 'Zainab Musa',
        'address' => 'Garko, Kano',
    ]);

    Filament::setCurrentPanel(Filament::getPanel('coordinator'));
    $this->actingAs($this->coordinator);

    Livewire::test(CoordinatorListWidows::class)
        ->assertCanSeeTableRecords([$this->activeWidow])
        ->assertCanNotSeeTableRecords([$otherWidow, $this->remarriedWidow]);
});

// 10. Coordinator cannot browse cross-zone widow history
test('10. coordinator cannot browse cross-zone widow history', function () {
    $otherDeceased = Deceased::factory()->create(['zone_id' => $this->otherZone->id]);
    $otherRemarriedWidow = Widow::create([
        'first_name' => 'Hauwa',
        'last_name' => 'Ali',
        'nin' => '33344455566',
        'reg_no' => 'WID-OTHER-02',
        'is_eligible' => false,
        'is_married' => true,
        'married_at' => '2026-03-01',
        'deceased_id' => $otherDeceased->id,
        'child_sequence' => 1,
        'full_name' => 'Hauwa Ali',
        'address' => 'Garko, Kano',
    ]);

    Filament::setCurrentPanel(Filament::getPanel('coordinator'));
    $this->actingAs($this->coordinator);

    Livewire::test(CoordinatorListWidowHistories::class)
        ->assertCanSeeTableRecords([$this->remarriedWidow])
        ->assertCanNotSeeTableRecords([$otherRemarriedWidow]);
});

// 11. Active orphan appears in main list
test('11. active orphan appears in main list', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($this->admin);

    Livewire::test(AdminListOrphans::class)
        ->call('loadTable')
        ->assertCanSeeTableRecords([$this->activeOrphan])
        ->assertCanNotSeeTableRecords([$this->archivedOrphan]);
});

// 12. Archived/overaged orphan does not appear in main list
test('12. archived/overaged orphan does not appear in main list', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($this->admin);

    Livewire::test(AdminListOrphans::class)
        ->call('loadTable')
        ->assertCanNotSeeTableRecords([$this->archivedOrphan]);
});

// 13. Archived/overaged orphan appears in Orphan History
test('13. archived/overaged orphan appears in Orphan History', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($this->admin);

    Livewire::test(AdminListOrphanHistories::class)
        ->call('loadTable')
        ->assertCanSeeTableRecords([$this->archivedOrphan])
        ->assertCanNotSeeTableRecords([$this->activeOrphan]);
});

// 14. Temporarily ineligible but still operational orphan is not incorrectly archived
test('14. temporarily ineligible but still operational orphan is not incorrectly archived', function () {
    $this->actingAs($this->admin);

    $tempIneligibleOrphan = Orphan::create([
        'first_name' => 'Salisu',
        'last_name' => 'Usman',
        'gender' => Gender::MALE,
        'birth_date' => now()->subYears(12)->format('Y-m-d'), // 12 years old (under 18)
        'deceased_id' => $this->deceasedA->id,
        'child_sequence' => 3,
        'is_eligible' => false, // Temporarily ineligible (e.g. pending document verification)
        'status' => OrphanStatus::PENDING_REVIEW,
        'nin' => '99988877766',
        'reg_no' => 'ORP-2026-0099',
    ]);

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(AdminListOrphans::class)
        ->call('loadTable')
        ->assertCanSeeTableRecords([$tempIneligibleOrphan]);

    Livewire::test(AdminListOrphanHistories::class)
        ->call('loadTable')
        ->assertCanNotSeeTableRecords([$tempIneligibleOrphan]);
});

// 15. Coordinator zone isolation applies to orphan history
test('15. coordinator zone isolation applies to orphan history', function () {
    $otherDeceased = Deceased::factory()->create(['zone_id' => $this->otherZone->id]);
    $otherArchivedOrphan = Orphan::create([
        'first_name' => 'Bello',
        'last_name' => 'Sani',
        'gender' => Gender::MALE,
        'birth_date' => now()->subYears(20)->format('Y-m-d'),
        'deceased_id' => $otherDeceased->id,
        'child_sequence' => 1,
        'is_eligible' => false,
        'status' => OrphanStatus::ARCHIVED,
        'rejection_reason' => 'Archived: male orphan is 18 years or older.',
        'nin' => '44455566677',
        'reg_no' => 'ORP-OTHER-01',
    ]);

    Filament::setCurrentPanel(Filament::getPanel('coordinator'));
    $this->actingAs($this->coordinator);

    Livewire::test(CoordinatorListOrphanHistories::class)
        ->call('loadTable')
        ->assertCanSeeTableRecords([$this->archivedOrphan])
        ->assertCanNotSeeTableRecords([$otherArchivedOrphan]);
});

// 16. Operational counts exclude archived beneficiaries
test('16. operational counts exclude archived beneficiaries', function () {
    expect(Widow::operational()->count())->toBe(1)
        ->and(Orphan::operational()->count())->toBe(1);
});

// 17. Historical/total counts include them where intended
test('17. historical/total counts include them where intended', function () {
    expect(Widow::historical()->count())->toBe(1)
        ->and(Orphan::historical()->count())->toBe(1)
        ->and(Widow::count())->toBe(2)
        ->and(Orphan::count())->toBe(2);
});
