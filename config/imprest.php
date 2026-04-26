<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Spending Limit
    |--------------------------------------------------------------------------
    |
    | Maximum amount allowed per imprest transaction. Amounts exceeding this
    | limit must go through the accounts payable process instead.
    |
    */
    'spending_limit' => env('IMPREST_SPENDING_LIMIT', 100.00),

    /*
    |--------------------------------------------------------------------------
    | Low Balance Threshold
    |--------------------------------------------------------------------------
    |
    | Percentage of authorized amount at which low balance alerts are triggered.
    |
    */
    'low_balance_threshold' => env('IMPREST_LOW_BALANCE_THRESHOLD', 20),

    /*
    |--------------------------------------------------------------------------
    | Receipt Grace Period
    |--------------------------------------------------------------------------
    |
    | Hours after transaction creation before a missing receipt triggers an alert.
    |
    */
    'receipt_grace_period_hours' => env('IMPREST_RECEIPT_GRACE_PERIOD', 48),

    /*
    |--------------------------------------------------------------------------
    | Reconciliation Schedule
    |--------------------------------------------------------------------------
    |
    | How frequently funds must be reconciled (in days).
    |
    */
    'reconciliation_frequency_days' => env('IMPREST_RECONCILIATION_FREQUENCY', 30),

    /*
    |--------------------------------------------------------------------------
    | Variance Thresholds
    |--------------------------------------------------------------------------
    |
    | Percentage thresholds for variance severity classification.
    |
    */
    'variance_thresholds' => [
        'negligible' => 0.5,
        'minor' => 2.0,
        'moderate' => 5.0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Voucher Configuration
    |--------------------------------------------------------------------------
    |
    | Prefix and padding for auto-generated voucher numbers.
    |
    */
    'voucher' => [
        'prefix' => env('IMPREST_VOUCHER_PREFIX', 'VCH'),
        'padding' => 4,
        'date_format' => 'Ymd',
    ],
];
