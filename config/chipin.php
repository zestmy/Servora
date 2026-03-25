<?php

return [
    'brand_id'       => env('CHIPIN_BRAND_ID'),
    'api_key'        => env('CHIPIN_API_KEY'),
    'webhook_secret' => env('CHIPIN_WEBHOOK_SECRET'),
    'base_url'       => env('CHIPIN_BASE_URL', 'https://gate.chip-in.asia/api/v1'),
    'sandbox'        => env('CHIPIN_SANDBOX', true),
];
