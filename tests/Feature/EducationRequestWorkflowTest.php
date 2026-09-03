<?php

namespace Tests\Feature;

use App\Enums\Gender;
use App\Models\BankAccount;
use App\Models\Deceased;
use App\Models\Institution;
use App\Models\InterventionRequest;
use App\Models\InterventionType;
use App\Models\Orphan;
use App\Models\OrphanEducation;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Zone;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EducationRequestWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected Zone $zoneA;

    protected Zone $zoneB;

    protected User $coordinatorZoneA;

    protected User $coordinatorZoneB;

    protected User $adminUser;

    protected User $verifierUser;

    protected Deceased $deceasedZoneA;

    protected Deceased $deceasedZoneB;

    protected Orphan $orphanZoneA;

    protected Orphan $orphanZoneB;

    protected InterventionType $educationType;

    protected BankAccount $bankAccount;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('coordinator'));

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $coordinatorRole = Role::firstOrCreate(['name' => 'coordinator', 'guard_name' => 'web']);
        $verifierRole = Role::firstOrCreate(['name' => 'education-verifier', 'guard_name' => 'web']);

        $viewEdu = Permission::firstOrCreate(['name' => 'view_education_interventions', 'guard_name' => 'web']);
        $createEdu = Permission::firstOrCreate(['name' => 'create_education_interventions', 'guard_name' => 'web']);
        $verifyEdu = Permission::firstOrCreate(['name' => 'verify_education_interventions', 'guard_name' => 'web']);
        $coordinatorRole->givePermissionTo([$viewEdu, $createEdu]);
        $verifierRole->givePermissionTo([$viewEdu, $verifyEdu]);

        $this->zoneA = Zone::create(['name' => 'Zone Alpha', 'code' => 'ZA']);
        $this->zoneB = Zone::create(['name' => 'Zone Beta', 'code' => 'ZB']);

        $this->coordinatorZoneA = User::factory()->create(['name' => 'Coordinator Zone A']);
        $this->coordinatorZoneA->assignRole('coordinator');
        $this->zoneA->update(['coordinator_id' => $this->coordinatorZoneA->id]);

        $this->coordinatorZoneB = User::factory()->create(['name' => 'Coordinator Zone B']);
        $this->coordinatorZoneB->assignRole('coordinator');
        $this->zoneB->update(['coordinator_id' => $this->coordinatorZoneB->id]);

        $this->adminUser = User::factory()->create(['name' => 'Admin User']);
        $this->adminUser->assignRole('admin');

        $this->verifierUser = User::factory()->create(['name' => 'Verifier User']);
        $this->verifierUser->assignRole('education-verifier');

        $this->coordinatorZoneA = $this->coordinatorZoneA->fresh();
        $this->coordinatorZoneB = $this->coordinatorZoneB->fresh();

        $this->deceasedZoneA = Deceased::factory()->create([
            'first_name' => 'DeceasedA',
            'last_name' => 'Alpha',
            'zone_id' => $this->zoneA->id,
        ]);

        $this->deceasedZoneB = Deceased::factory()->create([
            'first_name' => 'DeceasedB',
            'last_name' => 'Beta',
            'zone_id' => $this->zoneB->id,
        ]);

        $this->orphanZoneA = Orphan::create([
            'deceased_id' => $this->deceasedZoneA->id,
            'first_name' => 'OrphanA',
            'last_name' => 'Alpha',
            'reg_no' => 'ORP-ZA-001',
            'gender' => Gender::FEMALE,
            'date_of_birth' => now()->subYears(10),
            'is_eligible' => true,
        ]);

        $this->orphanZoneB = Orphan::create([
            'deceased_id' => $this->deceasedZoneB->id,
            'first_name' => 'OrphanB',
            'last_name' => 'Beta',
            'reg_no' => 'ORP-ZB-001',
            'gender' => Gender::MALE,
            'date_of_birth' => now()->subYears(11),
            'is_eligible' => true,
        ]);

        $institution = Institution::create([
            'name' => 'Community Secondary School',
            'type' => \App\Enums\InstitutionType::WESTERN,
            'address' => '1 Education Way',
        ]);

        OrphanEducation::create([
            'orphan_id' => $this->orphanZoneA->id,
            'institution_id' => $institution->id,
            'level' => 'Primary 4',
            'school_fee' => 35000,
            'fee_frequency' => 'termly',
            'is_current' => true,
        ]);

        $this->educationType = InterventionType::firstOrCreate(
            ['name' => 'Education - School Fees Support'],
            ['description' => 'Tuition and school fee support for orphans']
        );

        $this->bankAccount = BankAccount::create([
            'account_name' => 'Education Fund',
            'account_number' => '1234567890',
            'bank_name' => 'GOF Central Bank',
            'user_id' => $this->adminUser->id,
            'usage' => BankAccount::USAGE_INTERVENTION,
            'opening_balance' => 500000.00,
            'ledger_balance' => 500000.00,
            'is_active' => true,
        ]);
    }

    public function test_1_coordinator_can_list_own_zone_education_requests(): void
    {
        $requestA = InterventionRequest::create([
            'orphan_id' => $this->orphanZoneA->id,
            'intervention_type_id' => $this->educationType->id,
            'status' => 'pending',
            'verification_status' => 'pending',
            'requested_amount' => 35000,
            'notes' => 'Term 1 school fees',
            'request_date' => now(),
        ]);

        $this->actingAs($this->coordinatorZoneA);

        Livewire::test(\App\Filament\Coordinator\Resources\EducationRequestResource\Pages\ListEducationRequests::class)
            ->assertCanSeeTableRecords([$requestA]);
    }

    public function test_2_coordinator_cannot_see_cross_zone_education_requests(): void
    {
        $requestB = InterventionRequest::create([
            'orphan_id' => $this->orphanZoneB->id,
            'intervention_type_id' => $this->educationType->id,
            'status' => 'pending',
            'verification_status' => 'pending',
            'requested_amount' => 40000,
            'notes' => 'Zone B request',
            'request_date' => now(),
        ]);

        $this->actingAs($this->coordinatorZoneA);

        Livewire::test(\App\Filament\Coordinator\Resources\EducationRequestResource\Pages\ListEducationRequests::class)
            ->assertCanNotSeeTableRecords([$requestB]);
    }

    public function test_3_coordinator_can_create_education_request_for_eligible_own_zone_orphan(): void
    {
        $this->actingAs($this->coordinatorZoneA);

        Livewire::test(\App\Filament\Coordinator\Resources\EducationRequestResource\Pages\CreateEducationRequest::class)
            ->set('data.orphan_id', (string) $this->orphanZoneA->id)
            ->set('data.intervention_type_id', (string) $this->educationType->id)
            ->set('data.requested_amount', 35000)
            ->set('data.notes', 'School fee assistance needed for term 1')
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('intervention_requests', [
            'orphan_id' => $this->orphanZoneA->id,
            'intervention_type_id' => $this->educationType->id,
            'requested_amount' => 35000.00,
            'status' => 'pending',
        ]);
    }

    public function test_4_coordinator_cannot_create_education_request_for_cross_zone_orphan(): void
    {
        $this->actingAs($this->coordinatorZoneA);

        Livewire::test(\App\Filament\Coordinator\Resources\EducationRequestResource\Pages\CreateEducationRequest::class)
            ->set('data.orphan_id', (string) $this->orphanZoneB->id)
            ->set('data.intervention_type_id', (string) $this->educationType->id)
            ->set('data.requested_amount', 40000)
            ->set('data.notes', 'Cross zone submission')
            ->call('create')
            ->assertHasFormErrors(['orphan_id']);

        $this->assertDatabaseMissing('intervention_requests', [
            'notes' => 'Cross zone submission',
        ]);
    }

    public function test_5_livewire_payload_tampering_with_orphan_id_is_rejected(): void
    {
        $this->actingAs($this->coordinatorZoneA);

        Livewire::test(\App\Filament\Coordinator\Resources\EducationRequestResource\Pages\CreateEducationRequest::class)
            ->set('data.orphan_id', (string) $this->orphanZoneB->id)
            ->set('data.intervention_type_id', (string) $this->educationType->id)
            ->call('create')
            ->assertHasFormErrors(['orphan_id']);
    }

    public function test_6_ineligible_or_archived_orphan_cannot_receive_education_request(): void
    {
        $ineligibleOrphan = Orphan::create([
            'deceased_id' => $this->deceasedZoneA->id,
            'first_name' => 'Ineligible',
            'last_name' => 'Child',
            'reg_no' => 'ORP-ZA-999',
            'child_sequence' => 2,
            'gender' => Gender::MALE,
            'date_of_birth' => now()->subYears(20), // Aged out
            'is_eligible' => false,
        ]);

        $this->actingAs($this->coordinatorZoneA);

        Livewire::test(\App\Filament\Coordinator\Resources\EducationRequestResource\Pages\CreateEducationRequest::class)
            ->set('data.orphan_id', (string) $ineligibleOrphan->id)
            ->set('data.intervention_type_id', (string) $this->educationType->id)
            ->set('data.notes', 'Ineligible orphan attempt')
            ->call('create')
            ->assertHasFormErrors(['orphan_id']);
    }

    public function test_7_duplicate_active_education_request_for_same_type_is_blocked(): void
    {
        InterventionRequest::create([
            'orphan_id' => $this->orphanZoneA->id,
            'intervention_type_id' => $this->educationType->id,
            'status' => 'pending',
            'verification_status' => 'pending',
            'requested_amount' => 35000,
            'notes' => 'First active request',
            'request_date' => now(),
        ]);

        $this->actingAs($this->coordinatorZoneA);

        Livewire::test(\App\Filament\Coordinator\Resources\EducationRequestResource\Pages\CreateEducationRequest::class)
            ->set('data.orphan_id', (string) $this->orphanZoneA->id)
            ->set('data.intervention_type_id', (string) $this->educationType->id)
            ->set('data.requested_amount', 35000)
            ->set('data.notes', 'Duplicate active request attempt')
            ->call('create')
            ->assertHasFormErrors(['orphan_id']);
    }

    public function test_8_historical_rejected_request_does_not_block_new_request(): void
    {
        InterventionRequest::create([
            'orphan_id' => $this->orphanZoneA->id,
            'intervention_type_id' => $this->educationType->id,
            'status' => 'rejected',
            'rejection_reason' => 'Previous term expired',
            'verification_status' => 'failed',
            'requested_amount' => 35000,
            'notes' => 'Old rejected request',
            'request_date' => now()->subYear(),
        ]);

        $this->actingAs($this->coordinatorZoneA);

        Livewire::test(\App\Filament\Coordinator\Resources\EducationRequestResource\Pages\CreateEducationRequest::class)
            ->set('data.orphan_id', (string) $this->orphanZoneA->id)
            ->set('data.intervention_type_id', (string) $this->educationType->id)
            ->set('data.requested_amount', 35000)
            ->set('data.notes', 'New valid request')
            ->call('create')
            ->assertHasNoFormErrors();
    }

    public function test_9_requested_amount_must_be_greater_than_zero(): void
    {
        $this->actingAs($this->coordinatorZoneA);

        Livewire::test(\App\Filament\Coordinator\Resources\EducationRequestResource\Pages\CreateEducationRequest::class)
            ->set('data.orphan_id', (string) $this->orphanZoneA->id)
            ->set('data.intervention_type_id', (string) $this->educationType->id)
            ->set('data.requested_amount', -500)
            ->call('create')
            ->assertHasFormErrors(['requested_amount']);
    }

    public function test_10_cross_zone_direct_url_access_returns_404_or_403(): void
    {
        $requestB = InterventionRequest::create([
            'orphan_id' => $this->orphanZoneB->id,
            'intervention_type_id' => $this->educationType->id,
            'status' => 'pending',
            'requested_amount' => 40000,
            'notes' => 'Zone B request',
            'request_date' => now(),
        ]);

        $this->actingAs($this->coordinatorZoneA);

        $response = $this->get(\App\Filament\Coordinator\Resources\EducationRequestResource::getUrl('view', ['record' => $requestB->id], panel: 'coordinator'));
        $this->assertTrue(in_array($response->status(), [403, 404]));
    }

    public function test_11_cross_zone_livewire_mutation_is_rejected(): void
    {
        $requestB = InterventionRequest::create([
            'orphan_id' => $this->orphanZoneB->id,
            'intervention_type_id' => $this->educationType->id,
            'status' => 'pending',
            'requested_amount' => 40000,
            'notes' => 'Zone B request',
            'request_date' => now(),
        ]);

        $this->actingAs($this->coordinatorZoneA);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        Livewire::test(\App\Filament\Coordinator\Resources\EducationRequestResource\Pages\EditEducationRequest::class, ['record' => $requestB->id]);
    }

    public function test_12_verifier_or_admin_can_mark_education_request_as_verified(): void
    {
        $request = InterventionRequest::create([
            'orphan_id' => $this->orphanZoneA->id,
            'intervention_type_id' => $this->educationType->id,
            'status' => 'pending',
            'verification_status' => 'pending',
            'requested_amount' => 35000,
            'notes' => 'Request awaiting verification',
            'request_date' => now(),
        ]);

        $request->markVerified($this->verifierUser->id, 'Verified with school principal');

        $fresh = $request->fresh();
        $this->assertEquals('verified', $fresh->verification_status);
        $this->assertEquals($this->verifierUser->id, $fresh->verified_by);
        $this->assertNotNull($fresh->verified_at);
    }

    public function test_13_unverified_education_request_cannot_be_approved(): void
    {
        $request = InterventionRequest::create([
            'orphan_id' => $this->orphanZoneA->id,
            'intervention_type_id' => $this->educationType->id,
            'status' => 'pending',
            'verification_status' => 'pending',
            'requested_amount' => 35000,
            'notes' => 'Unverified request',
            'request_date' => now(),
        ]);

        $this->expectException(\RuntimeException::class);
        $request->approveRequest($this->adminUser->id);
    }

    public function test_14_admin_can_approve_verified_education_request(): void
    {
        $request = InterventionRequest::create([
            'orphan_id' => $this->orphanZoneA->id,
            'intervention_type_id' => $this->educationType->id,
            'status' => 'pending',
            'verification_status' => 'pending',
            'requested_amount' => 35000,
            'notes' => 'Verified request',
            'request_date' => now(),
        ]);

        $request->markVerified($this->verifierUser->id, 'Verified school enrollment');
        $request->approveRequest($this->adminUser->id);

        $fresh = $request->fresh();
        $this->assertEquals('approved', $fresh->status);
        $this->assertEquals($this->adminUser->id, $fresh->approved_by);
        $this->assertNotNull($fresh->approved_at);
    }

    public function test_15_coordinator_cannot_approve_education_request(): void
    {
        $request = InterventionRequest::create([
            'orphan_id' => $this->orphanZoneA->id,
            'intervention_type_id' => $this->educationType->id,
            'status' => 'pending',
            'verification_status' => 'verified',
            'requested_amount' => 35000,
            'notes' => 'Verified request',
            'request_date' => now(),
        ]);

        $this->actingAs($this->coordinatorZoneA);

        $this->assertFalse($this->coordinatorZoneA->can('approve', $request));
    }

    public function test_16_admin_can_reject_education_request_with_reason(): void
    {
        $request = InterventionRequest::create([
            'orphan_id' => $this->orphanZoneA->id,
            'intervention_type_id' => $this->educationType->id,
            'status' => 'pending',
            'verification_status' => 'pending',
            'requested_amount' => 35000,
            'notes' => 'Request to reject',
            'request_date' => now(),
        ]);

        $request->rejectRequest('Duplicate school fee claim', $this->adminUser->id);

        $fresh = $request->fresh();
        $this->assertEquals('rejected', $fresh->status);
        $this->assertEquals('failed', $fresh->verification_status);
        $this->assertEquals('Duplicate school fee claim', $fresh->rejection_reason);
    }

    public function test_17_coordinator_cannot_reject_education_request(): void
    {
        $request = InterventionRequest::create([
            'orphan_id' => $this->orphanZoneA->id,
            'intervention_type_id' => $this->educationType->id,
            'status' => 'pending',
            'requested_amount' => 35000,
            'notes' => 'Request',
            'request_date' => now(),
        ]);

        $this->actingAs($this->coordinatorZoneA);

        $this->assertFalse($this->coordinatorZoneA->can('reject', $request));
    }

    public function test_18_approved_education_request_can_be_fulfilled_with_bank_debit(): void
    {
        $request = InterventionRequest::create([
            'orphan_id' => $this->orphanZoneA->id,
            'intervention_type_id' => $this->educationType->id,
            'status' => 'pending',
            'verification_status' => 'pending',
            'requested_amount' => 35000,
            'notes' => 'Verified and approved request',
            'request_date' => now(),
        ]);

        $request->markVerified($this->verifierUser->id, 'Verified');
        $request->approveRequest($this->adminUser->id);

        $this->actingAs($this->adminUser);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(\App\Filament\Resources\InterventionRequests\RelationManagers\InterventionsRelationManager::class, [
            'ownerRecord' => $request,
            'pageClass' => \App\Filament\Resources\InterventionRequests\Pages\EditInterventionRequest::class,
        ])
            ->callTableAction('create', data: [
                'disbursed_at' => now()->format('Y-m-d'),
                'collected_by' => 'School Principal',
                'bank_account_id' => $this->bankAccount->id,
                'amount' => 35000,
                'notes' => 'Tuition fee paid directly to school',
            ])
            ->assertHasNoTableActionErrors();

        $fresh = $request->fresh();
        $this->assertEquals('fulfilled', $fresh->status);
        $this->assertEquals(465000.00, (float) $this->bankAccount->fresh()->ledger_balance);
        $this->assertDatabaseHas('transactions', [
            'bank_account_id' => $this->bankAccount->id,
            'amount' => 35000,
            'type' => 'intervention',
        ]);
    }

    public function test_19_pending_or_rejected_education_request_cannot_be_fulfilled(): void
    {
        $pendingRequest = InterventionRequest::create([
            'orphan_id' => $this->orphanZoneA->id,
            'intervention_type_id' => $this->educationType->id,
            'status' => 'pending',
            'requested_amount' => 35000,
            'notes' => 'Pending request',
            'request_date' => now(),
        ]);

        $this->actingAs($this->adminUser);

        Livewire::test(\App\Filament\Resources\InterventionRequests\RelationManagers\InterventionsRelationManager::class, [
            'ownerRecord' => $pendingRequest,
            'pageClass' => \App\Filament\Resources\InterventionRequests\Pages\EditInterventionRequest::class,
        ])
            ->assertTableActionHidden('create');
    }

    public function test_20_fulfilled_or_approved_education_request_cannot_be_deleted(): void
    {
        $request = InterventionRequest::create([
            'orphan_id' => $this->orphanZoneA->id,
            'intervention_type_id' => $this->educationType->id,
            'status' => 'pending',
            'verification_status' => 'pending',
            'requested_amount' => 35000,
            'notes' => 'Request to fulfil then delete',
            'request_date' => now(),
        ]);

        $request->markVerified($this->verifierUser->id, 'Verified');
        $request->approveRequest($this->adminUser->id);

        $this->expectException(\DomainException::class);
        $request->fresh()->delete();
    }

    public function test_21_admin_global_education_access_remains_intact(): void
    {
        $requestA = InterventionRequest::create([
            'orphan_id' => $this->orphanZoneA->id,
            'intervention_type_id' => $this->educationType->id,
            'status' => 'pending',
            'requested_amount' => 35000,
            'notes' => 'Zone A',
            'request_date' => now(),
        ]);

        $requestB = InterventionRequest::create([
            'orphan_id' => $this->orphanZoneB->id,
            'intervention_type_id' => $this->educationType->id,
            'status' => 'pending',
            'requested_amount' => 40000,
            'notes' => 'Zone B',
            'request_date' => now(),
        ]);

        $this->actingAs($this->adminUser);

        Livewire::test(\App\Filament\Coordinator\Resources\EducationRequestResource\Pages\ListEducationRequests::class)
            ->assertCanSeeTableRecords([$requestA, $requestB]);
    }

    public function test_22_duplicate_fulfilment_attempt_is_rejected_without_double_debit(): void
    {
        $request = InterventionRequest::create([
            'orphan_id' => $this->orphanZoneA->id,
            'intervention_type_id' => $this->educationType->id,
            'status' => 'pending',
            'verification_status' => 'pending',
            'requested_amount' => 35000,
            'notes' => 'Single disbursement test',
            'request_date' => now(),
        ]);

        $request->markVerified($this->verifierUser->id, 'Verified');
        $request->approveRequest($this->adminUser->id);

        $this->actingAs($this->adminUser);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $relManager = Livewire::test(\App\Filament\Resources\InterventionRequests\RelationManagers\InterventionsRelationManager::class, [
            'ownerRecord' => $request,
            'pageClass' => \App\Filament\Resources\InterventionRequests\Pages\EditInterventionRequest::class,
        ]);

        // First fulfillment: 35,000
        $relManager->callTableAction('create', data: [
            'disbursed_at' => now()->format('Y-m-d'),
            'collected_by' => 'School Bursar',
            'bank_account_id' => $this->bankAccount->id,
            'amount' => 35000,
            'notes' => 'First valid payment',
        ])->assertHasNoTableActionErrors();

        $this->assertEquals(465000.00, (float) $this->bankAccount->fresh()->ledger_balance);
        $this->assertEquals(1, \App\Models\Transaction::where('bank_account_id', $this->bankAccount->id)->count());
        $this->assertEquals(1, $request->interventions()->count());
        $this->assertEquals('fulfilled', $request->fresh()->status);

        // Second fulfillment attempt on the same fulfilled request:
        // 1. Action is hidden in the UI
        $fulfilledComponent = Livewire::test(\App\Filament\Resources\InterventionRequests\RelationManagers\InterventionsRelationManager::class, [
            'ownerRecord' => $request->fresh(),
            'pageClass' => \App\Filament\Resources\InterventionRequests\Pages\EditInterventionRequest::class,
        ])
            ->assertTableActionHidden('create');

        // 2. Direct server-side call handler is rejected
        try {
            $fulfilledComponent->call('callMountedTableAction');
        } catch (\Throwable $e) {
            // Server-side guard prevented execution
        }

        // Invariants check: No second debit, no second transaction, no second intervention row
        $this->assertEquals(465000.00, (float) $this->bankAccount->fresh()->ledger_balance);
        $this->assertEquals(1, \App\Models\Transaction::where('bank_account_id', $this->bankAccount->id)->count());
        $this->assertEquals(1, $request->interventions()->count());
        $this->assertEquals('fulfilled', $request->fresh()->status);
    }

    public function test_23_over_disbursement_beyond_requested_amount_is_rejected(): void
    {
        $request = InterventionRequest::create([
            'orphan_id' => $this->orphanZoneA->id,
            'intervention_type_id' => $this->educationType->id,
            'status' => 'pending',
            'verification_status' => 'pending',
            'requested_amount' => 35000,
            'notes' => 'Over disbursement check',
            'request_date' => now(),
        ]);

        $request->markVerified($this->verifierUser->id, 'Verified');
        $request->approveRequest($this->adminUser->id);

        $this->actingAs($this->adminUser);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(\App\Filament\Resources\InterventionRequests\RelationManagers\InterventionsRelationManager::class, [
            'ownerRecord' => $request,
            'pageClass' => \App\Filament\Resources\InterventionRequests\Pages\EditInterventionRequest::class,
        ])
            ->callTableAction('create', data: [
                'disbursed_at' => now()->format('Y-m-d'),
                'collected_by' => 'School Bursar',
                'bank_account_id' => $this->bankAccount->id,
                'amount' => 50000,
                'notes' => 'Excessive disbursement attempt',
            ])
            ->assertHasTableActionErrors();

        $this->assertEquals(500000.00, (float) $this->bankAccount->fresh()->ledger_balance);
        $this->assertEquals(0, $request->interventions()->count());
        $this->assertEquals('approved', $request->fresh()->status);
    }
}
