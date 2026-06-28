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

        /*
        |----------------------------------------------------------------------
        | NIN Verification Providers (modular, multi-provider)
        |----------------------------------------------------------------------
        | Each provider is self-contained: base_url, api_key, the methods it
        | supports, and a per-method price. The frontend reads `label`,
        | `active` and `methods` to build the dynamic UI. To add a provider:
        |   1. Add a block here.
        |   2. Create a matching service class in App\Services\Nin\Providers.
        |   3. Register it in App\Services\Nin\NinProviderManager.
        |   4. Add a thin controller + route.
        | Providers 1 & 2 are live (Prembly / ArewaSmart proxy). 3-5 are
        | placeholders — fill in real values and set `active => true`.
        */
        'providers' => [
            'prembly' => [
                'label'    => 'Prembly',
                'active'   => true,
                'base_url' => env('NIN_BASE_URL', 'https://api.prembly.com'),
                'api_key'  => env('NIN_API_KEY'),
                'methods'  => ['nin', 'phone', 'demographic'],
                'prices'   => [
                    'nin'         => env('PREMBLY_NIN_PRICE', 50),
                    'phone'       => env('PREMBLY_PHONE_PRICE', 150),
                    'demographic' => env('PREMBLY_DEMO_PRICE', 150),
                ],
            ],

            'arewasmart' => [
                'label'    => 'ArewaSmart',
                'active'   => true,
                'base_url' => env('AREWASMART_BASE_URL', env('NIN_BASE_URL', 'https://api.prembly.com')),
                'api_key'  => env('AREWASMART_API_KEY', env('NIN_API_KEY')),
                'methods'  => ['nin', 'phone', 'demographic'],
                'prices'   => [
                    'nin'         => env('AREWASMART_NIN_PRICE', 50),
                    'phone'       => env('AREWASMART_PHONE_PRICE', 150),
                    'demographic' => env('AREWASMART_DEMO_PRICE', 150),
                ],
            ],

            'provider3' => [
                'label'    => 'Provider 3 (Configure)',
                'active'   => env('PROVIDER3_ACTIVE', false),
                'base_url' => env('PROVIDER3_BASE_URL'),
                'api_key'  => env('PROVIDER3_API_KEY'),
                'methods'  => ['nin', 'phone', 'demographic'],
                'prices'   => [
                    'nin'         => env('PROVIDER3_NIN_PRICE', 50),
                    'phone'       => env('PROVIDER3_PHONE_PRICE', 150),
                    'demographic' => env('PROVIDER3_DEMO_PRICE', 150),
                ],
            ],

            'provider4' => [
                'label'    => 'Provider 4 (Configure)',
                'active'   => env('PROVIDER4_ACTIVE', false),
                'base_url' => env('PROVIDER4_BASE_URL'),
                'api_key'  => env('PROVIDER4_API_KEY'),
                'methods'  => ['nin', 'phone', 'demographic'],
                'prices'   => [
                    'nin'         => env('PROVIDER4_NIN_PRICE', 50),
                    'phone'       => env('PROVIDER4_PHONE_PRICE', 150),
                    'demographic' => env('PROVIDER4_DEMO_PRICE', 150),
                ],
            ],

            'provider5' => [
                'label'    => 'Provider 5 (Configure)',
                'active'   => env('PROVIDER5_ACTIVE', false),
                'base_url' => env('PROVIDER5_BASE_URL'),
                'api_key'  => env('PROVIDER5_API_KEY'),
                'methods'  => ['nin', 'phone', 'demographic'],
                'prices'   => [
                    'nin'         => env('PROVIDER5_NIN_PRICE', 50),
                    'phone'       => env('PROVIDER5_PHONE_PRICE', 150),
                    'demographic' => env('PROVIDER5_DEMO_PRICE', 150),
                ],
            ],
        ],
    ],

    // BVN Verification Configuration
    'bvn' => [
        'provider' => env('BVN_PROVIDER', 'nibss'),
        'base_url' => env('BVN_BASE_URL'),
        'api_key' => env('BVN_API_KEY'),
    ],

    // External "ArewaSmart" verification provider (api.arewasmart.com.ng).
    // Bearer-token auth. Endpoints: POST {base}/bvn/verify {bvn}, etc.
    // Success response: { status, message, data: {...}, transaction_ref }.
    'arewasmart' => [
        'base_url' => env('AREWASMART_VERIFY_BASE_URL', 'https://api.arewasmart.com.ng/api/v1'),
        'token' => env('AREWASMART_VERIFY_TOKEN'),
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

    // Data vending vendor #1 (bozavtu). Seeded into the `vendorapi` table by
    // VendorApiSeeder; the key stays out of source control.
    'data_vendors' => [
        'vendor1_url' => env('DATA_VENDOR1_URL', 'https://vtu.bozavtu.com/api/data'),
        'vendor1_key' => env('DATA_VENDOR1_KEY'),
    ],

    // Quicklysim — data vendor #5. Access token is fetched per-request via
    // HTTP Basic auth (see DataPurchaseController::getQuicklysimToken).
    'quicklysim' => [
        'base_url' => env('QUICKLYSIM_BASE_URL', 'https://quicklysim.com/api'),
        'username' => env('QUICKLYSIM_USERNAME'),
        'password' => env('QUICKLYSIM_PASSWORD'),
    ],

    // Billstack — reserved virtual-account funding (PALMPAY) with BVN KYC.
    // Port of nimcweb's reserveAccount flow. The webhook (x-wiaxy-signature)
    // verifies as md5(token).
    'billstack' => [
        'token' => env('BILLSTACK_API_TOKEN'),
        'base_url' => env('BILLSTACK_BASE_URL', 'https://api.billstack.co/v2'),
    ],

];
