<?php

return [
    'client' => env('BIOMETRICS_CLIENT', 'http'),

    /*
    |--------------------------------------------------------------------------
    | Biometric Governance
    |--------------------------------------------------------------------------
    |
    | lawful_basis_reference points to the policy/legal-basis identifier that
    | authorises biometric processing. It is deliberately a neutral reference,
    | NOT a hard-coded "consent" flag: the actual lawful basis for a deployment
    | must be approved by the Foundation's legal/privacy governance process.
    */
    'governance' => [
        'lawful_basis_reference' => env('BIOMETRICS_LAWFUL_BASIS_REFERENCE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Mock client (development / test only)
    |--------------------------------------------------------------------------
    |
    | When BIOMETRICS_CLIENT=mock, the application uses MockFingerprintDeviceClient.
    | force_low_quality may be flipped in tests to exercise the low-quality path.
    */
    'mock' => [
        'force_low_quality' => (bool) env('BIOMETRICS_MOCK_FORCE_LOW_QUALITY', false),

        // Deterministic forced outcomes for mock verify/identify in tests:
        // null (default -> match), 'no_match', 'scanner_unavailable',
        // 'timeout', 'low_quality', 'malformed', 'ambiguous'.
        'verify_outcome' => env('BIOMETRICS_MOCK_VERIFY_OUTCOME'),
        'identify_outcome' => env('BIOMETRICS_MOCK_IDENTIFY_OUTCOME'),
    ],

    'bridge' => [
        // Trusted scanner-bridge base URL. Must come only from server config.
        // Never accept this from request input / Livewire / query parameters.
        'base_url' => env('BIOMETRICS_BRIDGE_URL', 'http://127.0.0.1:8787'),

        // Optional bearer token for the local biometric bridge. This must be
        // isolated from APP_KEY and BIOMETRICS_ENCRYPTION_KEY.
        'token' => env('BIOMETRICS_BRIDGE_TOKEN'),

        // Overall request/response timeout (seconds) for capture/enroll, which
        // involves human interaction with the scanner.
        'timeout' => (int) env('BIOMETRICS_BRIDGE_TIMEOUT', 30),

        // Connection timeout (seconds) for reaching the bridge process.
        'connect_timeout' => (int) env('BIOMETRICS_BRIDGE_CONNECT_TIMEOUT', 3),

        // Maximum accepted fingerprint template payload size (bytes). Oversized
        // payloads are rejected to protect the app/database from a compromised
        // or misconfigured bridge.
        'max_template_bytes' => (int) env('BIOMETRICS_MAX_TEMPLATE_BYTES', 65536),
    ],

    /*
    |--------------------------------------------------------------------------
    | Identification (1:N)
    |--------------------------------------------------------------------------
    |
    | Candidate arrays sent to the bridge are bounded. If a pool exceeds the
    | limit the operation fails safely rather than silently dropping candidates.
    */
    'identification' => [
        'max_candidates' => (int) env('BIOMETRICS_IDENTIFICATION_MAX_CANDIDATES', 100),
        'max_total_bytes' => (int) env('BIOMETRICS_IDENTIFICATION_MAX_TOTAL_BYTES', 1048576),
    ],

    /*
    |--------------------------------------------------------------------------
    | Biometric Template Encryption
    |--------------------------------------------------------------------------
    |
    | Fingerprint templates are highly sensitive identity data and must not
    | depend on the application's general-purpose APP_KEY for their encryption
    | boundary. New templates are encrypted with a dedicated biometric key,
    | isolated from APP_KEY so that rotating APP_KEY does not silently redefine
    | the biometric cipher boundary.
    |
    | BIOMETRICS_ENCRYPTION_KEY: a base64-encoded 32-byte key, generated the
    | same way as an APP_KEY (e.g. `bin\base32` / `php artisan key:generate`).
    | It must be 32 bytes after base64 decoding (aes-256-cbc). It is validated
    | at runtime by BiometricTemplateCipher; encrypting with a missing or
    | invalid key fails closed rather than silently falling back to APP_KEY.
    |
    | Envelope format version written into the stored value. v1 = the current
    | dedicated-key envelope ("biometric:v1:" prefix). Bump this only when the
    | on-disk representation changes.
    */
    'encryption' => [
        'key' => env('BIOMETRICS_ENCRYPTION_KEY'),
        'cipher' => 'aes-256-cbc',
        'key_version' => (int) env('BIOMETRICS_ENCRYPTION_KEY_VERSION', 1),
        'format_version' => 1,
    ],
];
