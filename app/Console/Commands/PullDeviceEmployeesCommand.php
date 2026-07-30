<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Services\Hikvision\EmployeePullService;
use Illuminate\Console\Command;

class PullDeviceEmployeesCommand extends Command
{
    protected $signature = 'devices:pull-employees {device? : Qurilma ID (bo‘sh bo‘lsa barcha online)}';

    protected $description = 'Hikvision qurilmalaridan xodimlarni ISAPI UserInfo/Search orqali tortib oladi (dublikatsiz)';

    public function handle(EmployeePullService $employeePullService): int
    {
        $deviceId = $this->argument('device');

        if ($deviceId !== null) {
            $device = Device::query()->find($deviceId);

            if ($device === null) {
                $this->error("Qurilma #{$deviceId} topilmadi.");

                return self::FAILURE;
            }

            if (! $device->hasIsapiCredentials()) {
                $this->error('Qurilmada login/parol sozlanmagan.');

                return self::FAILURE;
            }

            try {
                $result = $employeePullService->pullDevice($device);
            } catch (\Throwable $exception) {
                $this->error('Xodimlarni tortib bo‘lmadi: '.$exception->getMessage());

                return self::FAILURE;
            }
        } else {
            $result = $employeePullService->pullAllDevices();
        }

        $this->info(sprintf(
            'Xodimlar: %d yangi, %d yangilangan, %d bog‘langan, %d o‘tkazildi, %d yuz, %d xato.',
            $result['created'],
            $result['updated'],
            $result['linked'],
            $result['skipped'],
            $result['faces'],
            $result['failed'],
        ));

        return self::SUCCESS;
    }
}
