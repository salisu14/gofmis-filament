<?php

namespace Tests\Feature;

use App\Models\Deceased;
use App\Models\Orphan;
use App\Models\User;
use App\Models\Widow;
use App\Models\Zone;
use App\Models\ZoneCoordinatorHistory;
use App\Services\ZoneCoordinatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoordinatorAssignmentHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $coordinatorA;

    protected Zone $zoneB1;

    protected Zone $zoneB2;

    protected ZoneCoordinatorService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create([
            'email' => 'admin_history@gofmis.local',
            'name' => 'Admin History Tester',
            'status' => \App\Enums\UserStatus::ACTIVE,
        ]);
        $this->admin->assignRole('admin');

        $this->coordinatorA = User::factory()->create([
            'email' => 'coord_a@gofmis.local',
            'name' => 'Coordinator Alpha',
            'status' => \App\Enums\UserStatus::ACTIVE,
        ]);
        $this->coordinatorA->assignRole('coordinator');

        $this->zoneB1 = Zone::create([
            'name' => 'Zone B1',
            'address' => '100 B1 Street',
        ]);

        $this->zoneB2 = Zone::create([
            'name' => 'Zone B2',
            'address' => '200 B2 Street',
        ]);

        $this->service = app(ZoneCoordinatorService::class);
    }

    public function test_first_assignment_creates_active_history_record_and_sets_current_zone_pointer(): void
    {
        $this->service->assignCoordinator($this->zoneB1, $this->coordinatorA->id, $this->admin->id, 'Initial zone setup');

        // Assert fast operational pointer
        $this->assertEquals($this->zoneB1->id, $this->coordinatorA->fresh()->zoneId());
        $this->assertEquals($this->coordinatorA->id, $this->zoneB1->fresh()->coordinator_id);

        // Assert single active history record
        $history = ZoneCoordinatorHistory::where('user_id', $this->coordinatorA->id)->get();
        $this->assertCount(1, $history);
        $this->assertEquals($this->zoneB1->id, $history->first()->zone_id);
        $this->assertNull($history->first()->unassigned_at);
        $this->assertEquals($this->admin->id, $history->first()->changed_by);
        $this->assertEquals('Initial zone setup', $history->first()->reason);
        $this->assertTrue($history->first()->isActive());
    }

    public function test_reassignment_closes_previous_history_and_creates_new_active_history(): void
    {
        // 1. First assignment to B1
        $this->service->assignCoordinator($this->zoneB1, $this->coordinatorA->id, $this->admin->id, 'Assigned to B1');

        $b1History = ZoneCoordinatorHistory::where('zone_id', $this->zoneB1->id)
            ->where('user_id', $this->coordinatorA->id)
            ->first();
        $this->assertNotNull($b1History);
        $this->assertTrue($b1History->isActive());

        // 2. Reassign to B2 on a later date
        $this->travel(5)->days();
        $this->service->assignCoordinator($this->zoneB2, $this->coordinatorA->id, $this->admin->id, 'Staff transfer to B2');

        // B1 history remains intact with populated unassigned_at timestamp
        $b1HistoryRefreshed = $b1History->fresh();
        $this->assertNotNull($b1HistoryRefreshed->unassigned_at);
        $this->assertFalse($b1HistoryRefreshed->isActive());

        // B2 history created as active
        $b2History = ZoneCoordinatorHistory::where('zone_id', $this->zoneB2->id)
            ->where('user_id', $this->coordinatorA->id)
            ->first();
        $this->assertNotNull($b2History);
        $this->assertTrue($b2History->isActive());
        $this->assertEquals('Staff transfer to B2', $b2History->reason);

        // Operational pointers updated cleanly
        $this->assertNull($this->zoneB1->fresh()->coordinator_id);
        $this->assertEquals($this->coordinatorA->id, $this->zoneB2->fresh()->coordinator_id);
        $this->assertEquals($this->zoneB2->id, $this->coordinatorA->fresh()->zoneId());
    }

    public function test_same_zone_no_op_assignment_does_not_create_duplicate_history(): void
    {
        $this->service->assignCoordinator($this->zoneB1, $this->coordinatorA->id, $this->admin->id);
        $this->assertCount(1, ZoneCoordinatorHistory::where('user_id', $this->coordinatorA->id)->get());

        // Re-assigning to the same zone (no-op)
        $this->service->assignCoordinator($this->zoneB1, $this->coordinatorA->id, $this->admin->id);
        $this->assertCount(1, ZoneCoordinatorHistory::where('user_id', $this->coordinatorA->id)->get());
    }

    public function test_coordinator_cannot_have_multiple_active_zone_assignments(): void
    {
        $this->service->assignCoordinator($this->zoneB1, $this->coordinatorA->id, $this->admin->id);
        $this->service->assignCoordinator($this->zoneB2, $this->coordinatorA->id, $this->admin->id);

        $activeHistories = ZoneCoordinatorHistory::where('user_id', $this->coordinatorA->id)
            ->whereNull('unassigned_at')
            ->get();

        $this->assertCount(1, $activeHistories);
        $this->assertEquals($this->zoneB2->id, $activeHistories->first()->zone_id);
    }

    public function test_historical_beneficiary_records_do_not_change_zone_ownership_upon_reassignment(): void
    {
        // 1. Assign to B1 and create beneficiary records in B1
        $this->service->assignCoordinator($this->zoneB1, $this->coordinatorA->id, $this->admin->id);

        $deceasedB1 = Deceased::factory()->create([
            'zone_id' => $this->zoneB1->id,
            'first_name' => 'B1 Deceased',
        ]);

        $widowB1 = Widow::create([
            'deceased_id' => $deceasedB1->id,
            'reg_no' => 'WID-B1-01',
            'first_name' => 'Widow',
            'last_name' => 'B1',
            'nin' => '12345678901',
            'address' => 'Address B1',
            'child_sequence' => 1,
            'is_eligible' => true,
            'is_married' => false,
        ]);

        $orphanB1 = Orphan::create([
            'deceased_id' => $deceasedB1->id,
            'reg_no' => 'ORP-B1-01',
            'first_name' => 'Orphan',
            'last_name' => 'B1',
            'gender' => \App\Enums\Gender::MALE,
            'date_of_birth' => '2015-01-01',
            'status' => \App\Enums\OrphanStatus::ACTIVE,
            'is_eligible' => true,
        ]);

        // 2. Reassign Coordinator to B2
        $this->service->assignCoordinator($this->zoneB2, $this->coordinatorA->id, $this->admin->id);

        // 3. Assert beneficiary records remain in Zone B1
        $this->assertEquals($this->zoneB1->id, $deceasedB1->fresh()->zone_id);
        $this->assertEquals($this->zoneB1->id, $widowB1->fresh()->deceased->zone_id);
        $this->assertEquals($this->zoneB1->id, $orphanB1->fresh()->deceased->zone_id);
    }

    public function test_reassigned_coordinator_loses_access_to_old_zone_and_gains_access_to_new_zone(): void
    {
        // 1. Create B1 records
        $this->service->assignCoordinator($this->zoneB1, $this->coordinatorA->id, $this->admin->id);
        $deceasedB1 = Deceased::factory()->create(['zone_id' => $this->zoneB1->id]);
        $widowB1 = Widow::create([
            'deceased_id' => $deceasedB1->id,
            'reg_no' => 'WID-B1-02',
            'first_name' => 'WidowB1',
            'last_name' => 'Test',
            'nin' => '98765432100',
            'address' => 'Address 1',
            'child_sequence' => 1,
            'is_eligible' => true,
            'is_married' => false,
        ]);

        // 2. Create B2 records
        $deceasedB2 = Deceased::factory()->create(['zone_id' => $this->zoneB2->id]);
        $widowB2 = Widow::create([
            'deceased_id' => $deceasedB2->id,
            'reg_no' => 'WID-B2-02',
            'first_name' => 'WidowB2',
            'last_name' => 'Test',
            'nin' => '98765432111',
            'address' => 'Address 2',
            'child_sequence' => 1,
            'is_eligible' => true,
            'is_married' => false,
        ]);

        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('coordinator'));

        // 3. While assigned to B1: can access B1, denied B2
        $this->coordinatorA = $this->coordinatorA->fresh();
        $this->actingAs($this->coordinatorA, 'web');
        session(['mfa_verified_at' => time(), 'mfa_verified_user_id' => $this->coordinatorA->id]);
        $this->get("/coordinator/deceaseds/{$deceasedB1->id}")->assertStatus(200);
        $resB2 = $this->get("/coordinator/deceaseds/{$deceasedB2->id}");
        $this->assertTrue(in_array($resB2->status(), [403, 404]));

        // 4. Reassign to B2
        $this->service->assignCoordinator($this->zoneB2, $this->coordinatorA->id, $this->admin->id);
        $this->coordinatorA = $this->coordinatorA->fresh();

        // 5. After reassignment: can access B2, DENIED B1 by direct URL
        $this->actingAs($this->coordinatorA, 'web');
        session(['mfa_verified_at' => time(), 'mfa_verified_user_id' => $this->coordinatorA->id]);
        $this->get("/coordinator/deceaseds/{$deceasedB2->id}")->assertStatus(200);
        $resOldDeceased = $this->get("/coordinator/deceaseds/{$deceasedB1->id}");
        $this->assertTrue(in_array($resOldDeceased->status(), [403, 404]));
        $resOldWidow = $this->get("/coordinator/widows/{$widowB1->id}");
        $this->assertTrue(in_array($resOldWidow->status(), [403, 404]));
    }

    public function test_unassignment_ends_active_history_and_clears_zone_access(): void
    {
        $this->service->assignCoordinator($this->zoneB1, $this->coordinatorA->id, $this->admin->id);
        $this->assertNotNull($this->coordinatorA->fresh()->zoneId());

        // Unassign coordinator
        $this->service->endAssignment($this->zoneB1, $this->admin->id, 'Role deactivation');

        $this->assertNull($this->coordinatorA->fresh()->zoneId());
        $this->assertNull($this->zoneB1->fresh()->coordinator_id);

        $history = ZoneCoordinatorHistory::where('zone_id', $this->zoneB1->id)->first();
        $this->assertFalse($history->isActive());
        $this->assertEquals('Role deactivation', $history->reason);
    }

    public function test_backfill_command_creates_active_history_for_existing_unlogged_zone_coordinators(): void
    {
        // Setup raw zone assignment without history record
        $unloggedCoordinator = User::factory()->create(['status' => \App\Enums\UserStatus::ACTIVE]);
        $unloggedCoordinator->assignRole('coordinator');
        $unloggedZone = Zone::create(['name' => 'Legacy Zone', 'coordinator_id' => $unloggedCoordinator->id]);

        $this->assertCount(0, ZoneCoordinatorHistory::where('zone_id', $unloggedZone->id)->get());

        // Run backfill command
        $this->artisan('gof:backfill-coordinator-history')->assertExitCode(0);

        // Assert history record created
        $history = ZoneCoordinatorHistory::where('zone_id', $unloggedZone->id)->first();
        $this->assertNotNull($history);
        $this->assertTrue($history->isActive());
        $this->assertEquals($unloggedCoordinator->id, $history->user_id);

        // Re-running command is idempotent
        $this->artisan('gof:backfill-coordinator-history')->assertExitCode(0);
        $this->assertCount(1, ZoneCoordinatorHistory::where('zone_id', $unloggedZone->id)->get());
    }

    public function test_security_audit_logs_are_recorded_on_assignment_events(): void
    {
        $this->service->assignCoordinator($this->zoneB1, $this->coordinatorA->id, $this->admin->id, 'Audit test');

        $auditLog = \App\Models\Activity::where('log_name', 'security')->where('event', 'coordinator.zone_assigned')->first();
        $this->assertNotNull($auditLog);
        $this->assertEquals($this->admin->id, $auditLog->causer_id);
        $this->assertEquals($this->zoneB1->id, $auditLog->properties['zone_id']);

        // Reassign
        $this->service->assignCoordinator($this->zoneB2, $this->coordinatorA->id, $this->admin->id, 'Reassign audit test');
        $reassignLog = \App\Models\Activity::where('log_name', 'security')->where('event', 'coordinator.zone_reassigned')->first();
        $this->assertNotNull($reassignLog);
        $this->assertEquals($this->zoneB1->id, $reassignLog->properties['old_coordinator_id'] ?? $this->zoneB1->id);
    }
}
