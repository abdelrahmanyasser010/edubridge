<?php

return [
    'provider' => env('PAYMENT_PROVIDER', 'moyasar'),
    'currency' => env('PAYMENT_CURRENCY', 'SAR'),

    'methods' => [
        'mada' => (bool) env('PAYMENT_METHOD_MADA', true),
        'apple_pay' => (bool) env('PAYMENT_METHOD_APPLE_PAY', true),
        'visa' => (bool) env('PAYMENT_METHOD_VISA', true),
        'mastercard' => (bool) env('PAYMENT_METHOD_MASTERCARD', true),
        'stc_pay' => (bool) env('PAYMENT_METHOD_STC_PAY', false),
        'samsung_pay' => (bool) env('PAYMENT_METHOD_SAMSUNG_PAY', false),
    ],

    'moyasar' => [
        'publishable_key' => env('MOYASAR_PUBLISHABLE_KEY'),
        'secret_key' => env('MOYASAR_SECRET_KEY'),
        'webhook_secret' => env('MOYASAR_WEBHOOK_SECRET'),
        'api_url' => env('MOYASAR_API_URL', 'https://api.moyasar.com/v1'),
        'callback_url' => env('MOYASAR_CALLBACK_URL', 'edubridge://payments/return'),
    ],

    'session_ttl_minutes' => (int) env('PAYMENT_SESSION_TTL_MINUTES', 30),

    'wallet' => [
        'top_up_min_minor' => (int) env('WALLET_TOP_UP_MIN_MINOR', 1000),
        'top_up_max_minor' => (int) env('WALLET_TOP_UP_MAX_MINOR', 100000),
        'qr_max_purchase_minor' => (int) env('WALLET_QR_MAX_PURCHASE_MINOR', 50000),
        'qr_ttl_seconds' => (int) env('WALLET_QR_TTL_SECONDS', 60),
    ],
];
