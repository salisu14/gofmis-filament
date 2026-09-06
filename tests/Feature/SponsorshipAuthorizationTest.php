<?php

namespace Tests\Feature;

use App\Filament\Resources\Sponsors\SponsorResource;
use App\Filament\Resources\Sponsorships\SponsorshipResource;
use App\Models\Sponsorship;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SponsorshipAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected User $admin;

    protected User $demoObserver;

    protected User $coordinator;

    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->superAdmin = User::factory()->create([
            'email' => 'superadmin@gofmis.test',
            'status' => \App\Enums\UserStatus::ACTIVE,
        ]);
        $this->superAdmin->assignRole('super_admin');

        $this->admin = User::factory()->create([
            'email' => 'admin@gofmis.test',
            'status' => \App\Enums\UserStatus::ACTIVE,
        ]);
        $this->admin->assignRole('admin');

        $this->demoObserver = User::factory()->create([
            'email' => 'demo@gofmis.test',
            'status' => \App\Enums\UserStatus::ACTIVE,
        ]);
        $this->demoObserver->assignRole('demo_observer');

        $this->coordinator = User::factory()->create([
            'email' => 'coordinator@gofmis.test',
            'status' => \App\Enums\UserStatus::ACTIVE,
        ]);
        $this->coordinator->assignRole('coordinator');

        $this->regularUser = User::factory()->create([
            'email' => 'user@gofmis.test',
            'status' => \App\Enums\UserStatus::ACTIVE,
        ]);
    }

    public function test_super_admin_has_full_sponsorship_access(): void
    {
        $this->actingAs($this->superAdmin);

        $this->assertTrue($this->superAdmin->can('view_sponsorships'));
        $this->assertTrue(SponsorResource::canViewAny());
        $this->assertTrue(SponsorshipResource::canViewAny());
        $this->assertTrue(SponsorshipResource::canCreate());
    }

    public function test_admin_has_full_sponsorship_access(): void
    {
        $this->actingAs($this->admin);

        $this->assertTrue($this->admin->can('view_sponsorships'));
        $this->assertTrue(SponsorResource::canViewAny());
        $this->assertTrue(SponsorshipResource::canViewAny());
        $this->assertTrue(SponsorshipResource::canCreate());
        $this->assertTrue(SponsorshipResource::canEdit(new Sponsorship));
    }

    public function test_demo_observer_has_read_only_sponsorship_access(): void
    {
        $this->actingAs($this->demoObserver);

        $this->assertTrue($this->demoObserver->can('view_sponsorships'));
        $this->assertTrue(SponsorResource::canViewAny());
        $this->assertTrue(SponsorshipResource::canViewAny());

        $this->assertFalse(SponsorshipResource::canCreate());
        $this->assertFalse(SponsorshipResource::canEdit(new Sponsorship));
        $this->assertFalse(SponsorshipResource::canDelete(new Sponsorship));
    }

    public function test_coordinator_is_denied_sponsorship_access(): void
    {
        $this->actingAs($this->coordinator);

        $this->assertFalse($this->coordinator->can('view_sponsorships'));
        $this->assertFalse(SponsorResource::canViewAny());
        $this->assertFalse(SponsorshipResource::canViewAny());
        $this->assertFalse(SponsorshipResource::canCreate());
    }

    public function test_unauthorized_user_is_denied_sponsorship_access(): void
    {
        $this->actingAs($this->regularUser);

        $this->assertFalse($this->regularUser->can('view_sponsorships'));
        $this->assertFalse(SponsorResource::canViewAny());
        $this->assertFalse(SponsorshipResource::canViewAny());
    }
}
