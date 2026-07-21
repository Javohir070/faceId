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
