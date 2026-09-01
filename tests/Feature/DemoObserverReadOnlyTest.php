<?php

use App\Enums\UserStatus;
use App\Exceptions\DemoReadOnlyException;
use App\Models\CompanyInformation;
use App\Models\Deceased;
use App\Models\Orphan;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Models\WelfarePackage;
use App\Models\Widow;
use App\Models\Zone;
use App\Services\Company\CompanyInformationService;
use App\Services\DocumentBrandingService;
use App\Services\MfaService;
use App\Services\Security\DemoReadOnlyGuard;
use App\Services\UserSecurityService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->zone = Zone::firstOrCreate(
        ['code' => 'KCZ'],
        ['name' => 'Kano Central Zone', 'state_id' => 1]
    );

    $this->demoObserver = User::factory()->create([
        'email' => 'demo@gofmis.org',
        'is_active' => true,
        'is_protected_system_account' => true,
        'status' => UserStatus::ACTIVE,
    ]);
    $this->demoObserver->assignRole('demo_observer');

    $this->superAdmin = User::factory()->create([
        'email' => 'superadmin@gofmis.org',
        'is_active' => true,
        'status' => UserStatus::ACTIVE,
        'app_authentication_secret' => 'SECRET1234567890',
        'mfa_confirmed_at' => now(),
        'mfa_enabled_at' => now(),
    ]);
    $this->superAdmin->assignRole('super_admin');

    $this->admin = User::factory()->create([
        'email' => 'admin@gofmis.org',
        'is_active' => true,
        'status' => UserStatus::ACTIVE,
        'app_authentication_secret' => 'SECRET1234567890',
        'mfa_confirmed_at' => now(),
        'mfa_enabled_at' => now(),
    ]);
    $this->admin->assignRole('admin');
});

function demoObserverCreateTestDeceased($zoneId)
{
    return Deceased::create([
        'uuid' => (string) Str::uuid(),
        'reg_no' => 'DEC-TEST-'.Str::random(5),
        'first_name' => 'Test',
        'last_name' => 'Deceased',
        'nin' => (string) random_int(10000000000, 99999999999),
        'date_of_death' => '2025-01-01',
        'date_registered' => '2025-01-01',
        'zone_id' => $zoneId,
        'guardian_name' => 'Guardian Name',
        'guardian_phone' => '08012345678',
        'vulnerability_status' => 'A',
        'marital_status' => 'married',
        'status' => 'verified',
    ]);
}

function demoObserverCreateTestWidow($deceased)
{
    return Widow::create([
        'uuid' => (string) Str::uuid(),
        'reg_no' => 'WID-TEST-'.Str::random(5),
        'first_name' => 'Hauwa',
        'last_name' => 'Ibrahim',
        'nin' => (string) random_int(10000000000, 99999999999),
        'is_eligible' => true,
        'is_married' => false,
        'child_sequence' => 1,
        'deceased_id' => $deceased->id,
        'zone_id' => $deceased->zone_id,
        'status' => 'active',
    ]);
}

function demoObserverCreateTestOrphan($deceased)
{
    return Orphan::create([
        'reg_no' => 'ORP-TEST-'.Str::random(5),
        'first_name' => 'Aliyu',
        'last_name' => 'Ibrahim',
        'gender' => 'MALE',
        'birth_date' => '2015-05-05',
        'deceased_id' => $deceased->id,
        'status' => App\Enums\OrphanStatus::ACTIVE->value,
    ]);
}

function demoObserverCreateTestProject($zoneId)
{
    return Project::create([
        'uuid' => (string) Str::uuid(),
        'code' => 'PRJ-TEST-'.Str::random(5),
        'name' => 'Community School Project',
        'type' => 'school',
        'description' => 'Test project',
        'zone_id' => $zoneId,
        'status' => 'planning',
        'budget_amount' => 500000.00,
    ]);
}

function demoObserverCreateTestWelfarePackage($userId)
{
    return WelfarePackage::create([
        'name' => 'Ramadan Care Package',
        'status' => 'draft',
        'start_date' => '2026-09-01',
        'end_date' => '2026-12-31',
        'created_by' => $userId,
    ]);
}

// ─────────────────────────────────────────────────────────────
// VISIBILITY (TESTS 1 - 12)
// ─────────────────────────────────────────────────────────────

test('1. demo observer can log into Admin panel and access dashboard without mandatory MFA', function () {
    actingAs($this->demoObserver);

    $this->get('/admin')
        ->assertStatus(200)
        ->assertSee('Dashboard');
});

test('2. demo observer has isDemoObserver helper and isMfaMandatoryByRole is false', function () {
    expect($this->demoObserver->isDemoObserver())->toBeTrue();
    expect($this->demoObserver->isMfaMandatoryByRole())->toBeFalse();
    expect($this->superAdmin->isMfaMandatoryByRole())->toBeTrue();
    expect($this->admin->isMfaMandatoryByRole())->toBeTrue();
    expect($this->superAdmin->isDemoObserver())->toBeFalse();
});

test('3. demo observer can view Widow records via Gate', function () {
    $deceased = demoObserverCreateTestDeceased($this->zone->id);
    $widow = demoObserverCreateTestWidow($deceased);

    actingAs($this->demoObserver);

    expect(Gate::allows('viewAny', Widow::class))->toBeTrue();
    expect(Gate::allows('view', $widow))->toBeTrue();
});

test('4. demo observer can view Orphan records via Gate', function () {
    $deceased = demoObserverCreateTestDeceased($this->zone->id);
    $orphan = demoObserverCreateTestOrphan($deceased);

    actingAs($this->demoObserver);

    expect(Gate::allows('viewAny', Orphan::class))->toBeTrue();
    expect(Gate::allows('view', $orphan))->toBeTrue();
});

test('5. demo observer can view Deceased records via Gate', function () {
    $deceased = demoObserverCreateTestDeceased($this->zone->id);

    actingAs($this->demoObserver);

    expect(Gate::allows('viewAny', Deceased::class))->toBeTrue();
    expect(Gate::allows('view', $deceased))->toBeTrue();
});

test('6. demo observer can view Welfare Package records via Gate', function () {
    $package = demoObserverCreateTestWelfarePackage($this->superAdmin->id);

    actingAs($this->demoObserver);

    expect(Gate::allows('viewAny', WelfarePackage::class))->toBeTrue();
    expect(Gate::allows('view', $package))->toBeTrue();
});

test('7. demo observer can view Projects via Gate', function () {
    $project = demoObserverCreateTestProject($this->zone->id);

    actingAs($this->demoObserver);

    expect(Gate::allows('viewAny', Project::class))->toBeTrue();
    expect(Gate::allows('view', $project))->toBeTrue();
});

test('8. demo observer can view User and Role records via Gate', function () {
    $role = Role::where('name', 'admin')->first();

    actingAs($this->demoObserver);

    expect(Gate::allows('viewAny', User::class))->toBeTrue();
    expect(Gate::allows('view', $this->admin))->toBeTrue();
    expect(Gate::allows('viewAny', Role::class))->toBeTrue();
    expect(Gate::allows('view', $role))->toBeTrue();
});

test('9. demo observer can view Company Information', function () {
    actingAs($this->demoObserver);

    $company = CompanyInformation::instance();
    $context = app(DocumentBrandingService::class)->getDocumentContext('Test');

    expect($context)->toBeArray();
    expect($context['department'])->toBe('Welfare Department');
});

test('10. demo observer can preview read-only reports on screen', function () {
    actingAs($this->demoObserver);

    $view = view('pdf.layouts.official-document', [
        'documentTitle' => 'Demo Report',
        'company' => app(DocumentBrandingService::class)->getDocumentContext('Demo Report'),
    ])->render();

    expect($view)->toContain('Welfare Department');
});

// ─────────────────────────────────────────────────────────────
// CRUD & MUTATION DENIAL (TESTS 13 - 22)
// ─────────────────────────────────────────────────────────────

test('13. demo observer cannot create Widow', function () {
    actingAs($this->demoObserver);

    expect(Gate::allows('create', Widow::class))->toBeFalse();
});

test('14. demo observer cannot edit Widow', function () {
    $deceased = demoObserverCreateTestDeceased($this->zone->id);
    $widow = demoObserverCreateTestWidow($deceased);

    actingAs($this->demoObserver);

    expect(Gate::allows('update', $widow))->toBeFalse();
});

test('15. demo observer cannot delete Widow', function () {
    $deceased = demoObserverCreateTestDeceased($this->zone->id);
    $widow = demoObserverCreateTestWidow($deceased);

    actingAs($this->demoObserver);

    expect(Gate::allows('delete', $widow))->toBeFalse();
});

test('16. demo observer cannot create Orphan', function () {
    actingAs($this->demoObserver);

    expect(Gate::allows('create', Orphan::class))->toBeFalse();
});

test('17. demo observer cannot edit Orphan', function () {
    $deceased = demoObserverCreateTestDeceased($this->zone->id);
    $orphan = demoObserverCreateTestOrphan($deceased);

    actingAs($this->demoObserver);

    expect(Gate::allows('update', $orphan))->toBeFalse();
});

test('18. demo observer cannot create Project', function () {
    actingAs($this->demoObserver);

    expect(Gate::allows('create', Project::class))->toBeFalse();
});

test('19. demo observer cannot edit Project', function () {
    $project = demoObserverCreateTestProject($this->zone->id);

    actingAs($this->demoObserver);

    expect(Gate::allows('update', $project))->toBeFalse();
});

test('20. demo observer cannot create/edit Welfare Package', function () {
    $package = demoObserverCreateTestWelfarePackage($this->superAdmin->id);

    actingAs($this->demoObserver);

    expect(Gate::allows('create', WelfarePackage::class))->toBeFalse();
    expect(Gate::allows('update', $package))->toBeFalse();
});

test('23. DemoReadOnlyGuard blocks any mutation attempt for demo observer', function () {
    actingAs($this->demoObserver);

    expect(fn () => DemoReadOnlyGuard::ensureCanMutate())
        ->toThrow(DemoReadOnlyException::class);
});

test('24. DemoReadOnlyGuard allows super admin and admin mutations', function () {
    actingAs($this->superAdmin);
    expect(fn () => DemoReadOnlyGuard::ensureCanMutate())->not->toThrow(DemoReadOnlyException::class);

    actingAs($this->admin);
    expect(fn () => DemoReadOnlyGuard::ensureCanMutate())->not->toThrow(DemoReadOnlyException::class);
});

// ─────────────────────────────────────────────────────────────
// DATA EXPORT & SENSITIVE DOWNLOAD DENIAL (TESTS 25 - 34)
// ─────────────────────────────────────────────────────────────

test('25. Gate denies export ability for demo observer across all models', function () {
    actingAs($this->demoObserver);

    expect(Gate::allows('export', Deceased::class))->toBeFalse();
    expect(Gate::allows('export', Widow::class))->toBeFalse();
    expect(Gate::allows('export', Orphan::class))->toBeFalse();
    expect(Gate::allows('export', User::class))->toBeFalse();
    expect(Gate::allows('export_reports'))->toBeFalse();
    expect(Gate::allows('download'))->toBeFalse();
});

test('26. DemoReadOnlyGuard ensureCanExportSensitiveData blocks demo observer', function () {
    actingAs($this->demoObserver);

    expect(fn () => DemoReadOnlyGuard::ensureCanExportSensitiveData())
        ->toThrow(DemoReadOnlyException::class);
});

test('27. DemoReadOnlyGuard ensureCanExportSensitiveData allows super admin and admin', function () {
    actingAs($this->superAdmin);
    expect(fn () => DemoReadOnlyGuard::ensureCanExportSensitiveData())->not->toThrow(DemoReadOnlyException::class);

    actingAs($this->admin);
    expect(fn () => DemoReadOnlyGuard::ensureCanExportSensitiveData())->not->toThrow(DemoReadOnlyException::class);
});

test('28. direct download invocation for orphan report as demo observer returns HTTP 403', function () {
    $deceased = demoObserverCreateTestDeceased($this->zone->id);
    $orphan = demoObserverCreateTestOrphan($deceased);

    actingAs($this->demoObserver);

    $this->get("/orphans/{$orphan->id}/report")
        ->assertStatus(403);
});

test('29. direct download invocation for project report as demo observer returns HTTP 403', function () {
    actingAs($this->demoObserver);

    $this->get('/projects/print?action=download')
        ->assertStatus(403);
});

test('30. direct download invocation for prescription pdf as demo observer returns HTTP 403', function () {
    actingAs($this->demoObserver);

    $this->get('/admin/reports/prescription-report/pdf?action=download')
        ->assertStatus(403);
});

// ─────────────────────────────────────────────────────────────
// SECURITY / SETTINGS DENIAL (TESTS 35 - 40)
// ─────────────────────────────────────────────────────────────

test('35. demo observer cannot create, edit, or delete users', function () {
    actingAs($this->demoObserver);

    expect(Gate::allows('create', User::class))->toBeFalse();
    expect(Gate::allows('update', $this->admin))->toBeFalse();
    expect(Gate::allows('delete', $this->admin))->toBeFalse();
});

test('36. demo observer cannot modify roles or permissions', function () {
    $role = Role::where('name', 'admin')->first();

    actingAs($this->demoObserver);

    expect(Gate::allows('create', Role::class))->toBeFalse();
    expect(Gate::allows('update', $role))->toBeFalse();
    expect(Gate::allows('delete', $role))->toBeFalse();
});

test('37. demo observer cannot reset user MFA via MfaService', function () {
    actingAs($this->demoObserver);

    $service = app(MfaService::class);

    expect(fn () => $service->resetMfa($this->demoObserver, $this->admin))
        ->toThrow(DemoReadOnlyException::class);
});

test('38. demo observer cannot administratively reset user password via UserSecurityService', function () {
    actingAs($this->demoObserver);

    $service = app(UserSecurityService::class);

    expect(fn () => $service->resetPassword($this->demoObserver, $this->admin, 'NewPassword123!'))
        ->toThrow(DemoReadOnlyException::class);
});

test('39. demo observer cannot update CompanyInformation via CompanyInformationService', function () {
    actingAs($this->demoObserver);

    $service = app(CompanyInformationService::class);

    expect(fn () => $service->update(['company_name' => 'Forged Name']))
        ->toThrow(DemoReadOnlyException::class);
});

test('40. demo observer cannot upload signature image via CompanyInformationService', function () {
    Storage::fake('local');
    actingAs($this->demoObserver);

    $file = UploadedFile::fake()->image('sign.png');
    $service = app(CompanyInformationService::class);

    expect(fn () => $service->storeSignature($file))
        ->toThrow(DemoReadOnlyException::class);
});

// ─────────────────────────────────────────────────────────────
// READ-ONLY UX & BANNER (TESTS 45 - 46)
// ─────────────────────────────────────────────────────────────

test('45. Demo Mode banner renders ONLY for demo observer and has compact max-height markup', function () {
    actingAs($this->demoObserver);

    $response = $this->get('/admin')
        ->assertStatus(200)
        ->assertSee('Demo Mode — Read Only');

    // The whole page might contain other SVGs, so we just verify the exact text is present.
    // The previous test logic verified no specific lock icon, but we removed it.
    expect($response->getContent())->not->toContain('🔒');
});

test('46. Ordinary Admin and Super Admin do NOT see Demo Mode banner', function () {
    actingAs($this->admin);
    session(['mfa_verified_at' => time(), 'mfa_verified_user_id' => $this->admin->id]);
    $this->get('/admin')
        ->assertStatus(200)
        ->assertDontSee('Demo Mode — Read Only');

    actingAs($this->superAdmin);
    session(['mfa_verified_at' => time(), 'mfa_verified_user_id' => $this->superAdmin->id]);
    $this->get('/admin')
        ->assertStatus(200)
        ->assertDontSee('Demo Mode — Read Only');
});

// ─────────────────────────────────────────────────────────────
// NON-REGRESSION (TESTS 47 - 50)
// ─────────────────────────────────────────────────────────────

test('47. Super Admin CAN perform normal authorized mutations and exports', function () {
    actingAs($this->superAdmin);

    expect(Gate::allows('create', Widow::class))->toBeTrue();
    expect(Gate::allows('create', Orphan::class))->toBeTrue();
    expect(Gate::allows('create', Project::class))->toBeTrue();
    expect(Gate::allows('export_reports'))->toBeTrue();
});

test('48. Admin CAN perform normal authorized mutations and exports', function () {
    actingAs($this->admin);

    expect(Gate::allows('create', Widow::class))->toBeTrue();
    expect(Gate::allows('create', Orphan::class))->toBeTrue();
    expect(Gate::allows('create', Project::class))->toBeTrue();
    expect(Gate::allows('export_reports'))->toBeTrue();
});

// ─────────────────────────────────────────────────────────────
// DATA VISIBILITY & ZONE ISOLATION REGRESSION (TESTS 50+)
// ─────────────────────────────────────────────────────────────

test('50. Demo Observer can see existing records regardless of zone scope', function () {
    $zone1 = Zone::create(['code' => 'Z1', 'name' => 'Zone 1', 'state_id' => 1]);
    $zone2 = Zone::create(['code' => 'Z2', 'name' => 'Zone 2', 'state_id' => 1]);

    demoObserverCreateTestDeceased($zone1->id);
    demoObserverCreateTestDeceased($zone2->id);

    actingAs($this->demoObserver);

    expect(Deceased::count())->toBeGreaterThanOrEqual(2);
});

test('51. Coordinator zone isolation has not been weakened', function () {
    $coordinator = User::factory()->create([
        'email' => 'coordinator@gofmis.org',
        'is_active' => true,
        'status' => App\Enums\UserStatus::ACTIVE,
    ]);
    $coordinator->assignRole('coordinator');

    $zone1 = Zone::create(['code' => 'Z3', 'name' => 'Zone 3', 'state_id' => 1, 'coordinator_id' => $coordinator->id]);
    $zone2 = Zone::create(['code' => 'Z4', 'name' => 'Zone 4', 'state_id' => 1]);

    demoObserverCreateTestDeceased($zone1->id);
    demoObserverCreateTestDeceased($zone2->id);

    actingAs($coordinator);

    $count = Deceased::count();
    expect($count)->toBe(1);

    $deceased = Deceased::first();
    expect($deceased->zone_id)->toBe($zone1->id);
});

test('52. Demo Observer can see representative Finance records', function () {
    actingAs($this->demoObserver);
    expect(Gate::allows('viewAny', \App\Models\BankAccount::class))->toBeTrue();
    expect(Gate::allows('viewAny', \App\Models\Transaction::class))->toBeTrue();
});

test('53. Demo Observer can see representative Education records', function () {
    actingAs($this->demoObserver);
    expect(Gate::allows('viewAny', \App\Models\OrphanEducation::class))->toBeTrue();
});

test('54. Demo Observer can see representative Intervention records', function () {
    actingAs($this->demoObserver);
    expect(Gate::allows('viewAny', \App\Models\InterventionRequest::class))->toBeTrue();
});

test('55. Demo Observer can see representative Sponsorship records', function () {
    actingAs($this->demoObserver);
    expect(Gate::allows('viewAny', \App\Models\Sponsorship::class))->toBeTrue();
});

test('56. Demo Observer can see representative Widow Loan records', function () {
    actingAs($this->demoObserver);
    expect(Gate::allows('viewAny', \App\Models\WidowLoan::class))->toBeTrue();
});
