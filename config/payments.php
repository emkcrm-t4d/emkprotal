<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Payment Driver
    |--------------------------------------------------------------------------
    | Supported: 'bkash_manual', 'sslcommerz', 'bkash_gateway', 'mock'
    */
    'default_driver' => env('PAYMENT_DEFAULT_DRIVER', 'bkash_manual'),

    /*
    |--------------------------------------------------------------------------
    | Production Mock Driver Safety Guard
    |--------------------------------------------------------------------------
    */
    'allow_mock_in_production' => env('PAYMENTS_ALLOW_MOCK_IN_PRODUCTION', false),

    'drivers' => [
        'bkash_manual' => [
            'merchant_wallet_number' => env('BKASH_MERCHANT_WALLET', '01700000000'),
            'reference_prefix'       => 'EMK',
            'regex'                  => '^[A-Z0-9]{10}$',
        ],

        'sslcommerz' => [
            'store_id'     => env('SSLCOMMERZ_STORE_ID'),
            'store_passwd' => env('SSLCOMMERZ_STORE_PASSWORD'),
            'sandbox'      => env('SSLCOMMERZ_SANDBOX', true),
            'api_domain'   => env('SSLCOMMERZ_API_DOMAIN', 'https://sandbox.sslcommerz.com'),
        ],

        'bkash_gateway' => [
            'app_key'      => env('BKASH_APP_KEY'),
            'app_secret'   => env('BKASH_APP_SECRET'),
            'username'     => env('BKASH_USERNAME'),
            'password'     => env('BKASH_PASSWORD'),
            'sandbox'      => env('BKASH_SANDBOX', true),
            'api_domain'   => env('BKASH_API_DOMAIN', 'https://tokenized.sandbox.bka.sh/v1.2.0-beta'),
        ],

        'mock' => [
            'endpoint'     => env('MOCK_PAYMENT_ENDPOINT', 'https://payment.mock/checkout'),
            'secret'       => env('MOCK_PAYMENT_SECRET', 'mock-secret-key'),
            'auto_approve' => env('MOCK_PAYMENT_AUTO_APPROVE', true),
        ],
    ],
];
