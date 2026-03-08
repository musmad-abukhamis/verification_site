<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // VTU Service Configuration
    'vtu' => [
        'provider' => env('VTU_PROVIDER', 'vtpass'),
        'base_url' => env('VTU_BASE_URL', 'https://sandbox.vtpass.com/api'),
        'api_key' => env('VTU_API_KEY'),
        'secret_key' => env('VTU_SECRET_KEY'),
    ],

    // NIN Verification Configuration
    'nin' => [
        'provider' => env('NIN_PROVIDER', 'prembly'),
        'base_url' => env('NIN_BASE_URL', 'https://api.prembly.com'),
        'api_key' => env('NIN_API_KEY'),
        'prices' => [
            'premium' => env('NIN_PREMIUM_PRICE', 150),
            'standard' => env('NIN_STANDARD_PRICE', 100),
            'regular' => env('NIN_REGULAR_PRICE', 50),
            'ipe' => env('NIN_IPE_PRICE', 50),
        ],
    ],

    // BVN Verification Configuration
    'bvn' => [
        'provider' => env('BVN_PROVIDER', 'nibss'),
        'base_url' => env('BVN_BASE_URL'),
        'api_key' => env('BVN_API_KEY'),
    ],

    // Verification Pricing
    'verification' => [
        'nin_price' => env('NIN_VERIFICATION_PRICE', 100),
        'bvn_price' => env('BVN_VERIFICATION_PRICE', 150),
        'nin_methods' => [
            'nin' => ['active' => true, 'label' => 'By NIN Number'],
            'phone' => ['active' => true, 'label' => 'By Phone Number'],
            'demographic' => ['active' => true, 'label' => 'By Demographics'],
        ],
    ],

    // Paystack Configuration
    'paystack' => [
        'public_key' => env('PAYSTACK_PUBLIC_KEY'),
        'secret_key' => env('PAYSTACK_SECRET_KEY'),
        'base_url' => env('PAYSTACK_BASE_URL', 'https://api.paystack.co'),
    ],

];
