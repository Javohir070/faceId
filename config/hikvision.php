<?php

return [

    /*
    |--------------------------------------------------------------------------
    | HTTP timeouts (barcha qurilmalar uchun)
    |--------------------------------------------------------------------------
    |
    | IP, port, login va parol — admin panel → Qurilmalar (`devices` jadvali).
    |
    */

    'connect_timeout' => (int) env('HIKVISION_CONNECT_TIMEOUT', 5),

    'timeout' => (int) env('HIKVISION_TIMEOUT', 10),

    /*
    |--------------------------------------------------------------------------
    | Webhook
    |--------------------------------------------------------------------------
    */

    'webhook_secret' => env('HIKVISION_WEBHOOK_SECRET', ''),

    'webhook_trust_device_ip' => env('HIKVISION_WEBHOOK_TRUST_DEVICE_IP', true),

    'webhook_path' => '/api/webhooks/hikvision/events',

    'webhook_url' => env('HIKVISION_WEBHOOK_URL'),

    'webhook_host' => env('HIKVISION_WEBHOOK_HOST'),

    'webhook_port' => env('HIKVISION_WEBHOOK_PORT'),

    'events_pull_enabled' => env('HIKVISION_EVENTS_PULL_ENABLED', true),

    'events_pull_interval_minutes' => max((int) env('HIKVISION_EVENTS_PULL_INTERVAL', 1), 1),

    /*
    |--------------------------------------------------------------------------
    | Real-time alertStream (GET /ISAPI/Event/notification/alertStream)
    |--------------------------------------------------------------------------
    |
    | Uzluksiz HTTP stream orqali eshik ochilishi va Face ID eventlarini oladi.
    | Ishga tushirish: php artisan devices:stream-events
    |
    | alertStream yoqilganda events_pull ni o‘chirish shart emas — u offline/zaxira
    | kanal sifatida qoladi. Real-time uchun webhook + alertStream ishlatiladi;
    | pull interval ni 10–15 daqiqaga qo‘ying (dublikat ReportService da filtrlanadi).
    |
    */

    'alert_stream_enabled' => env('HIKVISION_ALERT_STREAM_ENABLED', true),

    // Serverda true: scheduler har daqiqa `devices:stream-events --ensure` ni chaqirib,
    // manager o‘lgan bo‘lsa qayta ishga tushiradi. Lokalda false — terminalda qo‘lda ishlatiladi.
    'alert_stream_autostart' => env('HIKVISION_ALERT_STREAM_AUTOSTART', false),

    'alert_stream_connect_timeout' => (int) env('HIKVISION_ALERT_STREAM_CONNECT_TIMEOUT', 15),

    'alert_stream_timeout' => (int) env('HIKVISION_ALERT_STREAM_TIMEOUT', 0),

    'alert_stream_reconnect_seconds' => max((int) env('HIKVISION_ALERT_STREAM_RECONNECT', 30), 5),

    /*
    |--------------------------------------------------------------------------
    | Jonli tortish (devices:stream-events manageri ichida)
    |--------------------------------------------------------------------------
    |
    | alertStream birinchi ulanganda qurilma to‘planib qolgan eski eventlarni
    | yuboradi va bugungilar kechikadi. Manager har N soniyada onlayn qurilmalardan
    | faqat bugungi yangi eventlarni tortadi (dublikat ReportService da filtrlanadi).
    |
    */

    'live_pull_enabled' => env('HIKVISION_LIVE_PULL_ENABLED', true),

    'live_pull_interval_seconds' => max((int) env('HIKVISION_LIVE_PULL_INTERVAL', 2), 1),

    /*
    |--------------------------------------------------------------------------
    | ISAPI defaults
    |--------------------------------------------------------------------------
    */

    'face_lib_type' => 'blackFD',

    'face_lib_id' => '1',

    'door_no' => 1,

    'plan_template_no' => '1',

    'max_face_image_bytes' => 512 * 1024,

    'verify_ssl' => env('HIKVISION_VERIFY_SSL', false),

    /*
    |--------------------------------------------------------------------------
    | Qurilma ping (fon tekshiruv)
    |--------------------------------------------------------------------------
    */

    'ping_enabled' => env('HIKVISION_PING_ENABLED', true),

    'ping_interval_minutes' => max((int) env('HIKVISION_PING_INTERVAL', 2), 1),

    'ping_offline_after_failures' => max((int) env('HIKVISION_PING_OFFLINE_AFTER_FAILURES', 5), 1),

    /*
    |--------------------------------------------------------------------------
    | Qurilmadan xodimlarni tortish (UserInfo/Search)
    |--------------------------------------------------------------------------
    */

    'employees_pull_page_size' => max((int) env('HIKVISION_EMPLOYEES_PULL_PAGE_SIZE', 30), 1),

    'employees_pull_department' => env('HIKVISION_EMPLOYEES_PULL_DEPARTMENT', 'Qurilmadan'),

    'employees_pull_schedule' => env('HIKVISION_EMPLOYEES_PULL_SCHEDULE', 'Standart'),

];
