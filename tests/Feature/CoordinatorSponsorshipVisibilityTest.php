<?php

namespace Tests\Feature;

use App\Enums\Gender;
use App\Enums\OrphanStatus;
use App\Enums\SponsorType;
use App\Models\Deceased;
use App\Models\Orphan;
use App\Models\Sponsor;
use App\Models\Sponsorship;
use App\Models\User;
use App\Models\Zone;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoordinatorSponsorshipVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected User $coordinatorZoneA;

    protected User $coordinatorZoneB;

    protected Zone $zoneA;

    protected Zone $zoneB;

    protected Deceased $deceasedZoneA;

    protected Deceased $deceasedZoneB;

    protected Orphan $orphanZoneA;

    protected Orphan $orphanZoneB;

    protected Sponsor $sponsor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->coordinatorZoneA = User::factory()->create([
            'email' => 'coord_a@gofmis.test',
            'status' => \App\Enums\UserStatus::ACTIVE,
        ]);
        $this->coordinatorZoneA->assignRole('coordinator');

        $this->coordinatorZoneB = User::factory()->create([
            'email' => 'coord_b@gofmis.test',
            'status' => \App\Enums\UserStatus::ACTIVE,
        ]);
        $this->coordinatorZoneB->assignRole('coordinator');

        $this->zoneA = Zone::create([
            'name' => 'Zone A',
            'code' => 'ZA01',
            'coordinator_id' => $this->coordinatorZoneA->id,
        ]);

        $this->zoneB = Zone::create([
            'name' => 'Zone B',
            'code' => 'ZB01',
            'coordinator_id' => $this->coordinatorZoneB->id,
        ]);

        $this->deceasedZoneA = Deceased::factory()->create([
            'zone_id' => $this->zoneA->id,
        ]);

        $this->deceasedZoneB = Deceased::factory()->create([
            'zone_id' => $this->zoneB->id,
        ]);

        $this->orphanZoneA = Orphan::create([
            'deceased_id' => $this->deceasedZoneA->id,
            'reg_no' => 'ORP-ZA-01',
            'first_name' => 'Farouk',
            'last_name' => 'ZoneA',
            'gender' => Gender::MALE,
            'birth_date' => '2016-01-01',
            'status' => OrphanStatus::ACTIVE,
            'is_eligible' => true,
        ]);

        $this->orphanZoneB = Orphan::create([
            'deceased_id' => $this->deceasedZoneB->id,
            'reg_no' => 'ORP-ZB-01',
            'first_name' => 'Habib',
            'last_name' => 'ZoneB',
            'gender' => Gender::MALE,
            'birth_date' => '2017-01-01',
            'status' => OrphanStatus::ACTIVE,
            'is_eligible' => true,
        ]);

        $this->sponsor = Sponsor::create([
            'name' => 'Crescent Aid',
            'type' => SponsorType::NGO,
        ]);

        Sponsorship::create([
            'orphan_id' => $this->orphanZoneA->id,
            'sponsor_id' => $this->sponsor->id,
            'amount_committed' => 180000,
            'start_date' => now()->subMonth(),
            'end_date' => now()->addYear(),
        ]);

        Filament::setCurrentPanel(Filament::getPanel('coordinator'));
    }

    public function test_coordinator_cannot_access_admin_sponsor_master_resources(): void
    {
        $this->actingAs($this->coordinatorZoneA);

        $response = $this->get('/admin/donors');
        $response->assertStatus(403);

        $response2 = $this->get('/admin/sponsorships');
        $response2->assertStatus(403);
    }

    public function test_coordinator_can_see_sponsorship_status_for_own_zone_orphan(): void
    {
        $this->actingAs($this->coordinatorZoneA);

        $this->assertTrue($this->orphanZoneA->hasActiveSponsorship());
        $this->assertFalse($this->orphanZoneB->hasActiveSponsorship());

        $this->assertEquals(
            'Crescent Aid',
            $this->orphanZoneA->activeSponsorships()->first()?->sponsor_name
        );
    }

    public function test_zone_isolation_prevents_coordinator_from_seeing_other_zone_orphans(): void
    {
        $this->actingAs($this->coordinatorZoneA);

        $visibleOrphanIds = Orphan::pluck('id')->toArray();

        $this->assertContains($this->orphanZoneA->id, $visibleOrphanIds);
        $this->assertNotContains($this->orphanZoneB->id, $visibleOrphanIds);
    }
}
