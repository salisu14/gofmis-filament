<?php

return [
    'client' => env('BIOMETRICS_CLIENT', 'http'),

    'bridge' => [
        'base_url' => env('BIOMETRICS_BRIDGE_URL', 'http://127.0.0.1:8787'),
        'token' => env('BIOMETRICS_BRIDGE_TOKEN'),
        'timeout' => (int) env('BIOMETRICS_BRIDGE_TIMEOUT', 5),
    ],
];
