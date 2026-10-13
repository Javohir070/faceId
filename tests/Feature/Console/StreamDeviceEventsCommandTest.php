<?php

namespace Tests\Feature\Console;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class StreamDeviceEventsCommandTest extends TestCase
{
    private const HEARTBEAT_KEY = 'hikvision.alert_stream.manager.heartbeat';

    public function test_ensure_exits_quietly_when_manager_is_already_running(): void
    {
        Cache::put(self::HEARTBEAT_KEY, time(), 300);

        $this->artisan('devices:stream-events --ensure')
            ->doesntExpectOutputToContain('allaqachon')
            ->assertSuccessful();
    }

    public function test_second_manager_without_ensure_is_rejected(): void
    {
        Cache::put(self::HEARTBEAT_KEY, time(), 300);

        $this->artisan('devices:stream-events')
            ->expectsOutputToContain('allaqachon')
            ->assertFailed();
    }

    public function test_scheduler_keeps_manager_alive_only_when_autostart_is_enabled(): void
    {
        $event = $this->streamEnsureEvent();

        $this->assertNotNull($event, 'devices:stream-events --ensure schedulerda ro‘yxatdan o‘tmagan.');
        $this->assertSame('* * * * *', $event->expression);
        $this->assertTrue($event->runInBackground);
        // Mutex server o‘chib yonganda 24 soat qolib ketadi — himoya --ensure heartbeat orqali.
        $this->assertFalse($event->withoutOverlapping);

        config(['hikvision.alert_stream_enabled' => true, 'hikvision.alert_stream_autostart' => false]);
        $this->assertFalse($event->filtersPass($this->app));

        config(['hikvision.alert_stream_autostart' => true]);
        $this->assertTrue($event->filtersPass($this->app));

        config(['hikvision.alert_stream_enabled' => false]);
        $this->assertFalse($event->filtersPass($this->app));
    }

    private function streamEnsureEvent(): ?Event
    {
        return collect($this->app->make(Schedule::class)->events())
            ->first(fn (Event $event): bool => str_contains((string) $event->command, 'devices:stream-events --ensure'));
    }
}
