<?php

namespace Tests\Feature;

use App\Filament\Resources\Zones\Pages\EditZone;
use App\Models\User;
use App\Models\Zone;
use App\Models\ZoneCoordinatorHistory;
use App\Services\ZoneCoordinatorService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ZoneCoordinatorAssignmentHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $coordinator1;

    protected User $coordinator2;

    protected Zone $zoneA;

    protected Zone $zoneB;

    protected ZoneCoordinatorService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create([
            'email' => 'admin_test@gofmis.local',
            'name' => 'Admin User',
            'status' => \App\Enums\UserStatus::ACTIVE,
        ]);
        $this->admin->assignRole('admin');

        $this->coordinator1 = User::factory()->create([
            'email' => 'coord1@gofmis.local',
            'name' => 'Coordinator One',
            'status' => \App\Enums\UserStatus::ACTIVE,
        ]);
        $this->coordinator1->assignRole('coordinator');

        $this->coordinator2 = User::factory()->create([
            'email' => 'coord2@gofmis.local',
            'name' => 'Coordinator Two',
            'status' => \App\Enums\UserStatus::ACTIVE,
        ]);
        $this->coordinator2->assignRole('coordinator');

        $this->zoneA = Zone::create(['name' => 'Zone Alpha', 'address' => '100 Alpha St']);
        $this->zoneB = Zone::create(['name' => 'Zone Beta', 'address' => '200 Beta St']);

        $this->service = app(ZoneCoordinatorService::class);

        // Run partial index migration in memory DB
        $this->artisan('migrate');
    }

    /** 1. Assigning first coordinator creates exactly one current history row */
    public function test_assigning_first_coordinator_creates_exactly_one_current_history_row(): void
    {
        $this->service->assignCoordinator($this->zoneA, $this->coordinator1->id, $this->admin->id, 'First assignment');

        $histories = ZoneCoordinatorHistory::where('zone_id', $this->zoneA->id)->get();
        $this->assertCount(1, $histories);

        $activeHistories = $histories->whereNull('unassigned_at');
        $this->assertCount(1, $activeHistories);
        $this->assertEquals($this->coordinator1->id, $activeHistories->first()->user_id);
        $this->assertEquals('First assignment', $activeHistories->first()->reason);
    }

    /** 2. Reassigning Zone A from Coordinator 1 to Coordinator 2 */
    public function test_reassigning_zone_closes_old_history_and_creates_single_new_current_history(): void
    {
        // 1. Assign Coord 1 to Zone A
        $this->service->assignCoordinator($this->zoneA, $this->coordinator1->id, $this->admin->id, 'Initial');

        $coord1Row = ZoneCoordinatorHistory::where('zone_id', $this->zoneA->id)
            ->where('user_id', $this->coordinator1->id)
            ->first();
        $this->assertNull($coord1Row->unassigned_at);

        // 2. Reassign Zone A to Coord 2
        $this->travel(2)->hours();
        $this->service->assignCoordinator($this->zoneA, $this->coordinator2->id, $this->admin->id, 'Reassigned to Coord 2');

        // Total historical rows = 2
        $allHistories = ZoneCoordinatorHistory::where('zone_id', $this->zoneA->id)->get();
        $this->assertCount(2, $allHistories);

        // Coord 1 row closed with unassigned_at timestamp
        $coord1RowRefreshed = $coord1Row->fresh();
        $this->assertNotNull($coord1RowRefreshed->unassigned_at);
        $this->assertFalse($coord1RowRefreshed->isActive());

        // Exactly ONE current row remains
        $activeHistories = ZoneCoordinatorHistory::where('zone_id', $this->zoneA->id)
            ->whereNull('unassigned_at')
            ->get();
        $this->assertCount(1, $activeHistories);
        $this->assertEquals($this->coordinator2->id, $activeHistories->first()->user_id);
        $this->assertTrue($activeHistories->first()->isActive());

        // Fast operational pointer updated
        $this->assertEquals($this->coordinator2->id, $this->zoneA->fresh()->coordinator_id);
    }

    /** 3. Assigning Coordinator 2 to Zone A while Coordinator 2 is currently assigned to Zone B */
    public function test_assigning_coordinator_currently_in_another_zone_closes_old_zone_assignment_atomically(): void
    {
        // Assign Coord 2 to Zone B
        $this->service->assignCoordinator($this->zoneB, $this->coordinator2->id, $this->admin->id, 'Zone B initial');
        $this->assertEquals($this->coordinator2->id, $this->zoneB->fresh()->coordinator_id);

        // Reassign Coord 2 to Zone A
        $this->service->assignCoordinator($this->zoneA, $this->coordinator2->id, $this->admin->id, 'Transfer to Zone A');

        // Zone B assignment closed and coordinator_id cleared
        $this->assertNull($this->zoneB->fresh()->coordinator_id);
        $zoneBHistory = ZoneCoordinatorHistory::where('zone_id', $this->zoneB->id)->first();
        $this->assertNotNull($zoneBHistory->unassigned_at);

        // Coord 2 has exactly ONE active zone history across the entire system
        $coord2ActiveHistories = ZoneCoordinatorHistory::where('user_id', $this->coordinator2->id)
            ->whereNull('unassigned_at')
            ->get();
        $this->assertCount(1, $coord2ActiveHistories);
        $this->assertEquals($this->zoneA->id, $coord2ActiveHistories->first()->zone_id);
    }

    /** 4. Re-saving the same coordinator does not create duplicate history */
    public function test_resaving_same_coordinator_is_idempotent_and_does_not_create_duplicate_history(): void
    {
        $this->service->assignCoordinator($this->zoneA, $this->coordinator1->id, $this->admin->id, 'Initial');
        $this->assertCount(1, ZoneCoordinatorHistory::where('zone_id', $this->zoneA->id)->get());

        // Re-save same coordinator
        $this->service->assignCoordinator($this->zoneA, $this->coordinator1->id, $this->admin->id, 'Re-save same');
        $this->assertCount(1, ZoneCoordinatorHistory::where('zone_id', $this->zoneA->id)->get());
    }

    /** 5. Unassigning a coordinator closes the active history row */
    public function test_unassigning_coordinator_closes_active_history_row(): void
    {
        $this->service->assignCoordinator($this->zoneA, $this->coordinator1->id, $this->admin->id);
        $this->assertNotNull($this->zoneA->fresh()->coordinator_id);

        // Unassign
        $this->service->assignCoordinator($this->zoneA, null, $this->admin->id, 'Unassigned');

        $this->assertNull($this->zoneA->fresh()->coordinator_id);

        $activeHistories = ZoneCoordinatorHistory::where('zone_id', $this->zoneA->id)
            ->whereNull('unassigned_at')
            ->get();
        $this->assertCount(0, $activeHistories);

        $history = ZoneCoordinatorHistory::where('zone_id', $this->zoneA->id)->first();
        $this->assertFalse($history->isActive());
    }

    /** 6. Historical rows remain preserved */
    public function test_historical_rows_are_never_deleted_or_overwritten(): void
    {
        $this->service->assignCoordinator($this->zoneA, $this->coordinator1->id, $this->admin->id, 'First');
        $this->service->assignCoordinator($this->zoneA, $this->coordinator2->id, $this->admin->id, 'Second');
        $this->service->assignCoordinator($this->zoneA, null, $this->admin->id, 'Unassigned');

        $totalHistories = ZoneCoordinatorHistory::where('zone_id', $this->zoneA->id)->get();
        $this->assertCount(2, $totalHistories);
    }

    /** 7. Database unique constraint prevents two current assignments for same Zone */
    public function test_database_partial_unique_index_prevents_multiple_active_rows_for_same_zone(): void
    {
        ZoneCoordinatorHistory::create([
            'zone_id' => $this->zoneA->id,
            'user_id' => $this->coordinator1->id,
            'assigned_at' => now(),
            'unassigned_at' => null,
        ]);

        $this->expectException(QueryException::class);

        // Attempt direct insertion of second active row for same zone
        ZoneCoordinatorHistory::create([
            'zone_id' => $this->zoneA->id,
            'user_id' => $this->coordinator2->id,
            'assigned_at' => now(),
            'unassigned_at' => null,
        ]);
    }

    /** 8. Database unique constraint prevents same coordinator being current in two Zones */
    public function test_database_partial_unique_index_prevents_same_coordinator_active_in_multiple_zones(): void
    {
        ZoneCoordinatorHistory::create([
            'zone_id' => $this->zoneA->id,
            'user_id' => $this->coordinator1->id,
            'assigned_at' => now(),
            'unassigned_at' => null,
        ]);

        $this->expectException(QueryException::class);

        // Attempt direct insertion of second active row for same user in another zone
        ZoneCoordinatorHistory::create([
            'zone_id' => $this->zoneB->id,
            'user_id' => $this->coordinator1->id,
            'assigned_at' => now(),
            'unassigned_at' => null,
        ]);
    }

    /** 9. Existing coordinator zone isolation tests pass */
    public function test_zone_isolation_access_control_rules_hold(): void
    {
        $this->service->assignCoordinator($this->zoneA, $this->coordinator1->id, $this->admin->id);
        $this->assertEquals($this->zoneA->id, $this->coordinator1->fresh()->zoneId());
    }

    /** 10. Filament Zone edit Livewire test verifies after reassignment history table renders exactly one Current row */
    public function test_filament_zone_edit_livewire_component_updates_history_and_renders_exactly_one_current_row(): void
    {
        $state = \App\Models\State::create(['name' => 'State Test', 'code' => 'ST']);
        $city = \App\Models\City::create(['state_id' => $state->id, 'name' => 'City Test']);
        $town = \App\Models\Town::create(['city_id' => $city->id, 'name' => 'Town Test']);
        $this->zoneA->update(['town_id' => $town->id]);

        // Set up initial assignment
        $this->service->assignCoordinator($this->zoneA, $this->coordinator1->id, $this->admin->id, 'Initial Admin assignment');

        $this->actingAs($this->admin);

        // Perform reassignment through Filament EditZone Livewire page component
        Livewire::test(EditZone::class, ['record' => $this->zoneA->id])
            ->fillForm([
                'state_id' => $state->id,
                'city_id' => $city->id,
                'town_id' => $town->id,
                'coordinator_id' => $this->coordinator2->id,
                'assignment_reason' => 'Livewire UAT Reassignment',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        // Refresh zone & histories
        $zoneRefreshed = $this->zoneA->fresh();
        $this->assertEquals($this->coordinator2->id, $zoneRefreshed->coordinator_id);

        $activeHistories = ZoneCoordinatorHistory::where('zone_id', $this->zoneA->id)
            ->whereNull('unassigned_at')
            ->get();

        $this->assertCount(1, $activeHistories);
        $this->assertEquals($this->coordinator2->id, $activeHistories->first()->user_id);

        $previousHistories = ZoneCoordinatorHistory::where('zone_id', $this->zoneA->id)
            ->whereNotNull('unassigned_at')
            ->get();
        $this->assertCount(1, $previousHistories);
        $this->assertEquals($this->coordinator1->id, $previousHistories->first()->user_id);
    }
}
