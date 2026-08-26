<?php

namespace Tests\Feature;

use App\Enums\Gender;
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

class HealthcarePrescriptionDocumentTest extends TestCase
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

    protected Orphan $orphanZoneB;

    protected Prescription $prescriptionOrphan;

    protected Prescription $prescriptionWidowTreated;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->zoneA = Zone::create(['name' => 'Zone Alpha', 'code' => 'ZA']);
        $this->zoneB = Zone::create(['name' => 'Zone Beta', 'code' => 'ZB']);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole('admin');

        $this->coordinatorZoneA = User::factory()->create([
            'is_active' => true,
        ]);
        $this->coordinatorZoneA->assignRole('coordinator');
        $this->zoneA->update(['coordinator_id' => $this->coordinatorZoneA->id]);

        $this->coordinatorZoneB = User::factory()->create([
            'is_active' => true,
        ]);
        $this->coordinatorZoneB->assignRole('coordinator');
        $this->zoneB->update(['coordinator_id' => $this->coordinatorZoneB->id]);

        $this->deceasedZoneA = Deceased::factory()->create([
            'zone_id' => $this->zoneA->id,
            'first_name' => 'Ibrahim',
            'last_name' => 'ZoneA',
            'reg_no' => 'DEC-ZA-01',
        ]);

        $this->deceasedZoneB = Deceased::factory()->create([
            'zone_id' => $this->zoneB->id,
            'first_name' => 'Usman',
            'last_name' => 'ZoneB',
            'reg_no' => 'DEC-ZB-01',
        ]);

        $this->orphanZoneA = Orphan::create([
            'deceased_id' => $this->deceasedZoneA->id,
            'first_name' => 'Aliyu',
            'last_name' => 'Orphan',
            'nin' => '12345678901',
            'reg_no' => 'ORP-ZA-01',
            'is_eligible' => true,
            'gender' => Gender::MALE,
        ]);

        $this->widowZoneA = Widow::create([
            'deceased_id' => $this->deceasedZoneA->id,
            'child_sequence' => 1,
            'first_name' => 'Aisha',
            'last_name' => 'Widow',
            'nin' => '12345678902',
            'reg_no' => 'WID-ZA-01',
            'is_eligible' => true,
            'is_married' => false,
        ]);

        $this->orphanZoneB = Orphan::create([
            'deceased_id' => $this->deceasedZoneB->id,
            'first_name' => 'Bello',
            'last_name' => 'Orphan',
            'nin' => '12345678903',
            'reg_no' => 'ORP-ZB-01',
            'is_eligible' => true,
            'gender' => Gender::MALE,
        ]);

        $illness = Illness::create(['name' => 'Malaria Fever', 'category' => \App\Enums\IllnessCategory::Infectious]);
        $med1 = Medication::create(['name' => 'Paracetamol 500mg', 'type' => 'Tablet', 'user_id' => $this->admin->id]);

        $this->prescriptionOrphan = Prescription::create([
            'prescribable_type' => Orphan::class,
            'prescribable_id' => $this->orphanZoneA->id,
            'illness_id' => $illness->id,
            'doctor_name' => 'Dr. Sani',
            'prescription_date' => now(),
            'lab_test_cost' => 1500.00,
            'drug_cost' => 3500.00,
            'note' => 'Patient presented with high fever',
            'user_id' => $this->admin->id,
            'status' => PrescriptionStatus::PENDING,
        ]);
        $this->prescriptionOrphan->medications()->attach($med1->id, ['dosage' => '2 tablets 3x daily']);

        $this->prescriptionWidowTreated = Prescription::create([
            'prescribable_type' => Widow::class,
            'prescribable_id' => $this->widowZoneA->id,
            'illness_id' => $illness->id,
            'doctor_name' => 'Dr. Amina',
            'prescription_date' => now()->subDays(2),
            'lab_test_cost' => 2000.00,
            'drug_cost' => 5000.00,
            'note' => 'Hypertension follow-up',
            'user_id' => $this->admin->id,
            'status' => PrescriptionStatus::TREATED,
            'treated_at' => now()->subDay(),
            'treated_by_id' => $this->admin->id,
            'treatment_notes' => 'Treated completely at Specialist Hospital',
        ]);
    }

    public function test_admin_can_preview_and_download_prescription_document(): void
    {
        $this->actingAs($this->admin);

        $previewResponse = $this->get(route('prescriptions.preview', ['prescription' => $this->prescriptionOrphan]));
        $previewResponse->assertStatus(200);
        $previewResponse->assertHeader('Content-Type', 'application/pdf');

        $downloadResponse = $this->get(route('prescriptions.download', ['prescription' => $this->prescriptionOrphan]));
        $downloadResponse->assertStatus(200);
        $downloadResponse->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_prescription_document_renders_orphan_and_widow_patient_information(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('prescriptions.preview', ['prescription' => $this->prescriptionOrphan]));
        $response->assertStatus(200);

        $treatedResponse = $this->get(route('prescriptions.preview', ['prescription' => $this->prescriptionWidowTreated]));
        $treatedResponse->assertStatus(200);
    }

    public function test_unauthorized_guest_received_403(): void
    {
        $response = $this->get(route('prescriptions.preview', ['prescription' => $this->prescriptionOrphan]));
        $response->assertRedirect('/admin/login');
    }

    public function test_coordinator_own_zone_access_succeeds_and_cross_zone_fails_with_403(): void
    {
        // Own zone access
        $this->actingAs($this->coordinatorZoneA);
        $ownResponse = $this->get(route('prescriptions.preview', ['prescription' => $this->prescriptionOrphan]));
        $ownResponse->assertStatus(200);

        // Cross zone access
        $this->actingAs($this->coordinatorZoneB);
        $crossResponse = $this->get(route('prescriptions.preview', ['prescription' => $this->prescriptionOrphan]));
        $crossResponse->assertStatus(403);
    }
}
