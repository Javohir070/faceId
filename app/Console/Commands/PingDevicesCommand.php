<?php

namespace App\Console\Commands;

use App\Services\Hikvision\HikvisionDeviceService;
use Illuminate\Console\Command;

class PingDevicesCommand extends Command
{
    protected $signature = 'devices:ping';

    protected $description = 'Barcha Hikvision qurilmalariga ISAPI ping yuborib ulanish holatini yangilaydi';

    public function handle(HikvisionDeviceService $deviceService): int
    {
        if (! config('hikvision.ping_enabled', true)) {
            $this->comment('Qurilma ping o‘chirilgan (HIKVISION_PING_ENABLED=false).');

            return self::SUCCESS;
        }

        $result = $deviceService->pingAllDevices();

        $this->info(sprintf(
            'Ping yakunlandi: %d onlayn, %d oflayn.',
            $result['online'],
            $result['offline'],
        ));

        return self::SUCCESS;
    }
}
