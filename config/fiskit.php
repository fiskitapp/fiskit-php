<?php

return [
    // sk_test_... (ARCA homologation, no fiscal value) or sk_live_... (real invoices)
    'api_key' => env('FISKIT_API_KEY'),

    // Secret of your webhook endpoint (whsec_...), from the dashboard under API Keys → Webhooks
    'webhook_secret' => env('FISKIT_WEBHOOK_SECRET'),

    'base_url' => env('FISKIT_BASE_URL', 'https://api.fiskit.com.ar'),
    'max_retries' => 2,
    'timeout' => 60.0,
];
