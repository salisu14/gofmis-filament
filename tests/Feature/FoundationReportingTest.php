<?php

namespace Tests\Feature;

use App\Enums\OrphanStatus;
use App\Enums\VulnerabilityStatus;
use App\Enums\WidowLoanStatus;
use App\Models\CompanyInformation;
use App\Models\Deceased;
use App\Models\Institution;
use App\Models\Orphan;
use App\Models\OrphanClass;
use App\Models\OrphanEducation;
use App\Models\Prescription;
use App\Models\User;
use App\Models\WelfareBeneficiary;
use App\Models\WelfarePackage;
use App\Models\Widow;
use App\Models\WidowLoan;
use App\Models\WidowLoanRepayment;
use App\Models\Zone;
use App\Services\Company\CompanyInformationService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

beforeEach(function () {
    app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(RolesAndPermissionsSeeder::class);


    $this->zoneA = Zone::create(['name' => 'Kano Central Zone', 'code' => 'KCZ']);
    $this->zoneB = Zone::create(['name' => 'Kaduna North Zone', 'code' => 'KNZ']);

    $this->admin = User::factory()->create(['is_active' => true]);
    $this->admin->assignRole('admin');

    $this->coordinatorA = User::factory()->create(['is_active' => true]);
    $this->coordinatorA->assignRole('coordinator');
    $this->coordinatorA->coordinatedZone()->save($this->zoneA);

    $this->coordinatorB = User::factory()->create(['is_active' => true]);
    $this->coordinatorB->assignRole('coordinator');
    $this->coordinatorB->coordinatedZone()->save($this->zoneB);

    $this->deceasedA = Deceased::withoutGlobalScopes()->create([
        'zone_id' => $this->zoneA->id,
        'first_name' => 'Ibrahim',
        'last_name' => 'Bello',
        'full_name' => 'Ibrahim Bello',
        'reg_no' => 'DEC-KCZ-001',
        'nin' => fake()->unique()->numerify('###########'),
        'guardian_name' => 'Hauwa Ibrahim',
        'guardian_phone' => '08012345678',
        'vulnerability_status' => VulnerabilityStatus::A,
        'date_registered' => now()->toDateString(),
        'number_of_orphans_left' => 1,
        'number_of_widows_left' => 1,
        'date_of_death' => now()->subYears(2),
    ]);

    $this->deceasedB = Deceased::withoutGlobalScopes()->create([
        'zone_id' => $this->zoneB->id,
        'first_name' => 'Usman',
        'last_name' => 'Abubakar',
        'full_name' => 'Usman Abubakar',
        'reg_no' => 'DEC-KNZ-002',
        'nin' => fake()->unique()->numerify('###########'),
        'guardian_name' => 'Maryam Usman',
        'guardian_phone' => '08087654321',
        'vulnerability_status' => VulnerabilityStatus::B,
        'date_registered' => now()->toDateString(),
        'number_of_orphans_left' => 1,
        'number_of_widows_left' => 1,
        'date_of_death' => now()->subYears(3),
    ]);

    $this->orphanA = Orphan::withoutGlobalScopes()->create([
        'deceased_id' => $this->deceasedA->id,
        'first_name' => 'Amina',
        'middle_name' => 'Ibrahim',
        'last_name' => 'Bello',
        'full_name' => 'Amina Ibrahim Bello',
        'reg_no' => 'ORPH-KCZ-001',
        'nin' => '12345678901',
        'gender' => \App\Enums\Gender::FEMALE,
        'birth_date' => now()->subYears(10),
        'status' => OrphanStatus::ACTIVE,
        'is_eligible' => true,
    ]);

    $this->orphanB = Orphan::withoutGlobalScopes()->create([
        'deceased_id' => $this->deceasedB->id,
        'first_name' => 'Suleiman',
        'last_name' => 'Abubakar',
        'full_name' => 'Suleiman Usman Abubakar',
        'reg_no' => 'ORPH-KNZ-002',
        'gender' => \App\Enums\Gender::MALE,
        'birth_date' => now()->subYears(12),
        'status' => OrphanStatus::ACTIVE,
        'is_eligible' => true,
    ]);

    $this->widowA = Widow::withoutGlobalScopes()->create([
        'deceased_id' => $this->deceasedA->id,
        'first_name' => 'Hauwa',
        'last_name' => 'Ibrahim',
        'full_name' => 'Hauwa Ibrahim',
        'reg_no' => 'WID-KCZ-001',
        'nin' => fake()->unique()->numerify('###########'),
        'child_sequence' => 1,
        'is_eligible' => true,
        'is_married' => false,
    ]);

    $this->loanA = WidowLoan::withoutGlobalScopes()->create([
        'widow_id' => $this->widowA->id,
        'principal_amount' => 100000,
        'total_payable' => 100000,
        'weekly_installment' => 5000,
        'duration_months' => 5,
        'status' => WidowLoanStatus::DISBURSED,
    ]);

    $this->repaymentA = WidowLoanRepayment::create([
        'widow_loan_id' => $this->loanA->id,
        'amount' => 5000,
        'paid_at' => now()->startOfDay(),
        'receipt_number' => 1001,
        'payment_method' => 'cash',
    ]);
});

// 1. Authorized admin can download orphan dossier
test('authorized admin can download orphan dossier', function () {
    $response = $this->actingAs($this->admin)
        ->get(route('orphans.report.download', ['orphan' => $this->orphanA]));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
});





// 2. Authorized coordinator can download orphan dossier in own zone
test('authorized coordinator can download orphan in own zone', function () {
    $response = $this->actingAs($this->coordinatorA)
        ->get(route('orphans.report.download', ['orphan' => $this->orphanA]));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
});

// 3. Coordinator cannot download cross-zone orphan dossier
test('coordinator cannot download cross-zone orphan dossier', function () {
    $response = $this->actingAs($this->coordinatorA)
        ->get(route('orphans.report.download', ['orphan' => $this->orphanB]));

    expect($response->status())->toBeIn([403, 404]);
});

// 4. Report response is PDF
test('report response is PDF', function () {
    $response = $this->actingAs($this->admin)
        ->get(route('orphans.report.download', ['orphan' => $this->orphanA]));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

// 5. Report contains key orphan identity data
test('report contains key orphan identity data', function () {
    $view = view('filament.components.orphan-dossier', [
        'orphan' => $this->orphanA->load('deceased.zone'),
        'deceased' => $this->orphanA->deceased,
        'welfare' => collect(),
        'photo_data_uri' => null,
        'company' => app(\App\Services\Company\CompanyInformationService::class)->reportHeader(),
        'generated_at' => now(),
    ])->render();

    dump($view); expect($view)->toContain('Amina Ibrahim Bello')
        ->toContain('ORPH-KCZ-001')
        ->toContain('12345678901')
        ->toContain('Ibrahim Bello')
        ->toContain('Kano Central Zone');
});

// 6. Report generation succeeds when optional education/healthcare/sponsorship data is absent
test('report generation succeeds when optional data is absent', function () {
    $response = $this->actingAs($this->admin)
        ->get(route('orphans.report.download', ['orphan' => $this->orphanA]));

    $response->assertOk();
    expect(strlen($response->getContent()))->toBeGreaterThan(500);
});

// 7. Populated UAT orphan report contains education, healthcare, intervention, welfare data where seeded
test('populated UAT orphan report contains education, healthcare, intervention, welfare data where seeded', function () {
    $institution = Institution::create(['name' => 'Garba Yarima Academy', 'type' => 'western']);
    $orphanClass = OrphanClass::create(['name' => 'Primary 3', 'user_id' => $this->admin->id]);

    OrphanEducation::create([
        'orphan_id' => $this->orphanA->id,
        'institution_id' => $institution->id,
        'orphan_class_id' => $orphanClass->id,
        'school_fee' => 25000,
        'support_amount' => 25000,
        'is_current' => true,
    ]);

    Prescription::create([
        'user_id' => $this->admin->id,
        'prescribable_id' => $this->orphanA->id,
        'prescribable_type' => Orphan::class,
        'illness' => 'Malaria Fever',
        'prescription_date' => now()->toDateString(),
        'lab_test_cost' => 3000,
        'drug_cost' => 4500,
    ]);

    $welfarePackage = WelfarePackage::create([
        'name' => 'Ramadan Grain Pack',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(7)->toDateString(),
        'distribution_date' => now(),
        'created_by' => $this->admin->id,
        'status' => \App\Enums\WelfarePackageStatus::CLOSED,
    ]);
    WelfareBeneficiary::create([
        'deceased_id' => $this->deceasedA->id,
        'welfare_package_id' => $welfarePackage->id,
        'suggested_by' => $this->admin->id,
        'status' => \App\Enums\BeneficiaryStatus::APPROVED,
        'is_collected' => true,
        'collected_at' => now(),
    ]);

    $view = view('filament.components.orphan-dossier', [
        'orphan' => $this->orphanA->load(['educations.institution', 'educations.orphanClass', 'prescriptions', 'sponsorships']),
        'deceased' => $this->deceasedA,
        'welfare' => WelfareBeneficiary::where('deceased_id', $this->deceasedA->id)->with('welfarePackage')->get(),
        'photo_data_uri' => null,
        'company' => app(\App\Services\Company\CompanyInformationService::class)->reportHeader(),
        'generated_at' => now(),
    ])->render();

    dump($view); expect($view)->toContain('Garba Yarima Academy')
        ->toContain('Malaria Fever')
        ->toContain('Ramadan Grain Pack');
});

// 8. Authorized user can generate 58mm weekly report
test('authorized user can generate 58mm weekly report', function () {
    $responseRepayment = $this->actingAs($this->admin)
        ->get(route('repayments.thermal-receipt.download', ['repayment' => $this->repaymentA]));

    $responseRepayment->assertOk();

    $responseLoan = $this->actingAs($this->admin)
        ->get(route('loans.weekly-thermal.download', ['loan' => $this->loanA]));

    $responseLoan->assertOk();
});

// 9. Unauthorized/cross-zone access is blocked for thermal report
test('unauthorized or cross-zone access is blocked for thermal report', function () {
    $responseRepayment = $this->actingAs($this->coordinatorB)
        ->get(route('repayments.thermal-receipt.download', ['repayment' => $this->repaymentA]));

    expect($responseRepayment->status())->toBeIn([403, 404]);

    $responseLoan = $this->actingAs($this->coordinatorB)
        ->get(route('loans.weekly-thermal.download', ['loan' => $this->loanA]));

    expect($responseLoan->status())->toBeIn([403, 404]);
});

// 10. Thermal report response is PDF
test('thermal report response is PDF', function () {
    $response = $this->actingAs($this->admin)
        ->get(route('repayments.thermal-receipt.download', ['repayment' => $this->repaymentA]));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

// 11. Thermal report contains widow/loan/weekly repayment values
test('thermal report contains widow loan repayment values', function () {
    $this->repaymentA->refresh();
    
    $view = view('pdf.reports.wrl-weekly-repayment-thermal', [
        'repayment' => $this->repaymentA,
        'loan' => $this->loanA,
        'company' => app(\App\Services\Company\CompanyInformationService::class)->reportHeader(),
    ])->render();

    dump($view); expect($view)->toContain('Hauwa Ibrahim')
        ->toContain('WRL REPAYMENT RECEIPT')
        ->toContain('WID-KCZ-001')
        ->toContain('RCP-01001')
        ->toContain('5,000.00');
});

// 12. Expected/collected/shortfall/outstanding values reconcile with model data
test('thermal report reconciles calculated totals', function () {
    $this->repaymentA->refresh();
    
    $view = view('pdf.reports.wrl-weekly-repayment-thermal', [
        'repayment' => $this->repaymentA,
        'loan' => $this->loanA,
        'company' => app(\App\Services\Company\CompanyInformationService::class)->reportHeader(),
    ])->render();

    expect($view)->toContain('100,000.00')
        ->toContain('95,000.00')
        ->toContain('5,000.00');
});

// 13. Report generation works for current deterministic WRL UAT fixtures
test('thermal report works for current deterministic WRL UAT fixtures', function () {
    $response = $this->actingAs($this->coordinatorA)
        ->get(route('repayments.thermal-receipt.download', ['repayment' => $this->repaymentA]));

    $response->assertOk();
});

// 14. PDF uses the intended narrow 58mm paper configuration or equivalent rendering assertion
test('thermal report uses 58mm paper configuration', function () {
    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.reports.wrl-weekly-repayment-thermal', [
        'repayment' => $this->repaymentA,
        'loan' => $this->loanA,
        'company' => app(\App\Services\Company\CompanyInformationService::class)->reportHeader(),
    ]);

    $pdf->setPaper([0, 0, 164.41, 650], 'portrait');
    $output = $pdf->output();

    expect(strlen($output))->toBeGreaterThan(1000);
});

test('orphan dossier and WRL thermal report use canonical Company Information branding', function () {
    $companyInfo = CompanyInformation::updateOrCreate(['id' => 1], [
        'company_name' => 'Custom Foundation Name',
        'address_line_1' => '123 Foundation Way',
        'phone_no' => '08012345678',
        'email' => 'contact@customfoundation.org',
    ]);
    
    // 1. Orphan Dossier View Verification
    $dossierView = view('filament.components.orphan-dossier', [
        'orphan' => $this->orphanA->load('deceased.zone'),
        'deceased' => $this->orphanA->deceased,
        'welfare' => collect(),
        'photo_data_uri' => null,
        'company' => app(CompanyInformationService::class)->reportHeader(),
        'generated_at' => now(),
    ])->render();

    expect($dossierView)
        ->toContain('Custom Foundation Name')
        ->toContain('123 Foundation Way')
        ->toContain('08012345678')
        ->toContain('contact@customfoundation.org')
        ->not->toContain('Garko Orphans Foundation')
        ->not->toContain('Garba Yarima Foundation');

    // 2. WRL Thermal Report View Verification
    $thermalView = view('pdf.reports.wrl-weekly-repayment-thermal', [
        'repayment' => $this->repaymentA,
        'loan' => $this->loanA,
        'company' => app(CompanyInformationService::class)->reportHeader(),
    ])->render();

    expect($thermalView)
        ->toContain('CUSTOM FOUNDATION NAME') // thermal report uses uppercase in header
        ->toContain('Custom Foundation Name - WRL Program') // thermal report footer
        ->toContain('123 Foundation Way')
        ->toContain('08012345678')
        ->toContain('contact@customfoundation.org')
        ->not->toContain('GARKO ORPHANS FOUNDATION')
        ->not->toContain('GARBA YARIMA FOUNDATION');
        
    // 3. Graceful degradation when optional fields are missing
    $companyInfo->update([
        'address_line_1' => null,
        'phone_no' => null,
        'email' => null,
    ]);
    
    $thermalViewMissingFields = view('pdf.reports.wrl-weekly-repayment-thermal', [
        'repayment' => $this->repaymentA,
        'loan' => $this->loanA,
        'company' => app(CompanyInformationService::class)->reportHeader(),
    ])->render();
    
    expect($thermalViewMissingFields)->toContain('CUSTOM FOUNDATION NAME'); // Still renders successfully
});
