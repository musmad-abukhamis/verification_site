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

    // Brevo transactional email. Used by App\Mail\BrevoApiTransport for the
    // password-reset link; SMTP was never configured on this server, which made
    // /forgot-password a 500 until it was handled.
    'brevo' => [
        'key' => env('BREVO_API_KEY'),
        'endpoint' => env('BREVO_ENDPOINT', 'https://api.brevo.com/v3/smtp/email'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // NIN Verification Configuration
    'nin' => [
        'provider' => env('NIN_PROVIDER', 'prembly'),
        'base_url' => env('NIN_BASE_URL', 'https://api.prembly.com'),
        'api_key' => env('NIN_API_KEY'),

        /*
        |----------------------------------------------------------------------
        | NIN Verification Providers (modular, multi-provider)
        |----------------------------------------------------------------------
        | Each provider is self-contained: base_url, api_key and the methods it
        | supports. PRICES ARE NOT HERE -- every NIN service is priced in
        | Admin > Service Prices (the ninServicePrices table), the same fee for
        | every provider. The per-provider `prices` keys that used to live here
        | were removed because nothing read them once the admin page became the
        | source of truth. The frontend reads `label`, `active` and `methods` to
        | build the dynamic UI. To add a provider:
        |   1. Add a block here.
        |   2. Create a matching service class in App\Services\Nin\Providers.
        |   3. Register it in App\Services\Nin\NinProviderManager.
        |   4. Add a thin controller + route.
        | Providers 1 & 2 are live (Prembly / ArewaSmart proxy). 3-5 are
        | placeholders — fill in real values and set `active => true`.
        */
        'providers' => [
            'prembly' => [
                'label'    => 'V1',
                'active'   => true,
                'base_url' => env('NIN_BASE_URL', 'https://api.prembly.com'),
                'api_key'  => env('NIN_API_KEY'),
                'methods'  => ['nin', 'phone', 'demographic'],
            ],

            'arewasmart' => [
                'label'    => 'V2',
                'active'   => true,
                'base_url' => env('AREWASMART_BASE_URL', env('NIN_BASE_URL', 'https://api.prembly.com')),
                'api_key'  => env('AREWASMART_API_KEY', env('NIN_API_KEY')),
                'methods'  => ['nin', 'phone', 'demographic'],
            ],

            'provider3' => [
                'label'    => 'V3',
                'active'   => env('PROVIDER3_ACTIVE', false),
                'base_url' => env('PROVIDER3_BASE_URL'),
                'api_key'  => env('PROVIDER3_API_KEY'),
                'methods'  => ['nin', 'phone', 'demographic'],
            ],

            'provider4' => [
                'label'    => 'V4',
                'active'   => env('PROVIDER4_ACTIVE', false),
                'base_url' => env('PROVIDER4_BASE_URL'),
                'api_key'  => env('PROVIDER4_API_KEY'),
                'methods'  => ['nin', 'phone', 'demographic'],
            ],

            'provider5' => [
                'label'    => 'V5',
                'active'   => env('PROVIDER5_ACTIVE', false),
                'base_url' => env('PROVIDER5_BASE_URL'),
                'api_key'  => env('PROVIDER5_API_KEY'),
                'methods'  => ['nin', 'phone', 'demographic'],
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

    // Live data vendor (bozavtu). Seeded into the normalized `vendors` table by
    // VendorSeeder; the key stays out of source control. Additional vendors are
    // managed entirely from the admin Vendors screen (Phase 2).
    'data_vendors' => [
        'vendor1_url' => env('DATA_VENDOR1_URL', 'https://vtu.bozavtu.com/api/data'),
        'vendor1_key' => env('DATA_VENDOR1_KEY'),
    ],

    // PayVessel — STATIC virtual accounts for wallet funding (replaces
    // Billstack). Auth is api-key + api-secret headers; the webhook is signed
    // HMAC-SHA512 over the raw body using the secret.
    //
    // nimcweb shipped this webhook with BOTH the signature and IP checks
    // commented out, which let anyone credit any wallet. Both are enforced here.
    // The header arrives as "Payvessel-Http-Signature"; nimcweb read it as
    // HTTP_PAYVESSEL_HTTP_SIGNATURE (the PHP $_SERVER spelling), always got
    // null, and disabled the check rather than fixing the name.
    'payvessel' => [
        'key' => env('PAYVESSEL_API_KEY'),
        'secret' => env('PAYVESSEL_SECRET_KEY'),
        'business_id' => env('PAYVESSEL_BUSINESS_ID'),
        'base_url' => env('PAYVESSEL_BASE_URL', 'https://api.payvessel.com'),
        // PalmPay + 9PSB. Rubies (090175) is supported by PayVessel but has no
        // column in accountkyc, so it is not requested.
        'bank_codes' => ['999991', '120001'],
        // PayVessel's documented webhook source addresses. Empty disables the
        // check -- only for local testing, never in production.
        'webhook_ips' => array_filter(explode(',', (string) env('PAYVESSEL_WEBHOOK_IPS', '3.255.23.38,162.246.254.36'))),
    ],

    // Billstack — reserved virtual-account funding (PALMPAY) with BVN KYC.
    // Port of nimcweb's reserveAccount flow. The webhook (x-wiaxy-signature)
    // verifies as md5(token).
    'billstack' => [
        'token' => env('BILLSTACK_API_TOKEN'),
        'base_url' => env('BILLSTACK_BASE_URL', 'https://api.billstack.co/v2'),
    ],

    // Termii — SMS, used to deliver password-reset codes. Most accounts came
    // from nimcweb and cannot receive the emailed reset link (they registered
    // with addresses they no longer read), but all of them have a phone.
    //
    // nimcweb hardcoded this key in lib/pin/sendOtp.ts; it must be rotated and
    // kept in .env here.
    'termii' => [
        'key' => env('TERMII_API_KEY'),
        'sender' => env('TERMII_SENDER_ID', 'SOFT OTP'),
        'base_url' => env('TERMII_BASE_URL', 'https://v3.api.termii.com'),
        // "generic" is a STOPGAP. It works today because the dnd route is not
        // activated on this Termii account -- dnd answers 400 "Country
        // Inactive. Contact Administrator to activate country." (both /sms/send
        // and /sms/otp/send, measured 2026-07-19).
        //
        // Termii's own docs say generic is wrong for OTPs:
        //   - it does NOT deliver to numbers on DND, and many Nigerian mobiles
        //     are; those users get no code and we get no error
        //   - MTN blocks it 8PM-8AM WAT, so overnight resets silently fail
        //   - sustained OTP traffic on it can get the sender ID blocked
        //
        // Ask Termii support to activate the dnd route, then set
        // TERMII_CHANNEL=dnd. That is the correct channel for reset codes.
        'channel' => env('TERMII_CHANNEL', 'generic'),

        // "plain" -> /api/sms/send, we generate the code and verify it locally
        // "otp"   -> /api/sms/otp/send, Termii generates and verifies the code
        //
        // plain is the better design and the default: attempt limits and expiry
        // stay in our database, and a reset in progress does not depend on
        // Termii's verify endpoint being reachable. otp is kept only as a
        // fallback if the plain SMS product is ever unavailable.
        'mode' => env('TERMII_MODE', 'plain'),
    ],

];
