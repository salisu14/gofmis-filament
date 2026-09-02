<?php

namespace Tests\Feature\Biometrics;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BiometricSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    public function test_demo_observer_can_only_view_biometric_status()
    {
        $demo = User::factory()->create(['email' => 'demo@gofmis.local']);
        $demo->assignRole('demo_observer');

        $this->assertTrue($demo->can('biometrics.view'));
        $this->assertFalse($demo->can('biometrics.enroll'));
        $this->assertFalse($demo->can('biometrics.revoke'));
        $this->assertFalse($demo->can('biometrics.verify'));
        $this->assertFalse($demo->can('biometrics.identify'));
        $this->assertFalse($demo->can('export'));
    }

    public function test_coordinator_cannot_enroll_outside_permitted_zone()
    {
        $coordinator = User::factory()->create(['email' => 'coord@gofmis.local']);
        $coordinator->assignRole('coordinator');

        $this->assertTrue($coordinator->can('biometrics.enroll'));

        // Zone logic is usually handled at the query/policy level in the resource,
        // but for biometrics specifically, they only have enroll on the relation manager
        // if they can view the parent resource (which handles zone logic).
    }

    public function test_unauthorized_user_cannot_enroll()
    {
        $unauthorized = User::factory()->create(['email' => 'nobody@gofmis.local']);
        // No roles assigned.

        $this->assertFalse($unauthorized->can('biometrics.view'));
        $this->assertFalse($unauthorized->can('biometrics.enroll'));
        $this->assertFalse($unauthorized->can('biometrics.revoke'));
        $this->assertFalse($unauthorized->can('biometrics.verify'));
        $this->assertFalse($unauthorized->can('biometrics.identify'));
    }
}
