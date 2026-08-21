<?php

namespace Tests\Feature;

use App\Enums\Gender;
use App\Enums\PrescriptionStatus;
use App\Models\Deceased;
use App\Models\Illness;
use App\Models\Orphan;
use App\Models\Prescription;
use App\Models\User;
use App\Models\Widow;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HealthcarePrescriptionReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $coordinator;

    protected Zone $zoneA;

    protected Deceased $deceased;

    protected Orphan $orphan;

    protected Widow $widow;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->zoneA = Zone::create(['name' => 'Report Zone', 'code' => 'RZ']);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole('super_admin');
        $this->admin->update(['mfa_confirmed_at' => now(), 'mfa_enabled_at' => now()]);
        session(['mfa_verified_at' => time(), 'mfa_verified_user_id' => $this->admin->id]);

        $this->coordinator = User::factory()->create([
            'is_active' => true,
        ]);
        $this->coordinator->assignRole('coordinator');
        $this->zoneA->update(['coordinator_id' => $this->coordinator->id]);

        $this->deceased = Deceased::factory()->create([
            'zone_id' => $this->zoneA->id,
            'first_name' => 'Deceased',
            'last_name' => 'Father',
            'reg_no' => 'DEC-RZ-01',
        ]);

        $this->orphan = Orphan::create([
            'deceased_id' => $this->deceased->id,
            'first_name' => 'OrphanOne',
            'last_name' => 'Test',
            'nin' => '99999999991',
            'reg_no' => 'ORP-RZ-01',
            'is_eligible' => true,
            'gender' => Gender::MALE,
        ]);

        $this->widow = Widow::create([
            'deceased_id' => $this->deceased->id,
            'child_sequence' => 1,
            'first_name' => 'WidowOne',
            'last_name' => 'Test',
            'nin' => '99999999992',
            'reg_no' => 'WID-RZ-01',
            'is_eligible' => true,
            'is_married' => false,
        ]);

        $illness = Illness::create(['name' => 'Typhoid Fever', 'category' => \App\Enums\IllnessCategory::Infectious]);

        Prescription::create([
            'prescribable_type' => Orphan::class,
            'prescribable_id' => $this->orphan->id,
            'illness_id' => $illness->id,
            'doctor_name' => 'Dr. Ahmed',
            'prescription_date' => now()->startOfMonth()->addDays(2),
            'lab_test_cost' => 1000.00,
            'drug_cost' => 2000.00,
            'user_id' => $this->admin->id,
            'status' => PrescriptionStatus::PENDING,
        ]);

        Prescription::create([
            'prescribable_type' => Widow::class,
            'prescribable_id' => $this->widow->id,
            'illness_id' => $illness->id,
            'doctor_name' => 'Dr. Fatima',
            'prescription_date' => now()->startOfMonth()->addDays(5),
            'lab_test_cost' => 3000.00,
            'drug_cost' => 4000.00,
            'user_id' => $this->admin->id,
            'status' => PrescriptionStatus::TREATED,
            'treated_at' => now()->startOfMonth()->addDays(6),
        ]);

        // Out of range prescription
        Prescription::create([
            'prescribable_type' => Orphan::class,
            'prescribable_id' => $this->orphan->id,
            'illness_id' => $illness->id,
            'doctor_name' => 'Dr. Old',
            'prescription_date' => now()->subYear(),
            'lab_test_cost' => 500.00,
            'drug_cost' => 500.00,
            'user_id' => $this->admin->id,
            'status' => PrescriptionStatus::TREATED,
        ]);
    }

    public function test_admin_can_render_prescription_report_page(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(\App\Filament\Pages\Reports\PrescriptionReport::class)
            ->assertSuccessful();
    }

    public function test_date_range_filtering_includes_matching_and_excludes_out_of_range(): void
    {
        $this->actingAs($this->admin);

        $component = Livewire::test(\App\Filament\Pages\Reports\PrescriptionReport::class)
            ->set('data.start_date', now()->startOfMonth()->toDateString())
            ->set('data.end_date', now()->endOfMonth()->toDateString());

        $metrics = $component->get('metrics') ?? $component->instance()->getSummaryMetrics();
        $this->assertEquals(2, $metrics['total_prescriptions']);
        $this->assertEquals(10000.00, $metrics['total_healthcare_cost']);
        $this->assertEquals(1, $metrics['orphan_count']);
        $this->assertEquals(1, $metrics['widow_count']);
    }

    public function test_patient_type_and_status_filtering_works(): void
    {
        $this->actingAs($this->admin);

        $orphanFilter = Livewire::test(\App\Filament\Pages\Reports\PrescriptionReport::class)
            ->set('data.start_date', now()->startOfMonth()->toDateString())
            ->set('data.end_date', now()->endOfMonth()->toDateString())
            ->set('data.patient_type', 'orphan');

        $orphanMetrics = $orphanFilter->instance()->getSummaryMetrics();
        $this->assertEquals(1, $orphanMetrics['total_prescriptions']);
        $this->assertEquals(3000.00, $orphanMetrics['total_healthcare_cost']);

        $treatedFilter = Livewire::test(\App\Filament\Pages\Reports\PrescriptionReport::class)
            ->set('data.start_date', now()->startOfMonth()->toDateString())
            ->set('data.end_date', now()->endOfMonth()->toDateString())
            ->set('data.status', 'treated');

        $treatedMetrics = $treatedFilter->instance()->getSummaryMetrics();
        $this->assertEquals(1, $treatedMetrics['total_prescriptions']);
        $this->assertEquals(7000.00, $treatedMetrics['total_healthcare_cost']);
    }

    public function test_pdf_preview_and_download_exports_return_valid_responses(): void
    {
        $this->actingAs($this->admin);

        $params = [
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
        ];

        $previewResponse = $this->get(route('reports.prescription-report.pdf', array_merge($params, ['action' => 'preview'])));
        $previewResponse->assertStatus(200);
        $previewResponse->assertHeader('Content-Type', 'application/pdf');

        $downloadResponse = $this->get(route('reports.prescription-report.pdf', array_merge($params, ['action' => 'download'])));
        $downloadResponse->assertStatus(200);
        $downloadResponse->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_coordinator_cannot_access_global_prescription_report(): void
    {
        $this->actingAs($this->coordinator);

        $pageResponse = $this->get('/admin/reports/prescription-report');
        $pageResponse->assertStatus(403);

        $pdfResponse = $this->get(route('reports.prescription-report.pdf', [
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
        ]));
        $pdfResponse->assertStatus(403);
    }
}
