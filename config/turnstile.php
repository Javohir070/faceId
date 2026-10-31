<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Turniket tizimi (Expo Panel) — faqat o‘qiladi
    |--------------------------------------------------------------------------
    |
    | Turniket har bir karta o‘qilishini `expo.gecishareketleri` jadvaliga yozadi. `turnstile:pull`
    | yangi qatorlarni (`submission_id` bo‘yicha) o‘qib, `kartid` = `employees.card` bo‘yicha
    | ReportService orqali o‘tish sifatida yozadi — Jonli kuzatuv va Tashrif statistikasi shundan.
    | Ulanish: config/database.php → `turnstile` (TURNSTILE_DB_*).
    |
    */

    // true: scheduler har daqiqa `turnstile:pull --for=55` ni ishga tushiradi (amalda ~2 soniyalik kechikish).
    'enabled' => env('TURNSTILE_ENABLED', false),

    'connection' => 'turnstile',

    'table' => env('TURNSTILE_TABLE', 'gecishareketleri'),

    'poll_seconds' => max((int) env('TURNSTILE_POLL_SECONDS', 2), 1),

    'batch_size' => max((int) env('TURNSTILE_BATCH_SIZE', 500), 1),

    // Faqat shu fuar (`fuarid`) o‘tishlari; bo‘sh — hammasi.
    'fair_id' => env('TURNSTILE_FAIR_ID'),

    // Turniket `tarih` ni qaysi vaqt zonasida yozadi (Expo Panel — Turkiya vaqti, Europe/Istanbul = UTC+3).
    // Bo‘sh — APP_TIMEZONE bilan bir xil deb olinadi.
    'timezone' => env('TURNSTILE_TIMEZONE') ?: null,

];
