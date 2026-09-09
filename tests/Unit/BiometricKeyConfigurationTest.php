<?php

use App\Console\Commands\SystemHealthCheckCommand;
use App\Services\Biometrics\BiometricTemplateCipher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Support\Facades\Crypt;

// Neutral fixture already used in phpunit.xml; never read or alter deployed secrets.
const BIOMETRIC_TEST_KEY = 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=';

function biometricKeyHealthResult(): array
{
    return (new class extends SystemHealthCheckCommand
    {
        public function result(): array
        {
            return $this->checkBiometricKey();
        }
    })->result();
}

beforeEach(function () {
    $this->app->forgetInstance(BiometricTemplateCipher::class);
});

test('prefixed and historical bare keys preserve envelopes and legacy decryption', function (string $prefix) {
    config(['biometrics.encryption.key' => $prefix.BIOMETRIC_TEST_KEY]);
    $cipher = app(BiometricTemplateCipher::class);
    expect($cipher->isKeyAvailable())->toBeTrue();
    $encrypted = $cipher->encrypt('neutral-template', 1);
    expect($encrypted)->toStartWith('biometric:v1:');
    expect($cipher->isCurrentEnvelope($encrypted))->toBeTrue();
    expect($cipher->decrypt($encrypted))->toBe('neutral-template');
    expect($cipher->reencryptLegacy($encrypted))->toBeNull();
    $legacy = Crypt::encryptString('legacy-neutral-template');
    expect($cipher->decrypt($legacy))->toBe('legacy-neutral-template');
    expect($cipher->decrypt($cipher->reencryptLegacy($legacy)))->toBe('legacy-neutral-template');
    expect(biometricKeyHealthResult())->toMatchArray([
        'status' => 'PASS',
        'message' => 'Biometric encryption key is present and valid (32-byte AES-256).',
    ]);
})->with(['Laravel prefix' => ['base64:'], 'historical bare' => ['']]);

test('invalid configured keys fail closed and health reports safe severity', function ($key, string $status) {
    config(['biometrics.encryption.key' => $key]);
    $cipher = app(BiometricTemplateCipher::class);
    expect($cipher->isKeyAvailable())->toBeFalse();
    expect(fn () => $cipher->encrypt('neutral-template'))->toThrow(RuntimeException::class);
    expect(fn () => $cipher->decrypt('biometric:v1:invalid'))->toThrow(RuntimeException::class);
    expect(biometricKeyHealthResult()['status'])->toBe($status);
    if (is_string($key) && strlen($key) > 7) {
        expect(biometricKeyHealthResult()['message'])->not->toContain($key);
    }
    // Missing dedicated key never prevents reading an existing legacy envelope.
    expect($cipher->decrypt(Crypt::encryptString('legacy-neutral-template')))->toBe('legacy-neutral-template');
})->with([
    'missing' => [null, 'WARN'],
    'empty' => ['', 'WARN'],
    'blank' => ['   ', 'WARN'],
    'invalid Base64' => ['base64:***invalid***', 'ERROR'],
    'short decoded key' => ['base64:AA==', 'ERROR'],
    'long decoded key' => ['base64:'.str_repeat('A', 64), 'ERROR'],
    'empty payload' => ['base64:', 'ERROR'],
    'incorrect prefix case' => ['BASE64:'.BIOMETRIC_TEST_KEY, 'ERROR'],
    'duplicate prefix' => ['base64:base64:'.BIOMETRIC_TEST_KEY, 'ERROR'],
    'leading whitespace' => [' base64:'.BIOMETRIC_TEST_KEY, 'ERROR'],
    'payload whitespace' => ['base64: '.BIOMETRIC_TEST_KEY, 'ERROR'],
    'missing padding' => [substr(BIOMETRIC_TEST_KEY, 0, -1), 'ERROR'],
    'noncanonical padding bits' => [str_repeat('A', 42).'B=', 'ERROR'],
]);

test('configured cipher is honored and unsupported cipher fails closed', function () {
    config(['biometrics.encryption.key' => 'base64:'.BIOMETRIC_TEST_KEY, 'biometrics.encryption.cipher' => 'aes-256-gcm']);
    $cipher = new BiometricTemplateCipher;
    expect($cipher->decrypt($cipher->encrypt('neutral-template')))->toBe('neutral-template');
    config(['biometrics.encryption.cipher' => 'unsupported']);
    expect((new BiometricTemplateCipher)->isKeyAvailable())->toBeFalse();
    expect(biometricKeyHealthResult()['status'])->toBe('ERROR');
});

test('health validation uses cached configuration without runtime environment lookup', function ($key, string $status) {
    $path = tempnam(storage_path('framework'), 'biometric-config-test-');
    $originalConfig = $this->app->make('config');
    $timezone = date_default_timezone_get();
    try {
        // This cache contains ONLY neutral test configuration, never APP_KEY or a real secret.
        file_put_contents($path, '<?php return '.var_export([
            'app' => ['env' => 'testing', 'timezone' => 'UTC'],
            'biometrics' => ['encryption' => ['key' => $key, 'cipher' => 'aes-256-cbc']],
        ], true).';');
        $cachedApp = Mockery::mock(Application::class);
        $cachedApp->shouldReceive('getCachedConfigPath')->once()->andReturn($path);
        $cachedApp->shouldReceive('instance')->once()->with('config_loaded_from_cache', true);
        $cachedApp->shouldReceive('instance')->once()->with('config', Mockery::on(function ($config) {
            $this->app->instance('config', $config);

            return true;
        }));
        $cachedApp->shouldReceive('detectEnvironment')->once();
        $cachedApp->shouldReceive('resolveEnvironmentUsing')->once();
        (new LoadConfiguration)->bootstrap($cachedApp);
        expect(biometricKeyHealthResult()['status'])->toBe($status);
        expect(app(BiometricTemplateCipher::class)->isKeyAvailable())->toBe($status === 'PASS');
    } finally {
        $this->app->instance('config', $originalConfig);
        date_default_timezone_set($timezone);
        unlink($path);
    }
})->with([
    'cached prefixed key' => ['base64:'.BIOMETRIC_TEST_KEY, 'PASS'],
    'cached missing key despite valid PHPUnit env' => [null, 'WARN'],
    'cached malformed key despite valid PHPUnit env' => ['base64:invalid!', 'ERROR'],
]);

test('availability and biometric health checks leave runtime encryption state untouched', function () {
    config(['biometrics.encryption.key' => 'base64:'.BIOMETRIC_TEST_KEY]);
    $cipher = new class extends BiometricTemplateCipher
    {
        public int $runtimeRequests = 0;

        public function hasRuntimeEncrypter(): bool
        {
            return $this->encrypter !== null;
        }

        protected function encrypter(): ?\Illuminate\Encryption\Encrypter
        {
            $this->runtimeRequests++;

            return parent::encrypter();
        }
    };
    $this->app->instance(BiometricTemplateCipher::class, $cipher);

    for ($attempt = 0; $attempt < 3; $attempt++) {
        expect($cipher->isKeyAvailable())->toBeTrue();
        expect(biometricKeyHealthResult()['status'])->toBe('PASS');
        expect($cipher->hasRuntimeEncrypter())->toBeFalse();
        expect($cipher->runtimeRequests)->toBe(0);
    }

    $encrypted = $cipher->encrypt('neutral-template');
    expect($cipher->hasRuntimeEncrypter())->toBeTrue();
    expect($cipher->runtimeRequests)->toBe(1);
    expect($encrypted)->toStartWith('biometric:v1:');
    expect($cipher->decrypt($encrypted))->toBe('neutral-template');
    expect($cipher->runtimeRequests)->toBe(2);

    // Validation still avoids the runtime path after encryption initializes it.
    expect($cipher->isKeyAvailable())->toBeTrue();
    expect(biometricKeyHealthResult()['status'])->toBe('PASS');
    expect($cipher->runtimeRequests)->toBe(2);
});
