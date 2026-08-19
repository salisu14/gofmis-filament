<?php

return [
    'mfa' => [
        'mandatory_roles' => [
            'super_admin',
            'admin',
            'custodian',
            'auditor',
        ],
        'rate_limits' => [
            'challenge' => [
                'max_attempts' => 5,
                'decay_seconds' => 60,
            ],
            'recovery' => [
                'max_attempts' => 5,
                'decay_seconds' => 60,
            ],
            'enrollment' => [
                'max_attempts' => 5,
                'decay_seconds' => 60,
            ],
        ],
    ],
];
