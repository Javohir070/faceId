<?php

namespace App\Console\Commands;

use App\Services\Hikvision\HikvisionEventPullService;
use Illuminate\Console\Command;

class PullDeviceEventsCommand extends Command
{
    protected $signature = 'devices:pull-events';

    protected $description = 'Hikvision qurilmalaridan kirish/chiqish eventlarini ISAPI orqali tortib oladi';

    public function handle(HikvisionEventPullService $eventPullService): int
    {
        if (! config('hikvision.events_pull_enabled', true)) {
            $this->comment('Event tortib olish o‘chirilgan (HIKVISION_EVENTS_PULL_ENABLED=false).');

            return self::SUCCESS;
        }

        $result = $eventPullService->pullAllDevices();

        $this->info(sprintf(
            'Eventlar: %d yangi, %d o‘tkazildi, %d xato.',
            $result['imported'],
            $result['skipped'],
            $result['failed'],
        ));

        return self::SUCCESS;
    }
}
