<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // manual | stripe | chip
    'payment_gateway' => env('PAYMENT_GATEWAY', 'manual'),

    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'currency' => env('STRIPE_CURRENCY', 'myr'),
    ],

    'chip' => [
        'url' => env('CHIP_URL', 'https://gate.chip-in.asia/api/v1'),
        'brand_id' => env('CHIP_BRAND_ID'),
        'secret' => env('CHIP_SECRET_KEY'),
        // Company public key (GET /public_key/), used to verify the X-Signature on success callbacks.
        'public_key' => env('CHIP_PUBLIC_KEY'),
        'currency' => env('CHIP_CURRENCY', 'MYR'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
