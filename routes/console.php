<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('devices:ping')
    ->cron('*/'.max((int) config('hikvision.ping_interval_minutes', 1), 1).' * * * *')
    ->when(fn (): bool => (bool) config('hikvision.ping_enabled', true))
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('devices:pull-events')
    ->cron('*/'.max((int) config('hikvision.events_pull_interval_minutes', 1), 1).' * * * *')
    ->when(fn (): bool => (bool) config('hikvision.events_pull_enabled', true))
    ->withoutOverlapping()
    ->runInBackground();

// Turniket (expo.gecishareketleri) — har daqiqa ~55 soniya davomida har TURNSTILE_POLL_SECONDS da tortadi,
// shu sabab jonli sahifalar bir necha soniya kechikadi. Mutex 2 daqiqada eskiradi: jarayon o‘lsa ham ertasi daqiqa qayta turadi.
Schedule::command('turnstile:pull --for=55')
    ->everyMinute()
    ->when(fn (): bool => (bool) config('turnstile.enabled', false))
    ->withoutOverlapping(2)
    ->runInBackground();

// Innoweek API — joyida ro‘yxatdan o‘tgan yangi ishtirokchilar har INNOWEEK_SYNC_INTERVAL soniyada keladi.
Schedule::command('innoweek:sync --for=55')
    ->everyMinute()
    ->when(fn (): bool => (bool) config('innoweek.sync_enabled', false))
    ->withoutOverlapping(2)
    ->runInBackground();

Schedule::command('employees:notify-expiry')
    ->dailyAt('08:00')
    ->withoutOverlapping();

Schedule::command('telegram:daily-digest')
    ->dailyAt('08:30')
    ->withoutOverlapping();

// Spatie laravel-backup (02:00–03:00 oralig'idan qochiladi — DST muammosi)
Schedule::command('backup:clean')
    ->daily()
    ->at('01:00')
    ->withoutOverlapping();

Schedule::command('backup:run')
    ->daily()
    ->at('01:30')
    ->withoutOverlapping();

Schedule::command('backup:monitor')
    ->daily()
    ->at('03:00')
    ->withoutOverlapping();

// alertStream — uzluksiz jarayon; oddiy holda alohida terminalda ishga tushiriladi:
// php artisan devices:stream-events
//
// Serverda (HIKVISION_ALERT_STREAM_AUTOSTART=true) scheduler managerni tirik ushlab turadi:
// --ensure manager ishlayotgan bo‘lsa jim chiqadi, shu sabab har daqiqa yangi manager ochilmaydi.
// Busiz jonli monitor faqat devices:pull-events ga qoladi va eventlar daqiqalab kechikadi.
// withoutOverlapping QO‘YILMAYDI: server o‘chib yonsa mutex 24 soat qolib, manager qayta turmaydi.
Schedule::command('devices:stream-events --ensure')
    ->everyMinute()
    ->when(fn (): bool => (bool) config('hikvision.alert_stream_enabled', true)
        && (bool) config('hikvision.alert_stream_autostart', false))
    ->runInBackground();
