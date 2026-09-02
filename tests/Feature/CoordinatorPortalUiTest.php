<?php

namespace Tests\Feature;

use App\Filament\Coordinator\Widgets\PendingItemsWidget;
use App\Filament\Coordinator\Widgets\QuickActionsWidget;
use App\Filament\Coordinator\Widgets\RecentActivityWidget;
use App\Filament\Coordinator\Widgets\ZoneStatsWidget;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CoordinatorPortalUiTest extends TestCase
{
    use RefreshDatabase;

    protected User $coordinator;

    protected Zone $zone;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->coordinator = User::factory()->create([
            'email' => 'coord_ui@gofmis.local',
            'name' => 'UI Test Coordinator',
            'status' => \App\Enums\UserStatus::ACTIVE,
        ]);
        $this->coordinator->assignRole('coordinator');

        $this->zone = Zone::create([
            'name' => 'A1',
            'address' => '100 Zone A1 Street',
            'coordinator_id' => $this->coordinator->id,
        ]);
        $this->coordinator = $this->coordinator->fresh();

        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('coordinator'));
    }

    public function test_coordinator_dashboard_renders_safely_and_without_oversized_image_markup(): void
    {
        $response = $this->actingAs($this->coordinator, 'web')
            ->get('/coordinator');

        $response->assertStatus(200);
        $response->assertSee('Coordinator Portal - Garko Foundation');
        // Ensure no broken image tag with unconstrained brandLogo asset
        $response->assertDontSee('storage/logos/gof_logo.jpeg');

        // Verify that deterministic CSS sizing rules and scoped classes are present in rendered document head
        $response->assertSee('.coordinator-action-icon-wrapper', false);
        $response->assertSee('.coordinator-widget-icon-sm', false);
        $response->assertSee('.fi-wi-stats-overview-stat-icon', false);
    }

    public function test_coordinator_dashboard_widgets_render_successfully(): void
    {
        $this->actingAs($this->coordinator, 'web');

        Livewire::test(ZoneStatsWidget::class)
            ->assertStatus(200);

        Livewire::test(QuickActionsWidget::class)
            ->assertStatus(200)
            ->assertSee('Register Deceased')
            ->assertSee('Add Orphan')
            ->assertSee('Add Widow')
            ->assertSee('Education Request')
            ->assertSee('Welfare Nomination')
            ->assertDontSee('Loan Request')
            ->assertSeeHtml('coordinator-quick-action-card')
            ->assertSeeHtml('coordinator-action-icon')
            ->assertSeeHtml('style="width: 1.25rem !important;');

        Livewire::test(RecentActivityWidget::class)
            ->assertStatus(200)
            ->assertSeeHtml('coordinator-widget-icon-sm');

        Livewire::test(PendingItemsWidget::class)
            ->assertStatus(200)
            ->assertSee('Pending Loans')
            ->assertSee('Pending Education')
            ->assertSee('Recent Healthcare')
            ->assertSee('Pending Welfare')
            ->assertSeeHtml('coordinator-pending-tile')
            ->assertSeeHtml('coordinator-widget-icon-sm');
    }

    public function test_recent_activity_widget_is_bounded_to_maximum_5_entries(): void
    {
        $this->actingAs($this->coordinator, 'web');

        // Create 10 deceased records in coordinator's zone
        \App\Models\Deceased::factory()->count(10)->create([
            'zone_id' => $this->zone->id,
        ]);

        $component = Livewire::test(RecentActivityWidget::class);

        $component->assertStatus(200);

        // Assert activities payload is strictly capped at 5
        $activities = $component->viewData('activities');
        $this->assertCount(5, $activities);
    }

    public function test_coordinator_can_access_all_permitted_resource_routes(): void
    {
        $routes = [
            '/coordinator/deceaseds',
            '/coordinator/widows',
            '/coordinator/orphans',
            '/coordinator/education-requests',
            '/coordinator/welfare-requests',
        ];

        foreach ($routes as $route) {
            $response = $this->actingAs($this->coordinator, 'web')->get($route);
            $response->assertStatus(200);
        }

        // Out-of-scope route must return 403 Forbidden
        $this->actingAs($this->coordinator, 'web')->get('/coordinator/loan-requests')->assertStatus(403);
    }
}
