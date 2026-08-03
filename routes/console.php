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

Schedule::command('employees:notify-expiry')
    ->dailyAt('08:00')
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

// alertStream — uzluksiz jarayon; schedule orqali ISHLATILMAYDI (har daqiqa yangi
// manager ochilib qurilmani yuklaydi). Alohida terminalda ishga tushiring:
// php artisan devices:stream-events
