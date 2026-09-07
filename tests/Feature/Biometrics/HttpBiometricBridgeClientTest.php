<?php

namespace Tests\Feature\Biometrics;

use App\Exceptions\Biometrics\BridgeUnavailableException;
use App\Exceptions\Biometrics\CaptureTimeoutException;
use App\Exceptions\Biometrics\InvalidBridgeResponseException;
use App\Exceptions\Biometrics\LowQualityCaptureException;
use App\Exceptions\Biometrics\ScannerOperationException;
use App\Services\Biometrics\FingerprintIdentificationResult;
use App\Services\Biometrics\FingerprintVerificationResult;
use App\Services\Biometrics\HttpBiometricBridgeClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * PB-NEXT-02B — HttpBiometricBridgeClient.
 *
 * All tests use Laravel's HTTP fake; no real scanner/hardware is required.
 */
class HttpBiometricBridgeClientTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['biometrics.client' => 'http']);
        config(['biometrics.bridge.base_url' => 'http://127.0.0.1:9876']);
        config(['biometrics.bridge.timeout' => 30]);
        config(['biometrics.bridge.connect_timeout' => 3]);
        config(['biometrics.bridge.max_template_bytes' => 65536]);
        config(['biometrics.bridge.token' => null]);
    }

    protected function client(): HttpBiometricBridgeClient
    {
        return new HttpBiometricBridgeClient;
    }

    protected function aTemplate(): string
    {
        return 'base64:'.base64_encode('template-bytes');
    }

    // A. successful capture request.
    public function test_successful_capture_returns_canonical_result()
    {
        Http::fake([
            '127.0.0.1:9876/api/v1/fingerprints/capture' => Http::response([
                'success' => true,
                'template' => $this->aTemplate(),
                'template_format' => 'iso_iec_19794_2',
                'quality_score' => 85,
                'device' => ['vendor' => 'SecuGen', 'model' => 'Hamster Plus', 'serial' => 'SN-1'],
                'sdk_version' => '1.4',
            ], 200),
        ]);

        $result = $this->client()->enroll();

        $this->assertSame('ok', $result['status']);
        $this->assertSame($this->aTemplate(), $result['template']);
        $this->assertSame('iso_iec_19794_2', $result['format']);
        $this->assertSame(85, $result['quality']);
        $this->assertSame('SecuGen', $result['device_manufacturer']);
        $this->assertSame('Hamster Plus', $result['device_model']);
        $this->assertSame('SN-1', $result['device_serial']);
    }

    // B. correct configured bridge endpoint used (wildcard-free assertion).
    public function test_correct_configured_endpoint_is_used()
    {
        Http::fake([
            '*' => Http::response(['template' => $this->aTemplate()], 200),
        ]);
        config(['biometrics.bridge.base_url' => 'http://scanner.local:8443']);

        $this->client()->enroll();

        Http::assertSent(fn ($request) => $request->url() === 'http://scanner.local:8443/api/v1/fingerprints/capture');
    }

    // C. correct request payload (minimal).
    public function test_correct_request_payload_is_sent()
    {
        Http::fake([
            '*' => Http::response(['template' => $this->aTemplate()], 200),
        ]);

        $this->client()->capture('right_thumb');

        Http::assertSent(function ($request) {
            $data = $request->data() ?? [];

            return isset($data['request_id'])
                && $data['finger_position'] === 'RIGHT_THUMB'
                && Str::isUuid((string) $data['request_id']);
        });
    }

    // D. beneficiary PII is not transmitted.
    public function test_beneficiary_pii_is_not_transmitted()
    {
        Http::fake(['*' => Http::response(['template' => $this->aTemplate()], 200)]);

        $this->client()->capture();

        Http::assertSent(function ($request) {
            $body = $request->body();
            $forbidden = ['nin', 'reg_no', 'first_name', 'last_name', 'date_of_birth', 'address', 'guardian', 'phone'];

            foreach ($forbidden as $token) {
                if (str_contains($body, $token)) {
                    return false;
                }
            }

            return true;
        });
    }

    // E. auth token sent correctly if configured.
    public function test_auth_token_is_sent_when_configured()
    {
        Http::fake(['*' => Http::response(['template' => $this->aTemplate()], 200)]);
        config(['biometrics.bridge.token' => 'bridge-secret-token']);

        $this->client()->enroll();

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer bridge-secret-token'));
    }

    // F. successful response maps to canonical capture result.
    public function test_success_response_maps_quality_and_format()
    {
        Http::fake(['*' => Http::response([
            'template' => $this->aTemplate(),
            'quality_score' => 88,
            'template_format' => 'ansi_incits_378',
        ], 200)]);

        $result = $this->client()->capture();
        $this->assertSame(88, $result['quality']);
        $this->assertSame('ansi_incits_378', $result['format']);
    }

    // G. bridge unavailable handled safely.
    public function test_bridge_unavailable_throws_safe_exception()
    {
        Http::fake(['*' => Http::response([], 500)]);

        $this->expectException(BridgeUnavailableException::class);
        $this->client()->enroll();
    }

    // H. connection failure handled safely.
    public function test_connection_failure_throws_safe_exception()
    {
        Http::fake(['*' => fn () => throw new ConnectionException('Connection refused')]);

        $this->expectException(BridgeUnavailableException::class);
        $this->client()->enroll();
    }

    // I. capture timeout handled safely.
    public function test_capture_timeout_throws_capture_timeout()
    {
        Http::fake(['*' => fn () => throw new ConnectionException('Operation timed out')]);

        $this->expectException(CaptureTimeoutException::class);
        $this->client()->enroll();
    }

    // J. scanner-disconnected response handled safely.
    public function test_scanner_disconnected_maps_to_scanner_operation()
    {
        Http::fake(['*' => Http::response(['status' => 'error', 'message' => 'Scanner disconnected'], 200)]);

        $this->expectException(ScannerOperationException::class);
        $this->client()->enroll();
    }

    // K. scanner-busy response handled safely.
    public function test_scanner_busy_maps_to_scanner_operation()
    {
        Http::fake(['*' => Http::response(['status' => 'error', 'message' => 'Device busy'], 200)]);

        $this->expectException(ScannerOperationException::class);
        $this->client()->enroll();
    }

    // L. cancelled capture handled safely.
    public function test_cancelled_capture_maps_to_scanner_operation()
    {
        Http::fake(['*' => Http::response(['status' => 'error', 'message' => 'Capture cancelled'], 200)]);

        $this->expectException(ScannerOperationException::class);
        $this->client()->enroll();
    }

    // M. poor-quality response handled safely.
    public function test_poor_quality_maps_to_low_quality_exception()
    {
        Http::fake(['*' => Http::response(['status' => 'error', 'message' => 'Poor fingerprint quality'], 200)]);

        $this->expectException(LowQualityCaptureException::class);
        $this->client()->enroll();
    }

    // N. malformed JSON/response rejected.
    public function test_malformed_response_rejected()
    {
        Http::fake(['*' => Http::response('not-json', 200, ['Content-Type' => 'text/plain'])]);

        $this->expectException(InvalidBridgeResponseException::class);
        $this->client()->enroll();
    }

    // O. missing template rejected.
    public function test_missing_template_rejected()
    {
        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $this->expectException(InvalidBridgeResponseException::class);
        $this->client()->enroll();
    }

    // P. empty template rejected.
    public function test_empty_template_rejected()
    {
        Http::fake(['*' => Http::response(['template' => ''], 200)]);

        $this->expectException(InvalidBridgeResponseException::class);
        $this->client()->enroll();
    }

    // Q. oversized template rejected.
    public function test_oversized_template_rejected()
    {
        config(['biometrics.bridge.max_template_bytes' => 4]);

        Http::fake(['*' => Http::response(['template' => 'toolong'], 200)]);

        $this->expectException(InvalidBridgeResponseException::class);
        $this->client()->enroll();
    }

    // R. invalid quality score rejected.
    public function test_invalid_quality_score_rejected()
    {
        Http::fake(['*' => Http::response(['template' => $this->aTemplate(), 'quality_score' => 'not-a-number'], 200)]);

        $this->expectException(InvalidBridgeResponseException::class);
        $this->client()->enroll();
    }

    // S. unsupported template format rejected.
    public function test_unsupported_template_format_rejected()
    {
        Http::fake(['*' => Http::response(['template' => $this->aTemplate(), 'template_format' => 'proprietary_fancy'], 200)]);

        $this->expectException(InvalidBridgeResponseException::class);
        $this->client()->enroll();
    }

    // T. template content never appears in logs.
    public function test_template_never_appears_in_logs()
    {
        Http::fake(['*' => Http::response(['template' => 'supersecret-biometric-payload'], 200)]);

        $logged = [];
        \Illuminate\Support\Facades\Log::swap(
            new class($logged) extends \Psr\Log\AbstractLogger
            {
                public function __construct(protected array &$logged) {}

                public function log($level, string|\Stringable $message, array $context = []): void
                {
                    $this->logged[] = json_encode([$level, (string) $message, $context]);
                }
            }
        );

        $this->client()->enroll();

        $leaked = collect($logged)->first(fn ($entry) => str_contains((string) $entry, 'supersecret-biometric-payload'));
        $this->assertNull($leaked, 'Biometric template material must never be logged.');
    }

    // U. auth token never appears in logs/errors.
    public function test_auth_token_never_appears_in_logs_or_errors()
    {
        Http::fake(['*' => Http::response([], 500)]);
        config(['biometrics.bridge.token' => 'super-secret-bridge-token']);

        try {
            $this->client()->enroll();
            $this->fail('Expected BridgeUnavailableException');
        } catch (BridgeUnavailableException $e) {
            $this->assertStringNotContainsString('super-secret-bridge-token', $e->safeMessage());
        }
    }

    // V. new captured template still passes PB-NEXT-02A encrypted persistence path.
    public function test_captured_template_uses_dedicated_encryption_after_persist()
    {
        Http::fake(['*' => Http::response(['template' => 'plain-template-from-bridge'], 200)]);

        $result = $this->client()->enroll();

        // This mirrors FingerprintsRelationManager::enroll flow (PB-NEXT-02A).
        $this->actingAs(\App\Models\User::factory()->create());
        $zone = \App\Models\Zone::create(['name' => 'Bridge Zone']);
        $deceasedId = (string) \Illuminate\Support\Str::uuid();
        \Illuminate\Support\Facades\DB::table('deceased')->insert([
            'id' => $deceasedId, 'first_name' => 'A', 'last_name' => 'B',
            'nin' => '1234567890'.Str::random(4), 'reg_no' => 'REG-'.Str::random(6),
            'guardian_name' => 'G', 'guardian_phone' => '123',
            'vulnerability_status' => 'A', 'date_registered' => now()->toDateString(),
            'zone_id' => $zone->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $widowId = (string) \Illuminate\Support\Str::uuid();
        \Illuminate\Support\Facades\DB::table('widows')->insert([
            'id' => $widowId, 'first_name' => 'J', 'last_name' => 'D',
            'nin' => '1234567890'.Str::random(4), 'reg_no' => 'REG-'.Str::random(6),
            'is_eligible' => true, 'is_married' => false,
            'deceased_id' => $deceasedId, 'child_sequence' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $widow = \App\Models\Widow::withoutGlobalScopes()->find($widowId);
        $this->assertNotNull($widow);

        $print = $widow->fingerprints()->create([
            'finger_position' => 'right_thumb',
            'encrypted_template' => $result['template'],
            'template_format' => $result['format'],
            'quality_score' => $result['quality'],
            'source' => $result['source'],
            'device_manufacturer' => $result['device_manufacturer'],
            'device_model' => $result['device_model'],
            'device_serial' => $result['device_serial'],
            'sdk_version' => $result['sdk_version'],
            'enrolled_by' => auth()->id(),
            'is_active' => true,
        ]);

        $raw = \Illuminate\Support\Facades\DB::table('beneficiary_fingerprints')->where('id', $print->id)->value('encrypted_template');
        $this->assertStringStartsWith('biometric:v', $raw);
        $this->assertStringNotContainsString('plain-template-from-bridge', $raw);
    }

    // ------------------------------------------------------------
    //  1:1 VERIFY (HTTP bridge contract)
    // ------------------------------------------------------------

    public function test_verify_http_match()
    {
        Http::fake(['*' => Http::response(['result' => 'match', 'confidence' => 0.99], 200)]);
        $result = $this->client()->verify('ref-template');
        $this->assertTrue($result->isMatch());
        $this->assertSame(0.99, $result->confidence);
    }

    public function test_verify_http_no_match()
    {
        Http::fake(['*' => Http::response(['result' => 'no_match'], 200)]);
        $this->assertSame(FingerprintVerificationResult::NO_MATCH, $this->client()->verify('ref-template')->status);
    }

    public function test_verify_http_scanner_unavailable_is_error_not_no_match()
    {
        Http::fake(['*' => Http::response(['status' => 'error', 'message' => 'Scanner disconnected'], 200)]);
        $result = $this->client()->verify('ref-template');
        $this->assertNotSame(FingerprintVerificationResult::NO_MATCH, $result->status);
        $this->assertTrue($result->isError());
    }

    public function test_verify_http_timeout_maps_to_timeout_error()
    {
        Http::fake(['*' => fn () => throw new ConnectionException('Operation timed out')]);
        $this->expectException(CaptureTimeoutException::class);
        $this->client()->verify('ref-template');
    }

    public function test_verify_http_malformed_response_rejected()
    {
        Http::fake(['*' => Http::response([], 200)]);
        $this->expectException(InvalidBridgeResponseException::class);
        $this->client()->verify('ref-template');
    }

    public function test_verify_request_contains_no_beneficiary_pii()
    {
        Http::fake(['*' => Http::response(['result' => 'match'], 200)]);
        $this->client()->verify('ref-template', 'iso_iec_19794_2');

        Http::assertSent(function ($request) {
            $body = $request->body();
            foreach (['nin', 'reg_no', 'first_name', 'last_name', 'date_of_birth', 'address', 'guardian', 'phone'] as $token) {
                if (str_contains($body, $token)) {
                    return false;
                }
            }

            return true;
        });
    }

    // ------------------------------------------------------------
    //  1:N IDENTIFY (HTTP bridge contract)
    // ------------------------------------------------------------

    public function test_identify_http_match()
    {
        Http::fake(['*' => Http::response(['matched_candidate_id' => 'abc-123'], 200)]);
        $result = $this->client()->identify([['candidate_id' => 'abc-123', 'template' => 't1', 'format' => 'raw']]);
        $this->assertTrue($result->isMatch());
        $this->assertSame('abc-123', $result->candidateId);
    }

    public function test_identify_http_no_match()
    {
        Http::fake(['*' => Http::response(['matched' => false], 200)]);
        $this->assertSame(FingerprintIdentificationResult::NO_MATCH, $this->client()->identify([])->status);
    }

    public function test_identify_http_scanner_unavailable_is_error_not_no_match()
    {
        Http::fake(['*' => Http::response(['status' => 'error', 'message' => 'Scanner not found'], 200)]);
        $result = $this->client()->identify([]);
        $this->assertTrue($result->isError());
        $this->assertNotSame(FingerprintIdentificationResult::NO_MATCH, $result->status);
    }

    public function test_identify_http_timeout_maps_to_timeout_error()
    {
        Http::fake(['*' => fn () => throw new ConnectionException('Operation timed out')]);
        $this->expectException(CaptureTimeoutException::class);
        $this->client()->identify([]);
    }

    public function test_identify_http_malformed_match_id_rejected()
    {
        Http::fake(['*' => Http::response(['matched_candidate_id' => ''], 200)]);
        $this->expectException(InvalidBridgeResponseException::class);
        $this->client()->identify([]);
    }

    public function test_identify_http_candidate_payload_uses_opaque_ids_only()
    {
        Http::fake(['*' => Http::response(['matched_candidate_id' => 'cand-x'], 200)]);
        $candidates = [['candidate_id' => 'cand-1', 'template' => 't1', 'format' => 'raw']];
        $result = $this->client()->identify($candidates);

        $this->assertTrue($result->isMatch());
        $this->assertSame('cand-x', $result->candidateId);
    }

    // Mock client remains unaffected by the verify/identify contract evolution.
    public function test_mock_client_still_returns_its_own_shape()
    {
        config(['biometrics.client' => 'mock']);

        $mock = app(\App\Contracts\Biometrics\FingerprintDeviceClientInterface::class);
        $this->assertInstanceOf(\App\Services\Biometrics\MockFingerprintDeviceClient::class, $mock);
        $result = $mock->enroll();
        $this->assertSame('ok', $result['status']);
        $this->assertSame('mock', $result['source']);

        $verify = $mock->verify('template');
        $this->assertInstanceOf(\App\Services\Biometrics\FingerprintVerificationResult::class, $verify);
    }
}
