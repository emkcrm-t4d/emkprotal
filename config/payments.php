<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Payment Driver
    |--------------------------------------------------------------------------
    | Supported: 'bkash_manual', 'sslcommerz', 'bkash_gateway', 'mock'
    */
    'default_driver' => env('PAYMENT_DEFAULT_DRIVER', 'bkash_manual'),

    'drivers' => [
        'bkash_manual' => [
            'merchant_wallet_number' => env('BKASH_MERCHANT_WALLET', '01700000000'),
            'reference_prefix'       => 'EMK',
            'regex'                  => '^[A-Z0-9]{10}$',
        ],
    ],
];
