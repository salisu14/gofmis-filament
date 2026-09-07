<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class PortalLandingPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Removed $this->withoutVite() to PROVE that the page doesn't depend on Vite manifest
        // If it did, tests would throw ViteManifestNotFoundException

        \App\Models\CompanyInformation::instance();
        Artisan::call('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);

        $this->app['env'] = 'production';
    }

    public function test_guest_can_view_landing_page_without_vite()
    {
        $response = $this->get('/');

        $response->assertStatus(200);

        // 2. Branding still renders
        $response->assertSee('Garko Orphans Foundation');
        $response->assertSee('Management Information System');

        // 3. Guest portal cards still render
        $response->assertSee('Administration Portal');
        $response->assertSee('Coordinator Portal');
        $response->assertSee('Log in');

        // 4. Admin link is correct
        $response->assertSee(url('admin/login'));
        // 5. Coordinator link is correct
        $response->assertSee(url('coordinator/login'));

        // 9. No protected data appears on guest page
        $response->assertDontSee('Open Portal');
        $response->assertDontSee('Sign out');
    }

    public function test_super_admin_can_view_landing_page_with_authorized_portals()
    {
        $user = User::factory()->create(['is_active' => true, 'status' => UserStatus::ACTIVE]);
        $user->assignRole('super_admin');

        $response = $this->actingAs($user)->get('/');

        $response->assertStatus(200);

        // 6. Super Admin role-aware behavior remains correct
        $response->assertSee('Administration Portal');
        $response->assertSee('Coordinator Portal');
        $response->assertSee('Open Portal');
        $response->assertSee('Sign out');
        $response->assertSee($user->name);
        $response->assertDontSee('Log in');
    }

    public function test_coordinator_cannot_gain_admin_access_on_landing_page()
    {
        $user = User::factory()->create(['is_active' => true, 'status' => UserStatus::ACTIVE]);
        $user->assignRole('coordinator');

        $response = $this->actingAs($user)->get('/');

        $response->assertStatus(200);

        // 7. Coordinator cannot gain Admin access
        $response->assertSee('Coordinator Portal');
        $response->assertSee('Open Portal');
        $response->assertDontSee('Administration Portal');
    }

    public function test_demo_observer_sees_only_its_allowed_portal()
    {
        $user = User::factory()->create(['is_active' => true, 'status' => UserStatus::ACTIVE, 'is_protected_system_account' => true]);
        $user->assignRole('demo_observer');

        $response = $this->actingAs($user)->get('/');

        $response->assertStatus(200);

        // 8. Demo Observer sees only its allowed portal/read-only presentation
        $response->assertSee('Administration Portal — Read Only');
        $response->assertSee('Open Portal');
        $response->assertDontSee('Coordinator Portal');
    }
}
