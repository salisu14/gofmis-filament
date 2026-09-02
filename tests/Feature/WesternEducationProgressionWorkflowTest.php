<?php

use App\Enums\AcademicProgressionDecision;
use App\Enums\InstitutionType;
use App\Models\Deceased;
use App\Models\EducationFeeInvoice;
use App\Models\Institution;
use App\Models\Orphan;
use App\Models\OrphanClass;
use App\Models\OrphanEducation;
use App\Models\User;
use App\Models\Zone;
use App\Services\WesternEducationProgressionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $this->admin = User::factory()->create(['is_active' => true, 'status' => \App\Enums\UserStatus::ACTIVE]);
    $this->admin->assignRole('admin');

    $this->coordinator = User::factory()->create(['is_active' => true, 'status' => \App\Enums\UserStatus::ACTIVE]);
    $this->coordinator->assignRole('coordinator');

    $this->zone = Zone::create(['name' => 'Zone W']);
    $this->deceased = Deceased::factory()->create(['zone_id' => $this->zone->id]);
    $this->orphan = Orphan::create([
        'deceased_id' => $this->deceased->id,
        'first_name' => 'Academic',
        'last_name' => 'Student',
        'full_name' => 'Academic Student',
        'reg_no' => 'ORP-W-100',
        'nin' => '99999999999',
        'gender' => \App\Enums\Gender::MALE,
        'birth_date' => now()->subYears(10)->toDateString(),
        'status' => \App\Enums\OrphanStatus::ACTIVE,
        'is_eligible' => true,
    ]);

    $this->westernSchool = Institution::create(['name' => 'West Academy', 'type' => InstitutionType::WESTERN]);
    $this->islamiyyaSchool = Institution::create(['name' => 'Nurul Islam', 'type' => InstitutionType::ISLAMIYYA]);

    $this->p1 = OrphanClass::create(['name' => 'Primary 1', 'user_id' => $this->admin->id]);
    $this->p2 = OrphanClass::create(['name' => 'Primary 2', 'user_id' => $this->admin->id]);
    $this->p3 = OrphanClass::create(['name' => 'Primary 3', 'user_id' => $this->admin->id]);
    $this->p4 = OrphanClass::create(['name' => 'Primary 4', 'user_id' => $this->admin->id]);
    $this->p5 = OrphanClass::create(['name' => 'Primary 5', 'user_id' => $this->admin->id]);
    $this->p6 = OrphanClass::create(['name' => 'Primary 6', 'user_id' => $this->admin->id]);
    $this->jss1 = OrphanClass::create(['name' => 'JSS I', 'user_id' => $this->admin->id]);
    $this->ss1 = OrphanClass::create(['name' => 'SS I', 'user_id' => $this->admin->id]);
    $this->ss2 = OrphanClass::create(['name' => 'SS II', 'user_id' => $this->admin->id]);
    $this->ss3 = OrphanClass::create(['name' => 'SS III', 'user_id' => $this->admin->id]);

    $this->service = app(WesternEducationProgressionService::class);
});

test('1. Primary 4 + PROMOTED -> Primary 5 succeeds', function () {
    $education = OrphanEducation::create([
        'orphan_id' => $this->orphan->id,
        'institution_id' => $this->westernSchool->id,
        'orphan_class_id' => $this->p4->id,
        'class_level' => 'Primary 4',
        'academic_session' => '2025/2026',
        'is_current' => true,
        'started_at' => now()->subYear()->toDateString(),
    ]);

    $successor = $this->service->progress(
        $education,
        AcademicProgressionDecision::PROMOTED,
        ['academic_session' => '2026/2027', 'effective_date' => now()->toDateString()],
        $this->admin
    );

    expect($successor->orphan_class_id)->toBe($this->p5->id)
        ->and($successor->class_level)->toBe('Primary 5')
        ->and($successor->academic_session)->toBe('2026/2027')
        ->and($successor->is_current)->toBeTrue()
        ->and($education->fresh()->is_current)->toBeFalse();
});

test('2. Primary 4 + PROMOTED -> Primary 3 fails', function () {
    $education = OrphanEducation::create([
        'orphan_id' => $this->orphan->id,
        'institution_id' => $this->westernSchool->id,
        'orphan_class_id' => $this->p4->id,
        'academic_session' => '2025/2026',
        'is_current' => true,
        'started_at' => now()->subYear()->toDateString(),
    ]);

    expect(fn () => $this->service->progress(
        $education,
        AcademicProgressionDecision::PROMOTED,
        ['new_class_id' => $this->p3->id, 'academic_session' => '2026/2027'],
        $this->admin
    ))->toThrow(\DomainException::class, 'exact next sequential class');
});

test('3. Primary 4 + PROMOTED -> Primary 1 fails', function () {
    $education = OrphanEducation::create([
        'orphan_id' => $this->orphan->id,
        'institution_id' => $this->westernSchool->id,
        'orphan_class_id' => $this->p4->id,
        'academic_session' => '2025/2026',
        'is_current' => true,
        'started_at' => now()->subYear()->toDateString(),
    ]);

    expect(fn () => $this->service->progress(
        $education,
        AcademicProgressionDecision::PROMOTED,
        ['new_class_id' => $this->p1->id, 'academic_session' => '2026/2027'],
        $this->admin
    ))->toThrow(\DomainException::class);
});

test('4. Primary 4 + PROMOTED -> Primary 6 fails', function () {
    $education = OrphanEducation::create([
        'orphan_id' => $this->orphan->id,
        'institution_id' => $this->westernSchool->id,
        'orphan_class_id' => $this->p4->id,
        'academic_session' => '2025/2026',
        'is_current' => true,
        'started_at' => now()->subYear()->toDateString(),
    ]);

    expect(fn () => $this->service->progress(
        $education,
        AcademicProgressionDecision::PROMOTED,
        ['new_class_id' => $this->p6->id, 'academic_session' => '2026/2027'],
        $this->admin
    ))->toThrow(\DomainException::class);
});

test('5. Primary 6 + PROMOTED -> JSS I succeeds', function () {
    $education = OrphanEducation::create([
        'orphan_id' => $this->orphan->id,
        'institution_id' => $this->westernSchool->id,
        'orphan_class_id' => $this->p6->id,
        'academic_session' => '2025/2026',
        'is_current' => true,
        'started_at' => now()->subYear()->toDateString(),
    ]);

    $successor = $this->service->progress(
        $education,
        AcademicProgressionDecision::PROMOTED,
        ['academic_session' => '2026/2027'],
        $this->admin
    );

    expect($successor->orphan_class_id)->toBe($this->jss1->id);
});

test('6. JSS III + PROMOTED -> SS I succeeds', function () {
    $jss3 = OrphanClass::create(['name' => 'JSS III', 'user_id' => $this->admin->id]);
    $education = OrphanEducation::create([
        'orphan_id' => $this->orphan->id,
        'institution_id' => $this->westernSchool->id,
        'orphan_class_id' => $jss3->id,
        'academic_session' => '2025/2026',
        'is_current' => true,
        'started_at' => now()->subYear()->toDateString(),
    ]);

    $successor = $this->service->progress(
        $education,
        AcademicProgressionDecision::PROMOTED,
        ['academic_session' => '2026/2027'],
        $this->admin
    );

    expect($successor->orphan_class_id)->toBe($this->ss1->id);
});

test('7. SS II + PROMOTED -> SS III succeeds', function () {
    $education = OrphanEducation::create([
        'orphan_id' => $this->orphan->id,
        'institution_id' => $this->westernSchool->id,
        'orphan_class_id' => $this->ss2->id,
        'academic_session' => '2025/2026',
        'is_current' => true,
        'started_at' => now()->subYear()->toDateString(),
    ]);

    $successor = $this->service->progress(
        $education,
        AcademicProgressionDecision::PROMOTED,
        ['academic_session' => '2026/2027'],
        $this->admin
    );

    expect($successor->orphan_class_id)->toBe($this->ss3->id);
});

test('8. SS III normal terminal progression -> GRADUATED', function () {
    $education = OrphanEducation::create([
        'orphan_id' => $this->orphan->id,
        'institution_id' => $this->westernSchool->id,
        'orphan_class_id' => $this->ss3->id,
        'academic_session' => '2025/2026',
        'is_current' => true,
        'started_at' => now()->subYear()->toDateString(),
    ]);

    // Promoted throws exception on SS III
    expect(fn () => $this->service->progress(
        $education,
        AcademicProgressionDecision::PROMOTED,
        ['academic_session' => '2026/2027'],
        $this->admin
    ))->toThrow(\DomainException::class, 'final class');

    // Graduated succeeds
    $result = $this->service->progress(
        $education,
        AcademicProgressionDecision::GRADUATED,
        ['effective_date' => now()->toDateString(), 'reason' => 'Completed High School'],
        $this->admin
    );

    expect($result->is_current)->toBeFalse()
        ->and($result->progression_decision)->toBe(AcademicProgressionDecision::GRADUATED)
        ->and(OrphanEducation::where('orphan_id', $this->orphan->id)->where('is_current', true)->count())->toBe(0);
});

test('9. REPEATED retains exact same class', function () {
    $education = OrphanEducation::create([
        'orphan_id' => $this->orphan->id,
        'institution_id' => $this->westernSchool->id,
        'orphan_class_id' => $this->p4->id,
        'academic_session' => '2025/2026',
        'is_current' => true,
        'started_at' => now()->subYear()->toDateString(),
    ]);

    $successor = $this->service->progress(
        $education,
        AcademicProgressionDecision::REPEATED,
        ['academic_session' => '2026/2027'],
        $this->admin
    );

    expect($successor->orphan_class_id)->toBe($this->p4->id);
});

test('10. REPEATED with different class fails', function () {
    $education = OrphanEducation::create([
        'orphan_id' => $this->orphan->id,
        'institution_id' => $this->westernSchool->id,
        'orphan_class_id' => $this->p4->id,
        'academic_session' => '2025/2026',
        'is_current' => true,
        'started_at' => now()->subYear()->toDateString(),
    ]);

    expect(fn () => $this->service->progress(
        $education,
        AcademicProgressionDecision::REPEATED,
        ['new_class_id' => $this->p5->id, 'academic_session' => '2026/2027'],
        $this->admin
    ))->toThrow(\DomainException::class, 'match the current class');
});

test('11. DEMOTED uses previous sequential class', function () {
    $education = OrphanEducation::create([
        'orphan_id' => $this->orphan->id,
        'institution_id' => $this->westernSchool->id,
        'orphan_class_id' => $this->p4->id,
        'academic_session' => '2025/2026',
        'is_current' => true,
        'started_at' => now()->subYear()->toDateString(),
    ]);

    $successor = $this->service->progress(
        $education,
        AcademicProgressionDecision::DEMOTED,
        ['new_class_id' => $this->p3->id, 'academic_session' => '2026/2027', 'reason' => 'Literacy remediation'],
        $this->admin
    );

    expect($successor->orphan_class_id)->toBe($this->p3->id);
});

test('12. DEMOTED requires reason', function () {
    $education = OrphanEducation::create([
        'orphan_id' => $this->orphan->id,
        'institution_id' => $this->westernSchool->id,
        'orphan_class_id' => $this->p4->id,
        'academic_session' => '2025/2026',
        'is_current' => true,
        'started_at' => now()->subYear()->toDateString(),
    ]);

    expect(fn () => $this->service->progress(
        $education,
        AcademicProgressionDecision::DEMOTED,
        ['new_class_id' => $this->p3->id, 'academic_session' => '2026/2027'],
        $this->admin
    ))->toThrow(\DomainException::class, 'justification reason');
});

test('13. GRADUATED creates no successor', function () {
    $education = OrphanEducation::create([
        'orphan_id' => $this->orphan->id,
        'institution_id' => $this->westernSchool->id,
        'orphan_class_id' => $this->ss3->id,
        'academic_session' => '2025/2026',
        'is_current' => true,
        'started_at' => now()->subYear()->toDateString(),
    ]);

    $this->service->progress(
        $education,
        AcademicProgressionDecision::GRADUATED,
        ['effective_date' => now()->toDateString(), 'reason' => 'Graduated'],
        $this->admin
    );

    expect(OrphanEducation::where('orphan_id', $this->orphan->id)->where('is_current', true)->count())->toBe(0);
});

test('14. malformed session 2024/2026 fails', function () {
    $education = OrphanEducation::create([
        'orphan_id' => $this->orphan->id,
        'institution_id' => $this->westernSchool->id,
        'orphan_class_id' => $this->p4->id,
        'academic_session' => '2025/2026',
        'is_current' => true,
        'started_at' => now()->subYear()->toDateString(),
    ]);

    expect(fn () => $this->service->progress(
        $education,
        AcademicProgressionDecision::PROMOTED,
        ['academic_session' => '2024/2026'],
        $this->admin
    ))->toThrow(\DomainException::class, 'exactly one year');
});

test('15. malformed session 2027/2029 fails', function () {
    $education = OrphanEducation::create([
        'orphan_id' => $this->orphan->id,
        'institution_id' => $this->westernSchool->id,
        'orphan_class_id' => $this->p4->id,
        'academic_session' => '2025/2026',
        'is_current' => true,
        'started_at' => now()->subYear()->toDateString(),
    ]);

    expect(fn () => $this->service->progress(
        $education,
        AcademicProgressionDecision::PROMOTED,
        ['academic_session' => '2027/2029'],
        $this->admin
    ))->toThrow(\DomainException::class);
});

test('16. non-sequential but valid-format session fails without override', function () {
    $education = OrphanEducation::create([
        'orphan_id' => $this->orphan->id,
        'institution_id' => $this->westernSchool->id,
        'orphan_class_id' => $this->p4->id,
        'academic_session' => '2025/2026',
        'is_current' => true,
        'started_at' => now()->subYear()->toDateString(),
    ]);

    expect(fn () => $this->service->progress(
        $education,
        AcademicProgressionDecision::PROMOTED,
        ['academic_session' => '2028/2029'],
        $this->admin
    ))->toThrow(\DomainException::class, 'non-sequential');
});

test('17. valid next session succeeds', function () {
    $education = OrphanEducation::create([
        'orphan_id' => $this->orphan->id,
        'institution_id' => $this->westernSchool->id,
        'orphan_class_id' => $this->p4->id,
        'academic_session' => '2025/2026',
        'is_current' => true,
        'started_at' => now()->subYear()->toDateString(),
    ]);

    $successor = $this->service->progress(
        $education,
        AcademicProgressionDecision::PROMOTED,
        ['academic_session' => '2026/2027'],
        $this->admin
    );

    expect($successor->academic_session)->toBe('2026/2027');
});

test('18. unauthorized override fails', function () {
    $education = OrphanEducation::create([
        'orphan_id' => $this->orphan->id,
        'institution_id' => $this->westernSchool->id,
        'orphan_class_id' => $this->p4->id,
        'academic_session' => '2025/2026',
        'is_current' => true,
        'started_at' => now()->subYear()->toDateString(),
    ]);

    expect(fn () => $this->service->progress(
        $education,
        AcademicProgressionDecision::PROMOTED,
        ['academic_session' => '2028/2029', 'is_session_override' => true, 'session_override_reason' => 'Override test'],
        $this->coordinator
    ))->toThrow(\DomainException::class, 'permission');
});

test('19. authorized override requires reason and is audited', function () {
    $education = OrphanEducation::create([
        'orphan_id' => $this->orphan->id,
        'institution_id' => $this->westernSchool->id,
        'orphan_class_id' => $this->p4->id,
        'academic_session' => '2025/2026',
        'is_current' => true,
        'started_at' => now()->subYear()->toDateString(),
    ]);

    $successor = $this->service->progress(
        $education,
        AcademicProgressionDecision::PROMOTED,
        ['academic_session' => '2028/2029', 'is_session_override' => true, 'session_override_reason' => 'Double promotion authorized by Board'],
        $this->admin
    );

    expect($successor->academic_session)->toBe('2028/2029')
        ->and($education->fresh()->progression_reason)->toBe('Double promotion authorized by Board')
        ->and($education->fresh()->recorded_by_id)->toBe($this->admin->id);
});

test('20. tampered Livewire target_class_id is rejected by service', function () {
    $education = OrphanEducation::create([
        'orphan_id' => $this->orphan->id,
        'institution_id' => $this->westernSchool->id,
        'orphan_class_id' => $this->p4->id,
        'academic_session' => '2025/2026',
        'is_current' => true,
        'started_at' => now()->subYear()->toDateString(),
    ]);

    // Service strictly enforces next sequential class Primary 5 even if tampered Primary 3 ID is passed
    expect(fn () => $this->service->progress(
        $education,
        AcademicProgressionDecision::PROMOTED,
        ['new_class_id' => $this->p3->id, 'academic_session' => '2026/2027'],
        $this->admin
    ))->toThrow(\DomainException::class, 'exact next sequential class');
});

test('21. failed progression leaves original enrollment untouched', function () {
    $education = OrphanEducation::create([
        'orphan_id' => $this->orphan->id,
        'institution_id' => $this->westernSchool->id,
        'orphan_class_id' => $this->p4->id,
        'academic_session' => '2025/2026',
        'is_current' => true,
        'started_at' => now()->subYear()->toDateString(),
    ]);

    try {
        $this->service->progress(
            $education,
            AcademicProgressionDecision::PROMOTED,
            ['new_class_id' => $this->p3->id, 'academic_session' => '2026/2027'],
            $this->admin
        );
    } catch (\DomainException $e) {
        // Expected
    }

    $fresh = $education->fresh();
    expect($fresh->is_current)->toBeTrue()
        ->and($fresh->ended_at)->toBeNull()
        ->and($fresh->progression_decision)->toBeNull()
        ->and(OrphanEducation::where('orphan_id', $this->orphan->id)->count())->toBe(1);
});

test('22. at most one current enrollment remains per orphan/institution', function () {
    $education = OrphanEducation::create([
        'orphan_id' => $this->orphan->id,
        'institution_id' => $this->westernSchool->id,
        'orphan_class_id' => $this->p4->id,
        'academic_session' => '2025/2026',
        'is_current' => true,
        'started_at' => now()->subYear()->toDateString(),
    ]);

    $this->service->progress(
        $education,
        AcademicProgressionDecision::PROMOTED,
        ['academic_session' => '2026/2027'],
        $this->admin
    );

    expect(OrphanEducation::where('orphan_id', $this->orphan->id)->where('institution_id', $this->westernSchool->id)->where('is_current', true)->count())->toBe(1);
});

test('23. historical financial records remain attached to original enrollment', function () {
    $education = OrphanEducation::create([
        'orphan_id' => $this->orphan->id,
        'institution_id' => $this->westernSchool->id,
        'orphan_class_id' => $this->p4->id,
        'academic_session' => '2025/2026',
        'is_current' => true,
        'started_at' => now()->subYear()->toDateString(),
    ]);

    $invoice = EducationFeeInvoice::create([
        'orphan_education_id' => $education->id,
        'amount' => 15000.00,
        'due_date' => now()->addDays(30)->toDateString(),
        'period' => 'Term 1',
        'issued_at' => now()->toDateString(),
    ]);

    $successor = $this->service->progress(
        $education,
        AcademicProgressionDecision::PROMOTED,
        ['academic_session' => '2026/2027'],
        $this->admin
    );

    expect($invoice->fresh()->orphan_education_id)->toBe($education->id)
        ->and($successor->invoices()->count())->toBe(0);
});
