<?php

namespace Tests\Feature;

use App\Models\Deceased;
use App\Models\Orphan;
use App\Models\User;
use App\Models\Widow;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CoordinatorPortalScopeLockdownTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $coordinator;

    protected Zone $zone;

    protected Zone $otherZone;

    protected User $otherCoordinator;

    protected Deceased $deceased;

    protected Widow $widow;

    protected Orphan $orphan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->coordinator = User::factory()->create();
        $this->coordinator->assignRole('coordinator');

        $this->otherCoordinator = User::factory()->create();
        $this->otherCoordinator->assignRole('coordinator');

        $this->zone = Zone::create(['name' => 'Kano Central', 'code' => 'KCZ', 'coordinator_id' => $this->coordinator->id]);
        $this->otherZone = Zone::create(['name' => 'Kano South', 'code' => 'KSZ', 'coordinator_id' => $this->otherCoordinator->id]);

        $this->coordinator = $this->coordinator->fresh();
        $this->otherCoordinator = $this->otherCoordinator->fresh();

        $this->deceased = Deceased::create([
            'first_name' => 'Kano',
            'last_name' => 'Parent',
            'nin' => '12345678901',
            'reg_no' => 'DEC-KCZ-101',
            'guardian_name' => 'Guardian Name',
            'guardian_phone' => '08012345678',
            'vulnerability_status' => 'A',
            'date_registered' => now(),
            'zone_id' => $this->zone->id,
        ]);

        $this->widow = Widow::create([
            'deceased_id' => $this->deceased->id,
            'reg_no' => 'WID-KCZ-101',
            'first_name' => 'Amina',
            'last_name' => 'Kano',
            'nin' => '98765432101',
            'address' => 'Kano Address',
            'child_sequence' => 1,
            'is_eligible' => true,
            'is_married' => false,
            'status' => 'active',
        ]);

        $this->orphan = Orphan::create([
            'deceased_id' => $this->deceased->id,
            'reg_no' => 'ORP-KCZ-101',
            'first_name' => 'Sani',
            'last_name' => 'Kano',
            'gender' => \App\Enums\Gender::MALE->value,
            'birth_date' => now()->subYears(10)->format('Y-m-d'),
            'is_married' => false,
            'is_eligible' => true,
            'status' => 'active',
        ]);
    }

    public function test_1_coordinator_can_access_deceased_in_own_zone(): void
    {
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('coordinator'));

        Livewire::actingAs($this->coordinator)
            ->test(\App\Filament\Coordinator\Resources\DeceasedResource\Pages\ListDeceaseds::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$this->deceased]);
    }

    public function test_2_coordinator_can_access_widows_in_own_zone(): void
    {
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('coordinator'));

        Livewire::actingAs($this->coordinator)
            ->test(\App\Filament\Coordinator\Resources\WidowResource\Pages\ListWidows::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$this->widow]);
    }

    public function test_3_coordinator_can_access_orphans_in_own_zone(): void
    {
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('coordinator'));

        Livewire::actingAs($this->coordinator)
            ->test(\App\Filament\Coordinator\Resources\OrphanResource\Pages\ListOrphans::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$this->orphan]);
    }

    public function test_4_coordinator_can_access_education_requests_in_own_zone(): void
    {
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('coordinator'));

        Livewire::actingAs($this->coordinator)
            ->test(\App\Filament\Coordinator\Resources\EducationRequestResource\Pages\ListEducationRequests::class)
            ->assertSuccessful();
    }

    public function test_5_coordinator_can_access_welfare_nominations_in_own_zone(): void
    {
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('coordinator'));

        Livewire::actingAs($this->coordinator)
            ->test(\App\Filament\Coordinator\Resources\WelfareRequestResource\Pages\ListWelfareRequests::class)
            ->assertSuccessful();
    }

    public function test_6_coordinator_cannot_see_healthcare_navigation(): void
    {
        $this->actingAs($this->coordinator);
        expect(\App\Filament\Coordinator\Resources\HealthcareRequestResource::canViewAny())->toBeFalse();
    }

    public function test_7_coordinator_cannot_access_healthcare_by_direct_url(): void
    {
        $response = $this->actingAs($this->coordinator)
            ->get('/coordinator/healthcare-requests');

        $response->assertStatus(403);
    }

    public function test_8_coordinator_cannot_invoke_healthcare_mutation_through_livewire(): void
    {
        $this->actingAs($this->coordinator);
        expect(\App\Filament\Coordinator\Resources\HealthcareRequestResource::canCreate())->toBeFalse();
    }

    public function test_9_coordinator_cannot_access_education_analytics(): void
    {
        expect($this->coordinator->can('orphan_education.analytics.view'))->toBeFalse();
        expect($this->coordinator->can('orphan_education.analytics.export'))->toBeFalse();
    }

    public function test_10_coordinator_cannot_invoke_academic_progression_override(): void
    {
        expect($this->coordinator->can('orphan_education.override_academic_progression'))->toBeFalse();
    }

    public function test_11_coordinator_cannot_access_sponsorship_administration(): void
    {
        expect($this->coordinator->can('view_sponsorships'))->toBeFalse();
        expect($this->coordinator->can('create_sponsorships'))->toBeFalse();
    }

    public function test_12_coordinator_cannot_access_finance_and_loans(): void
    {
        expect($this->coordinator->can('view_loans'))->toBeFalse();
        expect($this->coordinator->can('create_loans'))->toBeFalse();

        $response = $this->actingAs($this->coordinator)->get('/coordinator/loan-requests');
        $response->assertStatus(403);
    }

    public function test_13_coordinator_cannot_access_user_administration(): void
    {
        expect($this->coordinator->can('view_users'))->toBeFalse();
        expect($this->coordinator->can('create_users'))->toBeFalse();
    }

    public function test_14_coordinator_cannot_access_company_settings(): void
    {
        expect($this->coordinator->can('view_settings'))->toBeFalse();
        expect($this->coordinator->can('edit_settings'))->toBeFalse();
    }

    public function test_15_coordinator_cannot_access_imprest(): void
    {
        expect($this->coordinator->can('imprest_view_transactions'))->toBeFalse();
        expect($this->coordinator->can('imprest_create_transactions'))->toBeFalse();
    }

    public function test_16_cross_zone_beneficiary_isolation_remains_enforced(): void
    {
        $otherDeceased = Deceased::create([
            'first_name' => 'South',
            'last_name' => 'Parent',
            'nin' => '12345678902',
            'reg_no' => 'DEC-KSZ-101',
            'guardian_name' => 'Guardian Two',
            'guardian_phone' => '08012345679',
            'vulnerability_status' => 'A',
            'date_registered' => now(),
            'zone_id' => $this->otherZone->id,
        ]);

        Livewire::actingAs($this->coordinator)
            ->test(\App\Filament\Coordinator\Resources\DeceasedResource\Pages\ListDeceaseds::class)
            ->assertDontSee($otherDeceased->display_name);
    }

    public function test_17_dashboard_contains_no_healthcare_quick_action(): void
    {
        $widget = Livewire::actingAs($this->coordinator)
            ->test(\App\Filament\Coordinator\Widgets\QuickActionsWidget::class);

        $actions = collect($widget->viewData('actions'));
        $labels = $actions->pluck('label')->toArray();

        expect($labels)->not->toContain('Healthcare Request');
        expect($labels)->not->toContain('Loan Request');
        expect($labels)->toContain('Register Deceased');
        expect($labels)->toContain('Add Widow');
        expect($labels)->toContain('Add Orphan');
        expect($labels)->toContain('Education Request');
        expect($labels)->toContain('Welfare Nomination');
    }

    public function test_18_dashboard_contains_no_healthcare_pending_count(): void
    {
        $widget = Livewire::actingAs($this->coordinator)
            ->test(\App\Filament\Coordinator\Widgets\PendingItemsWidget::class);

        $counts = $widget->viewData('counts');
        expect(array_keys($counts))->not->toContain('healthcare');
        expect(array_keys($counts))->not->toContain('loans');
        expect(array_keys($counts))->toContain('education');
        expect(array_keys($counts))->toContain('welfare');
    }

    public function test_19_dashboard_contains_no_healthcare_recent_activity(): void
    {
        $widget = Livewire::actingAs($this->coordinator)
            ->test(\App\Filament\Coordinator\Widgets\RecentActivityWidget::class);

        $activities = collect($widget->viewData('activities'));
        $types = $activities->pluck('type')->toArray();

        expect($types)->not->toContain('healthcare_requested');
        expect($types)->not->toContain('loan_requested');
    }

    public function test_20_admin_and_super_admin_healthcare_access_remains_unchanged(): void
    {
        $this->actingAs($this->admin);
        expect(\App\Filament\Coordinator\Resources\HealthcareRequestResource::canViewAny())->toBeTrue();
        expect(\App\Filament\Coordinator\Resources\HealthcareRequestResource::canCreate())->toBeTrue();
        expect($this->admin->can('view_healthcare_interventions'))->toBeTrue();
    }
}
