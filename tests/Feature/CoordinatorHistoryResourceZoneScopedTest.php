<?php

namespace Tests\Feature;

use App\Filament\Coordinator\Concerns\ZoneScoped;
use App\Filament\Coordinator\Resources\OrphanHistoryResource;
use App\Filament\Coordinator\Resources\WidowHistoryResource;
use App\Models\Deceased;
use App\Models\Orphan;
use App\Models\Role;
use App\Models\User;
use App\Models\Widow;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use Tests\TestCase;

class CoordinatorHistoryResourceZoneScopedTest extends TestCase
{
    use RefreshDatabase;

    protected User $coordinatorZoneA;

    protected User $coordinatorZoneB;

    protected Zone $zoneA;

    protected Zone $zoneB;

    protected Deceased $deceasedA;

    protected Deceased $deceasedB;

    protected Widow $historicalWidowZoneA;

    protected Widow $historicalWidowZoneB;

    protected Orphan $historicalOrphanZoneA;

    protected Orphan $historicalOrphanZoneB;

    protected function setUp(): void
    {
        parent::setUp();

        $coordinatorRole = Role::firstOrCreate(['name' => 'coordinator', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $viewWidowsPermission = \App\Models\Permission::firstOrCreate(['name' => 'view_widows', 'guard_name' => 'web']);
        $viewOrphansPermission = \App\Models\Permission::firstOrCreate(['name' => 'view_orphans', 'guard_name' => 'web']);
        $coordinatorRole->givePermissionTo([$viewWidowsPermission, $viewOrphansPermission]);

        $this->coordinatorZoneA = User::factory()->create(['name' => 'Coordinator A']);
        $this->coordinatorZoneA->assignRole('coordinator');

        $this->coordinatorZoneB = User::factory()->create(['name' => 'Coordinator B']);
        $this->coordinatorZoneB->assignRole('coordinator');

        $this->zoneA = Zone::create([
            'name' => 'Zone A',
            'code' => 'Z-A',
            'coordinator_id' => $this->coordinatorZoneA->id,
        ]);

        $this->zoneB = Zone::create([
            'name' => 'Zone B',
            'code' => 'Z-B',
            'coordinator_id' => $this->coordinatorZoneB->id,
        ]);

        $this->coordinatorZoneA = $this->coordinatorZoneA->fresh();
        $this->coordinatorZoneB = $this->coordinatorZoneB->fresh();

        $this->deceasedA = Deceased::factory()->create([
            'zone_id' => $this->zoneA->id,
        ]);

        $this->deceasedB = Deceased::factory()->create([
            'zone_id' => $this->zoneB->id,
        ]);

        $this->historicalWidowZoneA = Widow::create([
            'deceased_id' => $this->deceasedA->id,
            'reg_no' => 'WID-HA-1',
            'first_name' => 'Widow',
            'last_name' => 'HistA',
            'nin' => '11111111111',
            'address' => 'Addr A',
            'child_sequence' => 1,
            'is_eligible' => false,
            'is_married' => true,
            'status' => 'remarried',
        ]);

        $this->historicalWidowZoneB = Widow::create([
            'deceased_id' => $this->deceasedB->id,
            'reg_no' => 'WID-HB-1',
            'first_name' => 'Widow',
            'last_name' => 'HistB',
            'nin' => '22222222222',
            'address' => 'Addr B',
            'child_sequence' => 1,
            'is_eligible' => false,
            'is_married' => true,
            'status' => 'remarried',
        ]);

        $this->historicalOrphanZoneA = Orphan::create([
            'deceased_id' => $this->deceasedA->id,
            'reg_no' => 'ORP-HA-1',
            'first_name' => 'Orphan',
            'last_name' => 'HistA',
            'gender' => \App\Enums\Gender::MALE->value,
            'birth_date' => now()->subYears(20)->format('Y-m-d'),
            'is_married' => false,
            'is_eligible' => false,
            'status' => 'archived',
        ]);

        $this->historicalOrphanZoneB = Orphan::create([
            'deceased_id' => $this->deceasedB->id,
            'reg_no' => 'ORP-HB-1',
            'first_name' => 'Orphan',
            'last_name' => 'HistB',
            'gender' => \App\Enums\Gender::MALE->value,
            'birth_date' => now()->subYears(20)->format('Y-m-d'),
            'is_married' => false,
            'is_eligible' => false,
            'status' => 'archived',
        ]);
    }

    public function test_all_zone_scoped_resources_satisfy_trait_contract(): void
    {
        $resourceClasses = [
            \App\Filament\Coordinator\Resources\DeceasedResource::class,
            \App\Filament\Coordinator\Resources\WidowResource::class,
            \App\Filament\Coordinator\Resources\OrphanResource::class,
            \App\Filament\Coordinator\Resources\ProjectResource::class,
            \App\Filament\Coordinator\Resources\WidowHistoryResource::class,
            \App\Filament\Coordinator\Resources\OrphanHistoryResource::class,
        ];

        foreach ($resourceClasses as $className) {
            $this->assertTrue(class_exists($className), "Class {$className} must exist and be autoloadable without fatal errors.");

            $reflection = new ReflectionClass($className);
            $this->assertFalse($reflection->isAbstract(), "Class {$className} must not be abstract.");

            $traits = $reflection->getTraitNames();
            $this->assertContains(ZoneScoped::class, $traits, "Class {$className} must use ZoneScoped trait.");

            // Verify abstract methods from ZoneScoped trait are implemented
            $this->assertTrue($reflection->hasMethod('applyZoneScope'), "Class {$className} must implement applyZoneScope.");
            $this->assertTrue($reflection->hasMethod('getRecordZoneId'), "Class {$className} must implement getRecordZoneId.");

            $applyZoneScopeMethod = $reflection->getMethod('applyZoneScope');
            $this->assertFalse($applyZoneScopeMethod->isAbstract(), "applyZoneScope in {$className} must not be abstract.");

            $getRecordZoneIdMethod = $reflection->getMethod('getRecordZoneId');
            $this->assertFalse($getRecordZoneIdMethod->isAbstract(), "getRecordZoneId in {$className} must not be abstract.");
        }
    }

    public function test_widow_history_resource_enforces_zone_scoping_and_read_only(): void
    {
        $this->actingAs($this->coordinatorZoneA, 'web');

        // Check query scoping
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('coordinator'));
        $queryResults = WidowHistoryResource::getEloquentQuery()->pluck('id')->toArray();

        $this->assertContains($this->historicalWidowZoneA->id, $queryResults);
        $this->assertNotContains($this->historicalWidowZoneB->id, $queryResults);

        // Check record-level authorization
        $this->assertTrue(WidowHistoryResource::canView($this->historicalWidowZoneA));
        $this->assertFalse(WidowHistoryResource::canView($this->historicalWidowZoneB));

        // Check read-only invariants
        $this->assertFalse(WidowHistoryResource::canCreate());
        $this->assertFalse(WidowHistoryResource::canEdit($this->historicalWidowZoneA));
        $this->assertFalse(WidowHistoryResource::canDelete($this->historicalWidowZoneA));
    }

    public function test_orphan_history_resource_enforces_zone_scoping_and_read_only(): void
    {
        $this->actingAs($this->coordinatorZoneA, 'web');

        // Check query scoping
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('coordinator'));
        $queryResults = OrphanHistoryResource::getEloquentQuery()->pluck('id')->toArray();

        $this->assertContains($this->historicalOrphanZoneA->id, $queryResults);
        $this->assertNotContains($this->historicalOrphanZoneB->id, $queryResults);

        // Check record-level authorization
        $this->assertTrue(OrphanHistoryResource::canView($this->historicalOrphanZoneA));
        $this->assertFalse(OrphanHistoryResource::canView($this->historicalOrphanZoneB));

        // Check read-only invariants
        $this->assertFalse(OrphanHistoryResource::canCreate());
        $this->assertFalse(OrphanHistoryResource::canEdit($this->historicalOrphanZoneA));
        $this->assertFalse(OrphanHistoryResource::canDelete($this->historicalOrphanZoneA));
    }
}
