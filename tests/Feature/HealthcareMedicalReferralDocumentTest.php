<?php

namespace Tests\Feature;

use App\Enums\Gender;
use App\Enums\IllnessCategory;
use App\Enums\PrescriptionStatus;
use App\Models\Deceased;
use App\Models\Illness;
use App\Models\Medication;
use App\Models\Orphan;
use App\Models\Prescription;
use App\Models\User;
use App\Models\Widow;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthcareMedicalReferralDocumentTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $coordinatorZoneA;

    protected User $coordinatorZoneB;

    protected Zone $zoneA;

    protected Zone $zoneB;

    protected Deceased $deceasedZoneA;

    protected Deceased $deceasedZoneB;

    protected Orphan $orphanZoneA;

    protected Widow $widowZoneA;

    protected Prescription $prescriptionOrphan;

    protected Prescription $prescriptionWidow;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        \App\Models\CompanyInformation::query()->delete();
        \App\Models\CompanyInformation::create([
            'id' => 1,
            'company_name' => 'Garko Orphans Foundation',
            'display_name' => 'Garko Orphans Foundation',
            'trading_name' => 'Garko Orphans Foundation',
            'address_line_1' => 'Garko Kano Road',
            'city' => 'Garko',
            'state_province' => 'Kano',
            'country_code' => 'NG',
            'phone_no' => '+2348000000000',
            'email' => 'info@garko.org',
            'website' => 'https://garko.org',
            'registration_no' => 'CAC/IT/12345',
        ]);

        $this->zoneA = Zone::create(['name' => 'Zone Alpha', 'code' => 'ZA']);
        $this->zoneB = Zone::create(['name' => 'Zone Beta', 'code' => 'ZB']);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole('admin');

        $this->coordinatorZoneA = User::factory()->create(['is_active' => true]);
        $this->coordinatorZoneA->assignRole('coordinator');
        $this->zoneA->update(['coordinator_id' => $this->coordinatorZoneA->id]);

        $this->coordinatorZoneB = User::factory()->create(['is_active' => true]);
        $this->coordinatorZoneB->assignRole('coordinator');
        $this->zoneB->update(['coordinator_id' => $this->coordinatorZoneB->id]);

        $this->deceasedZoneA = Deceased::factory()->create([
            'zone_id' => $this->zoneA->id,
            'guardian_name' => 'O\'Reilly & Sons',
            'first_name' => 'Abba',
            'last_name' => 'Sani',
            'reg_no' => 'DEC-ZA-10',
        ]);

        $this->deceasedZoneB = Deceased::factory()->create([
            'zone_id' => $this->zoneB->id,
            'first_name' => 'Garba',
            'last_name' => 'Lawal',
            'reg_no' => 'DEC-ZB-10',
        ]);

        $this->orphanZoneA = Orphan::create([
            'deceased_id' => $this->deceasedZoneA->id,
            'first_name' => 'Kabiru',
            'last_name' => 'Abba',
            'nin' => '55555555555',
            'reg_no' => 'ORP-ZA-10',
            'is_eligible' => true,
            'gender' => Gender::MALE,
        ]);

        $this->widowZoneA = Widow::create([
            'deceased_id' => $this->deceasedZoneA->id,
            'child_sequence' => 1,
            'first_name' => 'Hauwa',
            'last_name' => 'Abba',
            'nin' => '55555555556',
            'reg_no' => 'WID-ZA-10',
            'is_eligible' => true,
            'is_married' => false,
        ]);

        $illness = Illness::create(['name' => 'Acute Appendicitis', 'category' => IllnessCategory::Trauma]);
        $medication = Medication::create(['name' => 'Amoxicillin 500mg', 'type' => 'Capsule', 'user_id' => $this->admin->id]);

        $this->prescriptionOrphan = Prescription::create([
            'prescribable_type' => Orphan::class,
            'prescribable_id' => $this->orphanZoneA->id,
            'illness_id' => $illness->id,
            'doctor_name' => 'Dr. Farouk',
            'prescription_date' => now(),
            'lab_test_cost' => 2500.00,
            'drug_cost' => 4500.00,
            'note' => 'Severe lower right quadrant abdominal pain',
            'user_id' => $this->admin->id,
            'status' => PrescriptionStatus::PENDING,
        ]);
        $this->prescriptionOrphan->medications()->attach($medication->id, ['dosage' => '1 capsule 3x daily']);

        $this->prescriptionWidow = Prescription::create([
            'prescribable_type' => Widow::class,
            'prescribable_id' => $this->widowZoneA->id,
            'illness_id' => $illness->id,
            'doctor_name' => 'Dr. Zubaida',
            'prescription_date' => now(),
            'lab_test_cost' => 3000.00,
            'drug_cost' => 6000.00,
            'note' => 'Routine surgical consultation',
            'user_id' => $this->admin->id,
            'status' => PrescriptionStatus::PENDING,
        ]);
    }

    public function test_admin_can_preview_and_download_medical_referral_document(): void
    {
        $this->actingAs($this->admin);

        $previewResponse = $this->get(route('prescriptions.referral.preview', ['prescription' => $this->prescriptionOrphan]));
        $previewResponse->assertStatus(200);
        $previewResponse->assertHeader('Content-Type', 'application/pdf');

        $downloadResponse = $this->get(route('prescriptions.referral.download', ['prescription' => $this->prescriptionOrphan]));
        $downloadResponse->assertStatus(200);
        $downloadResponse->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_medical_referral_form_renders_patient_and_referral_information(): void
    {
        $this->actingAs($this->admin);

        // Orphan Referral HTML verification
        $orphanHtml = view('pdf.medical-referral-document', ['prescription' => $this->prescriptionOrphan])->render();
        $this->assertStringContainsString('Kabiru Abba', $orphanHtml);
        $this->assertStringContainsString('ORP-ZA-10', $orphanHtml);
        $this->assertStringContainsString('Male', $orphanHtml);
        $this->assertStringContainsString('Zone Alpha', $orphanHtml);
        $this->assertStringContainsString(e($this->deceasedZoneA->guardian_name), $orphanHtml);
        $this->assertStringContainsString('Severe lower right quadrant abdominal pain', $orphanHtml);
        $this->assertStringContainsString('Acute Appendicitis', $orphanHtml);
        $this->assertStringContainsString('Garko Orphans Foundation', $orphanHtml);
        $this->assertStringContainsString('For Medical Officer Use Only', $orphanHtml);

        // Widow Referral HTML verification
        $widowHtml = view('pdf.medical-referral-document', ['prescription' => $this->prescriptionWidow])->render();
        $this->assertStringContainsString('Hauwa Abba', $widowHtml);
        $this->assertStringContainsString('WID-ZA-10', $widowHtml);
        $this->assertStringContainsString('Female', $widowHtml);
        $this->assertStringContainsString('Zone Alpha', $widowHtml);
        $this->assertStringContainsString('Routine surgical consultation', $widowHtml);
        $this->assertStringContainsString('Acute Appendicitis', $widowHtml);
        $this->assertStringContainsString('Garko Orphans Foundation', $widowHtml);
    }

    public function test_coordinator_can_access_own_zone_referral_document_and_cross_zone_is_rejected(): void
    {
        $this->actingAs($this->coordinatorZoneA);
        $ownResponse = $this->get(route('prescriptions.referral.preview', ['prescription' => $this->prescriptionOrphan]));
        $ownResponse->assertStatus(200);

        $this->actingAs($this->coordinatorZoneB);
        $crossResponse = $this->get(route('prescriptions.referral.preview', ['prescription' => $this->prescriptionOrphan]));
        $crossResponse->assertStatus(403);
    }

    public function test_unauthenticated_guest_is_redirected(): void
    {
        $response = $this->get(route('prescriptions.referral.preview', ['prescription' => $this->prescriptionOrphan]));
        $response->assertRedirect('/admin/login');
    }

    public function test_admin_can_render_prescriptions_list_page_with_correct_grouped_actions(): void
    {
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));
        $this->actingAs($this->admin);

        \Livewire\Livewire::test(\App\Filament\Resources\Prescriptions\Pages\ListPrescriptions::class)
            ->assertSuccessful()
            ->assertTableActionExists('preview_referral')
            ->assertTableActionExists('download_referral')
            ->assertTableActionVisible('preview_referral', $this->prescriptionOrphan)
            ->assertTableActionVisible('download_referral', $this->prescriptionOrphan);
    }
}
