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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google_vision' => [
        'key' => env('GOOGLE_VISION_API_KEY'),
    ],

    'google' => [
        'drive' => [
            'credentials_path' => env('GOOGLE_DRIVE_CREDENTIALS'),
        ],
    ],

    /*
     * PrintNode — server-driven label printing.
     *
     * The API key is NOT here: it is per-company and lives encrypted on
     * label_settings, because different tenants use different accounts.
     * Only the endpoint and timeout are global.
     *
     * The timeout is deliberately short. A chef is stood at the printer
     * waiting, and a request that hangs for 30 seconds is worse than one
     * that fails fast and lets them fall back to browser printing.
     */
    'printnode' => [
        'base_url' => env('PRINTNODE_BASE_URL', 'https://api.printnode.com'),
        'timeout'  => (int) env('PRINTNODE_TIMEOUT', 10),
    ],

];
