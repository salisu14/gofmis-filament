<?php

use App\Enums\AcademicProgressionDecision;
use App\Enums\InstitutionType;
use App\Filament\Pages\EducationAnalytics;
use App\Models\Deceased;
use App\Models\EducationFeeInvoice;
use App\Models\EducationFeePayment;
use App\Models\Institution;
use App\Models\Orphan;
use App\Models\OrphanClass;
use App\Models\OrphanEducation;
use App\Models\User;
use App\Models\Zone;
use App\Services\WesternEducationProgressionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $this->admin = User::factory()->create([
        'is_active' => true,
        'status' => \App\Enums\UserStatus::ACTIVE,
        'app_authentication_secret' => 'TESTSECRET123456',
        'mfa_confirmed_at' => now(),
        'mfa_enabled_at' => now(),
    ]);
    $this->admin->assignRole('admin');

    $this->coordinator = User::factory()->create([
        'is_active' => true,
        'status' => \App\Enums\UserStatus::ACTIVE,
        'app_authentication_secret' => 'TESTSECRET123456',
        'mfa_confirmed_at' => now(),
        'mfa_enabled_at' => now(),
    ]);
    $this->coordinator->assignRole('coordinator');

    $this->zoneA = Zone::create(['name' => 'Zone Alpha']);
    $this->zoneB = Zone::create(['name' => 'Zone Beta']);

    $this->deceasedA = Deceased::factory()->create(['zone_id' => $this->zoneA->id]);
    $this->deceasedB = Deceased::factory()->create(['zone_id' => $this->zoneB->id]);

    $this->orphanA = Orphan::create([
        'deceased_id' => $this->deceasedA->id,
        'first_name' => 'Analytics',
        'last_name' => 'StudentA',
        'full_name' => 'Analytics StudentA',
        'reg_no' => 'ORP-AN-001',
        'gender' => \App\Enums\Gender::MALE,
        'status' => \App\Enums\OrphanStatus::ACTIVE,
        'is_eligible' => true,
    ]);

    $this->orphanB = Orphan::create([
        'deceased_id' => $this->deceasedB->id,
        'first_name' => 'Analytics',
        'last_name' => 'StudentB',
        'full_name' => 'Analytics StudentB',
        'reg_no' => 'ORP-AN-002',
        'gender' => \App\Enums\Gender::FEMALE,
        'status' => \App\Enums\OrphanStatus::ACTIVE,
        'is_eligible' => true,
    ]);

    $this->school1 = Institution::firstOrCreate(['name' => 'Alpha Grammar School'], ['type' => InstitutionType::WESTERN]);
    $this->school2 = Institution::firstOrCreate(['name' => 'Beta High School'], ['type' => InstitutionType::WESTERN]);

    $this->p4 = OrphanClass::firstOrCreate(['name' => 'Primary 4'], ['user_id' => $this->admin->id]);
    $this->p5 = OrphanClass::firstOrCreate(['name' => 'Primary 5'], ['user_id' => $this->admin->id]);
    $this->p6 = OrphanClass::firstOrCreate(['name' => 'Primary 6'], ['user_id' => $this->admin->id]);
    $this->jss1 = OrphanClass::firstOrCreate(['name' => 'JSS I'], ['user_id' => $this->admin->id]);
    $this->ss3 = OrphanClass::firstOrCreate(['name' => 'SS III'], ['user_id' => $this->admin->id]);

    $this->service = app(WesternEducationProgressionService::class);
    $this->actingAs($this->admin);
});

test('1. Promotion counts are correct in analytics query', function () {
    $edu = OrphanEducation::create([
        'orphan_id' => $this->orphanA->id,
        'institution_id' => $this->school1->id,
        'orphan_class_id' => $this->p4->id,
        'academic_session' => '2025/2026',
        'is_current' => true,
        'started_at' => now()->subYear()->toDateString(),
    ]);

    $this->service->progress($edu, AcademicProgressionDecision::PROMOTED, ['academic_session' => '2026/2027'], $this->admin);

    $page = Livewire::test(EducationAnalytics::class);
    $stats = $page->instance()->getKpiStats();

    expect($stats['promotions'])->toBe(1)
        ->and($stats['current'])->toBe(1);
});

test('2. Repetition report returns only repeated students', function () {
    $edu = OrphanEducation::create([
        'orphan_id' => $this->orphanA->id,
        'institution_id' => $this->school1->id,
        'orphan_class_id' => $this->p4->id,
        'academic_session' => '2025/2026',
        'is_current' => true,
        'started_at' => now()->subYear()->toDateString(),
    ]);

    $this->service->progress($edu, AcademicProgressionDecision::REPEATED, ['academic_session' => '2026/2027'], $this->admin);

    $page = Livewire::test(EducationAnalytics::class);
    $stats = $page->instance()->getKpiStats();

    expect($stats['repetitions'])->toBe(1)
        ->and($stats['promotions'])->toBe(0);
});

test('3. Graduation count is correct', function () {
    $edu = OrphanEducation::create([
        'orphan_id' => $this->orphanA->id,
        'institution_id' => $this->school1->id,
        'orphan_class_id' => $this->ss3->id,
        'academic_session' => '2025/2026',
        'is_current' => true,
        'started_at' => now()->subYear()->toDateString(),
    ]);

    $this->service->progress($edu, AcademicProgressionDecision::GRADUATED, ['reason' => 'Completed Secondary Education'], $this->admin);

    $page = Livewire::test(EducationAnalytics::class);
    $stats = $page->instance()->getKpiStats();

    expect($stats['graduations'])->toBe(1)
        ->and($stats['current'])->toBe(0);
});

test('4. Primary 6 to JSS I transition is counted only when actual transition exists', function () {
    $eduP6 = OrphanEducation::create([
        'orphan_id' => $this->orphanA->id,
        'institution_id' => $this->school1->id,
        'orphan_class_id' => $this->p6->id,
        'academic_session' => '2025/2026',
        'is_current' => true,
        'started_at' => now()->subYear()->toDateString(),
    ]);

    $successor = $this->service->progress($eduP6, AcademicProgressionDecision::PROMOTED, ['academic_session' => '2026/2027'], $this->admin);

    expect($successor->previous_enrollment_id)->toBe($eduP6->id)
        ->and($successor->orphan_class_id)->toBe($this->jss1->id);
});

test('5. Withdrawal and dropout decisions are explicit, not inferred', function () {
    $eduA = OrphanEducation::create([
        'orphan_id' => $this->orphanA->id,
        'institution_id' => $this->school1->id,
        'orphan_class_id' => $this->p4->id,
        'academic_session' => '2025/2026',
        'is_current' => true,
        'started_at' => now()->subYear()->toDateString(),
    ]);

    $eduB = OrphanEducation::create([
        'orphan_id' => $this->orphanB->id,
        'institution_id' => $this->school2->id,
        'orphan_class_id' => $this->p5->id,
        'academic_session' => '2025/2026',
        'is_current' => true,
        'started_at' => now()->subYear()->toDateString(),
    ]);

    $this->service->progress($eduA, AcademicProgressionDecision::WITHDRAWN, ['reason' => 'Relocated to another state'], $this->admin);
    $this->service->progress($eduB, AcademicProgressionDecision::DROPPED_OUT, ['reason' => 'Discontinued education'], $this->admin);

    $stats = Livewire::test(EducationAnalytics::class)->instance()->getKpiStats();

    expect($stats['withdrawals'])->toBe(1)
        ->and($stats['dropouts'])->toBe(1)
        ->and(OrphanEducation::where('orphan_id', $this->orphanA->id)->where('is_current', true)->count())->toBe(0)
        ->and(OrphanEducation::where('orphan_id', $this->orphanB->id)->where('is_current', true)->count())->toBe(0);
});

test('6. Lifetime education cost does not double count invoices or payments', function () {
    $edu = OrphanEducation::create([
        'orphan_id' => $this->orphanA->id,
        'institution_id' => $this->school1->id,
        'orphan_class_id' => $this->p4->id,
        'school_fee' => 15000.00,
        'support_amount' => 15000.00,
        'is_fee_supported' => true,
        'academic_session' => '2025/2026',
        'is_current' => true,
        'started_at' => now()->subYear()->toDateString(),
    ]);

    $invoice = EducationFeeInvoice::create([
        'orphan_education_id' => $edu->id,
        'amount' => 15000.00,
        'due_date' => now()->addDays(30)->toDateString(),
        'period' => 'Term 1',
        'issued_at' => now()->toDateString(),
    ]);

    EducationFeePayment::create([
        'education_fee_invoice_id' => $invoice->id,
        'amount' => 15000.00,
        'payment_date' => now()->toDateString(),
        'payment_method' => 'bank_transfer',
    ]);

    expect($edu->total_paid)->toBe(15000.0)
        ->and($edu->balance)->toBe(0.0);
});

test('7. Academic session filtering works correctly', function () {
    OrphanEducation::create([
        'orphan_id' => $this->orphanA->id,
        'institution_id' => $this->school1->id,
        'orphan_class_id' => $this->p4->id,
        'academic_session' => '2024/2025',
        'is_current' => false,
        'started_at' => now()->subYears(2)->toDateString(),
    ]);

    OrphanEducation::create([
        'orphan_id' => $this->orphanA->id,
        'institution_id' => $this->school1->id,
        'orphan_class_id' => $this->p5->id,
        'academic_session' => '2025/2026',
        'is_current' => true,
        'started_at' => now()->subYear()->toDateString(),
    ]);

    $page = Livewire::test(EducationAnalytics::class)
        ->set('filterData.academic_session', '2025/2026');

    $stats = $page->instance()->getKpiStats();

    expect($stats['current'])->toBe(1);
});

test('8. Institution filtering works correctly', function () {
    OrphanEducation::create([
        'orphan_id' => $this->orphanA->id,
        'institution_id' => $this->school1->id,
        'orphan_class_id' => $this->p4->id,
        'academic_session' => '2025/2026',
        'is_current' => true,
        'started_at' => now()->subYear()->toDateString(),
    ]);

    OrphanEducation::create([
        'orphan_id' => $this->orphanB->id,
        'institution_id' => $this->school2->id,
        'orphan_class_id' => $this->p5->id,
        'academic_session' => '2025/2026',
        'is_current' => true,
        'started_at' => now()->subYear()->toDateString(),
    ]);

    $page = Livewire::test(EducationAnalytics::class)
        ->set('filterData.institution_id', $this->school1->id);

    $stats = $page->instance()->getKpiStats();

    expect($stats['current'])->toBe(1);
});

test('9. Zone filtering works for Admin', function () {
    OrphanEducation::create([
        'orphan_id' => $this->orphanA->id, // Zone Alpha
        'institution_id' => $this->school1->id,
        'orphan_class_id' => $this->p4->id,
        'academic_session' => '2025/2026',
        'is_current' => true,
        'started_at' => now()->subYear()->toDateString(),
    ]);

    OrphanEducation::create([
        'orphan_id' => $this->orphanB->id, // Zone Beta
        'institution_id' => $this->school2->id,
        'orphan_class_id' => $this->p5->id,
        'academic_session' => '2025/2026',
        'is_current' => true,
        'started_at' => now()->subYear()->toDateString(),
    ]);

    $page = Livewire::test(EducationAnalytics::class)
        ->set('filterData.zone_id', $this->zoneA->id);

    $stats = $page->instance()->getKpiStats();

    expect($stats['current'])->toBe(1);
});

test('10. Coordinator cannot access Admin analytics unless permission explicitly granted', function () {
    $this->actingAs($this->coordinator);

    expect(EducationAnalytics::canAccess())->toBeFalse();
});

test('11. Legacy null-session records do not crash analytics', function () {
    OrphanEducation::create([
        'orphan_id' => $this->orphanA->id,
        'institution_id' => $this->school1->id,
        'orphan_class_id' => $this->p4->id,
        'academic_session' => null, // Legacy row
        'is_current' => false,
        'started_at' => now()->subYears(3)->toDateString(),
    ]);

    $stats = Livewire::test(EducationAnalytics::class)->instance()->getKpiStats();

    expect($stats['current'])->toBe(0);
});

test('12. Current enrollment uniqueness remains intact', function () {
    $edu = OrphanEducation::create([
        'orphan_id' => $this->orphanA->id,
        'institution_id' => $this->school1->id,
        'orphan_class_id' => $this->p4->id,
        'academic_session' => '2025/2026',
        'is_current' => true,
        'started_at' => now()->subYear()->toDateString(),
    ]);

    $this->service->progress($edu, AcademicProgressionDecision::PROMOTED, ['academic_session' => '2026/2027'], $this->admin);

    expect(OrphanEducation::where('orphan_id', $this->orphanA->id)->where('institution_id', $this->school1->id)->where('is_current', true)->count())->toBe(1);
});

test('13. Route exists and page is registered under Education navigation group', function () {
    expect(\Illuminate\Support\Facades\Route::has('filament.admin.pages.education-analytics'))->toBeTrue()
        ->and(EducationAnalytics::getNavigationGroup())->toBe('Education')
        ->and(EducationAnalytics::getNavigationLabel())->toBe('Education Analytics')
        ->and(EducationAnalytics::canAccess())->toBeTrue();

    $this->actingAs($this->admin)
        ->withSession(['mfa_verified_at' => time(), 'mfa_verified_user_id' => $this->admin->id])
        ->get('/admin/education-analytics')
        ->assertStatus(200);
});

test('14. Unauthorized user cannot access analytics page or route', function () {
    $unauthorizedUser = User::factory()->create(['is_active' => true, 'status' => \App\Enums\UserStatus::ACTIVE]);

    $this->actingAs($unauthorizedUser);

    expect(EducationAnalytics::canAccess())->toBeFalse();

    $this->actingAs($unauthorizedUser)
        ->withSession(['mfa_verified_at' => time(), 'mfa_verified_user_id' => $unauthorizedUser->id])
        ->get('/admin/education-analytics')
        ->assertStatus(403);

    $this->actingAs($this->coordinator)
        ->withSession(['mfa_verified_at' => time(), 'mfa_verified_user_id' => $this->coordinator->id])
        ->get('/admin/education-analytics')
        ->assertStatus(403);
});

test('15. Required permissions exist in database and are assigned to Admin role', function () {
    $viewPerm = \App\Models\Permission::where('name', 'orphan_education.analytics.view')->first();
    $exportPerm = \App\Models\Permission::where('name', 'orphan_education.analytics.export')->first();

    expect($viewPerm)->not->toBeNull()
        ->and($exportPerm)->not->toBeNull();

    expect($this->admin->can('orphan_education.analytics.view'))->toBeTrue()
        ->and($this->admin->can('orphan_education.analytics.export'))->toBeTrue();
});

test('16. Page renders filters, all 6 report tabs, KPI card labels, and clean markup without unconstrained icons', function () {
    $response = $this->actingAs($this->admin)
        ->withSession(['mfa_verified_at' => time(), 'mfa_verified_user_id' => $this->admin->id])
        ->get('/admin/education-analytics');

    $response->assertStatus(200)
        ->assertSee('Combined Analytics Filters')
        ->assertSee('Progression Summary')
        ->assertSee('Repeated Students')
        ->assertSee('SS III Graduations')
        ->assertSee('P6 → JSS I Transitions')
        ->assertSee('Institution Rates')
        ->assertSee('Lifetime Support Cost')
        ->assertSee('Current Active')
        ->assertSee('Promotions')
        ->assertSee('Total Support Cost')
        ->assertSee('Avg Cost / Orphan')
        ->assertDontSee('<x-heroicon-o-funnel class="w-5 h-5', false);
});

test('17. User without export permission cannot export CSV and button is hidden', function () {
    $viewOnlyUser = User::factory()->create([
        'is_active' => true,
        'status' => \App\Enums\UserStatus::ACTIVE,
        'app_authentication_secret' => 'TESTSECRET123456',
        'mfa_confirmed_at' => now(),
        'mfa_enabled_at' => now(),
    ]);
    $viewOnlyUser->givePermissionTo('orphan_education.analytics.view');

    expect($viewOnlyUser->can('orphan_education.analytics.view'))->toBeTrue()
        ->and($viewOnlyUser->can('orphan_education.analytics.export'))->toBeFalse();

    Livewire::actingAs($viewOnlyUser)
        ->test(EducationAnalytics::class)
        ->call('exportCsv')
        ->assertStatus(403);
});

test('18. Demo Observer behavior remains permission-driven and read-only', function () {
    $demoUser = User::factory()->create([
        'is_active' => true,
        'status' => \App\Enums\UserStatus::ACTIVE,
        'app_authentication_secret' => 'TESTSECRET123456',
        'mfa_confirmed_at' => now(),
        'mfa_enabled_at' => now(),
    ]);
    $demoUser->assignRole('demo_observer');

    expect($demoUser->can('orphan_education.analytics.view'))->toBeTrue()
        ->and($demoUser->can('orphan_education.analytics.export'))->toBeFalse();

    $this->actingAs($demoUser)
        ->withSession(['mfa_verified_at' => time(), 'mfa_verified_user_id' => $demoUser->id]);

    expect(EducationAnalytics::canAccess())->toBeTrue();
});

test('19. Native EducationAnalyticsOverviewWidget renders all 11 KPIs and reflects active filters', function () {
    Livewire::actingAs($this->admin)
        ->test(\App\Filament\Widgets\EducationAnalyticsOverviewWidget::class, [
            'filters' => [
                'academic_session' => '2025/2026',
            ],
        ])
        ->assertSee('Current Active')
        ->assertSee('Promotions')
        ->assertSee('Repetitions')
        ->assertSee('Demotions')
        ->assertSee('Graduations')
        ->assertSee('Transfers')
        ->assertSee('Withdrawals')
        ->assertSee('Dropouts')
        ->assertSee('P6 → JSS I Transitions')
        ->assertSee('Total Support Cost')
        ->assertSee('Avg Cost / Orphan')
        ->assertSee('₦');
});

test('20. Distinct tab selections render correct dedicated table columns', function () {
    // 1. Default / Summary Tab
    Livewire::actingAs($this->admin)
        ->test(EducationAnalytics::class)
        ->set('activeTab', 'summary')
        ->assertTableColumnExists('progression_decision')
        ->assertTableColumnExists('started_at');

    // 2. Repeated Tab
    Livewire::actingAs($this->admin)
        ->test(EducationAnalytics::class)
        ->set('activeTab', 'repeated')
        ->assertTableColumnExists('progression_reason');

    // 3. Graduation Tab
    Livewire::actingAs($this->admin)
        ->test(EducationAnalytics::class)
        ->set('activeTab', 'graduation')
        ->assertTableColumnExists('ended_at');

    // 4. Transition Tab
    Livewire::actingAs($this->admin)
        ->test(EducationAnalytics::class)
        ->set('activeTab', 'transition')
        ->assertTableColumnExists('successorEnrollment.institution.name');

    // 5. Institution Rates Tab
    Livewire::actingAs($this->admin)
        ->test(EducationAnalytics::class)
        ->set('activeTab', 'institution')
        ->assertTableColumnExists('institution.name');

    // 6. Lifetime Support Cost Tab
    Livewire::actingAs($this->admin)
        ->test(EducationAnalytics::class)
        ->set('activeTab', 'lifetime_cost')
        ->assertTableColumnExists('support_amount');
});
