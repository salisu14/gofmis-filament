<?php

namespace Tests\Feature;

use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemHealthCheckCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed expected system roles
        foreach (['super_admin', 'admin', 'coordinator', 'auditor', 'demo_observer'] as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }
    }

    public function test_system_health_check_runs_successfully(): void
    {
        $this->artisan('system:health-check')
            ->assertExitCode(0);
    }

    public function test_system_health_check_json_output_returns_valid_structure(): void
    {
        $this->artisan('system:health-check', ['--json' => true])
            ->assertExitCode(0);
    }
}
