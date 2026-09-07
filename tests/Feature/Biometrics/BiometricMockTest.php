<?php

namespace Tests\Feature\Biometrics;

use App\Contracts\Biometrics\FingerprintDeviceClientInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BiometricMockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->seed(\Database\Seeders\ZonesTableSeeder::class);
        $zoneId = \App\Models\Zone::first()->id ?? \Illuminate\Support\Str::uuid()->toString();

        $deceasedId = (string) \Illuminate\Support\Str::uuid();
        DB::table('deceased')->insert([
            'id' => $deceasedId,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'nin' => '12345678901'.rand(10, 99),
            'reg_no' => 'REG-'.rand(1000, 9999).rand(1, 9),
            'guardian_name' => 'Guardian',
            'guardian_phone' => '1234567890',
            'vulnerability_status' => 'A',
            'date_registered' => now()->toDateString(),
            'zone_id' => $zoneId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $widowId = (string) \Illuminate\Support\Str::uuid();
        DB::table('widows')->insert([
            'id' => $widowId,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'nin' => '12345678902'.rand(10, 99),
            'reg_no' => 'REG-'.rand(1000, 9999).rand(1, 9),
            'is_eligible' => true,
            'is_married' => false,
            'deceased_id' => $deceasedId,
            'child_sequence' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (strpos(get_class($this), 'BiometricEnrollmentTest') !== false) {
            $orphanId = (string) \Illuminate\Support\Str::uuid();
            DB::table('orphans')->insert([
                'id' => $orphanId,
                'first_name' => 'Jimmy',
                'last_name' => 'Doe',
                'gender' => 'MALE',
                'reg_no' => 'REG-'.rand(1000, 9999).rand(1, 9),
                'is_eligible' => true,
                'deceased_id' => $deceasedId,
                'child_sequence' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

    }

    public function test_mock_client_cannot_silently_become_production_fallback()
    {
        // Force the app to act like production without an explicit mock value.
        // The container must FAIL CLOSED (throw) rather than silently resolving
        // to the mock client or an unconfigured fallback.
        config(['biometrics.client' => null]);

        $this->expectException(\RuntimeException::class);
        app(FingerprintDeviceClientInterface::class);
    }

    public function test_service_container_resolves_mock_client_when_configured()
    {
        config(['biometrics.client' => 'mock']);

        $client = app(FingerprintDeviceClientInterface::class);

        $this->assertInstanceOf(\App\Services\Biometrics\MockFingerprintDeviceClient::class, $client);
    }

    public function test_service_container_resolves_http_client_when_configured()
    {
        config(['biometrics.client' => 'http']);

        $client = app(FingerprintDeviceClientInterface::class);

        $this->assertInstanceOf(\App\Services\Biometrics\HttpBiometricBridgeClient::class, $client);
    }

    public function test_service_container_fails_closed_on_invalid_or_missing_configuration()
    {
        foreach ([null, '', 'invalid_driver', 'production', 'something_else'] as $invalidConfig) {
            config(['biometrics.client' => $invalidConfig]);

            try {
                app(FingerprintDeviceClientInterface::class);
                $this->fail("Expected RuntimeException for invalid config '{$invalidConfig}'.");
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('BIOMETRICS_CLIENT', $e->getMessage());
            }
        }
    }

    public function test_mock_enrollment_is_marked_as_mock_source_and_synthetic_template()
    {
        config(['biometrics.client' => 'mock']);

        $client = app(FingerprintDeviceClientInterface::class);
        $result = $client->enroll();

        $this->assertEquals('ok', $result['status']);
        $this->assertEquals('mock', $result['source']);
        $this->assertStringContainsString('MOCK_TEMPLATE_DO_NOT_USE_IN_PRODUCTION_', $result['template']);
    }

    public function test_scanner_unavailable_is_handled_gracefully()
    {
        // Use the HTTP client with a valid config but an unreachable bridge.
        config(['biometrics.client' => 'http']);
        config(['biometrics.bridge.base_url' => 'http://127.0.0.1:1']);
        \Illuminate\Support\Facades\Http::fake([
            '*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('Connection refused'),
        ]);

        $client = app(FingerprintDeviceClientInterface::class);

        $this->expectException(\App\Exceptions\Biometrics\BridgeUnavailableException::class);
        $client->enroll();
    }

    public function test_low_quality_capture_is_handled_gracefully()
    {
        config(['biometrics.client' => 'mock']);
        config(['biometrics.mock.force_low_quality' => true]);

        $client = app(FingerprintDeviceClientInterface::class);
        $result = $client->capture();

        $this->assertEquals('low_quality', $result['status']);
        $this->assertTrue($result['quality'] < 50);
    }
}
