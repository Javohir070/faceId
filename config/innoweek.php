<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Innoweek sayti — ishtirokchilar (mehmonlar) manbai
    |--------------------------------------------------------------------------
    |
    | `innoweek:sync` Innoweek API sidan (`GET /api/biocontrol/participants`) yangi va o‘zgargan
    | ishtirokchilarni tortadi — joyida ro‘yxatdan o‘tganlar ham Jonli kuzatuvga tushadi.
    | API kontrakti va Innoweek tomonidagi kod: docs/innoweek-api.md
    |
    */

    'api_url' => rtrim((string) env('INNOWEEK_API_URL', ''), '/'),

    'api_token' => env('INNOWEEK_API_TOKEN', ''),

    // true: scheduler har daqiqa `innoweek:sync --for=55` ni ishga tushiradi.
    'sync_enabled' => env('INNOWEEK_SYNC_ENABLED', false),

    'sync_interval_seconds' => max((int) env('INNOWEEK_SYNC_INTERVAL', 20), 5),

    'page_size' => max(min((int) env('INNOWEEK_PAGE_SIZE', 500), 1000), 1),

    'timeout' => max((int) env('INNOWEEK_API_TIMEOUT', 30), 1),

    // Mehmonlar bo‘limi (OAV (Media), Homiy, Ishtirokchi guruhlari shu bo‘limda); bo‘sh — "Innoweek <yil>".
    'department' => env('INNOWEEK_DEPARTMENT'),

];
