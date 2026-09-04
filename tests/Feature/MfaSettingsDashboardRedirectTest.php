<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MfaSettingsDashboardRedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    private function createUserWithRole(string $roleName): User
    {
        $user = User::factory()->create([
            'is_active' => true,
            'status' => \App\Enums\UserStatus::ACTIVE,
        ]);

        $role = Role::where('name', $roleName)->first();
        if (! $role) {
            $role = Role::create(['name' => $roleName, 'uuid' => (string) \Illuminate\Support\Str::uuid()]);
        }

        $user->assignRole($roleName);

        return $user;
    }

    /** @test */
    public function super_admin_back_to_dashboard_resolves_to_admin_dashboard()
    {
        $user = $this->createUserWithRole('super_admin');

        $url = $user->getDashboardUrl();
        $this->assertEquals(Filament::getPanel('admin')->getUrl(), $url);

        $this->actingAs($user)->withSession(['mfa_verified_user_id' => $user->id, 'mfa_verified_at' => time()]);
        $response = $this->get('/mfa/settings');
        $response->assertStatus(200);
        $response->assertSee($url);
    }

    /** @test */
    public function admin_back_to_dashboard_resolves_to_admin_dashboard()
    {
        $user = $this->createUserWithRole('admin');

        $url = $user->getDashboardUrl();
        $this->assertEquals(Filament::getPanel('admin')->getUrl(), $url);

        $this->actingAs($user)->withSession(['mfa_verified_user_id' => $user->id, 'mfa_verified_at' => time()]);
        $response = $this->get('/mfa/settings');
        $response->assertStatus(200);
        $response->assertSee($url);
    }

    /** @test */
    public function coordinator_resolves_to_coordinator_dashboard()
    {
        $user = $this->createUserWithRole('coordinator');

        // Ensure the coordinator has a zone so isAssignedCoordinator is true if needed by other things
        \App\Models\Zone::create([
            'name' => 'Test Zone',
            'coordinator_name' => 'John',
            'coordinator_phone' => '123',
            'coordinator_id' => $user->id,
        ]);

        $url = $user->getDashboardUrl();
        $this->assertEquals(Filament::getPanel('coordinator')->getUrl(), $url);
        $this->assertNotEquals(Filament::getPanel('admin')->getUrl(), $url);

        $this->actingAs($user)->withSession(['mfa_verified_user_id' => $user->id, 'mfa_verified_at' => time()]);
        $response = $this->get('/mfa/settings');
        $response->assertStatus(200);
        $response->assertSee($url);
    }

    /** @test */
    public function demo_observer_resolves_to_authorized_dashboard()
    {
        $user = $this->createUserWithRole('demo_observer');

        $url = $user->getDashboardUrl();
        // demo_observer has access to admin panel
        $this->assertEquals(Filament::getPanel('admin')->getUrl(), $url);

        $this->actingAs($user)->withSession(['mfa_verified_user_id' => $user->id, 'mfa_verified_at' => time()]);
        $response = $this->get('/mfa/settings');
        $response->assertStatus(200);
        $response->assertSee($url);
    }

    /** @test */
    public function auditor_resolves_to_authorized_dashboard()
    {
        $user = $this->createUserWithRole('auditor');

        $url = $user->getDashboardUrl();
        // auditor has access to admin panel
        $this->assertEquals(Filament::getPanel('admin')->getUrl(), $url);

        $this->actingAs($user)->withSession(['mfa_verified_user_id' => $user->id, 'mfa_verified_at' => time()]);
        $response = $this->get('/mfa/settings');
        $response->assertStatus(200);
        $response->assertSee($url);
    }

    /** @test */
    public function unauthenticated_access_to_mfa_settings_follows_auth_behavior()
    {
        $response = $this->get('/mfa/settings');
        // Because of MfaSettings mount logic, it redirects to filament.admin.auth.login
        $response->assertRedirect(route('filament.admin.auth.login'));
    }

    /** @test */
    public function mfa_settings_page_renders_successfully_for_authorized_non_admin_user()
    {
        $user = $this->createUserWithRole('coordinator');
        \App\Models\Zone::create([
            'name' => 'Test Zone 2',
            'coordinator_name' => 'John',
            'coordinator_phone' => '123',
            'coordinator_id' => $user->id,
        ]);

        $this->actingAs($user)->withSession(['mfa_verified_user_id' => $user->id, 'mfa_verified_at' => time()]);
        $response = $this->get('/mfa/settings');
        $response->assertStatus(200);
        $response->assertSee('Back to Dashboard');
    }
}
