<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Services\Hikvision\HikvisionAlertStreamService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Process;

class StreamDeviceEventsCommand extends Command
{
    protected $signature = 'devices:stream-events
                            {--device= : Faqat shu qurilma ID si uchun stream}
                            {--worker : Ichki worker rejimi (manager tomonidan ishga tushiriladi)}';

    protected $description = 'Bazadagi Hikvision qurilmalaridan real vaqtda alertStream eventlarini qabul qiladi';

    /** @var array<int, Process> */
    private array $workers = [];

    public function handle(
        HikvisionAlertStreamService $alertStreamService,
    ): int {
        $deviceId = $this->option('device');
        $manualDevice = $deviceId !== null && $deviceId !== '';

        if (! $manualDevice && ! config('hikvision.alert_stream_enabled', false)) {
            $this->comment('alertStream o‘chirilgan (HIKVISION_ALERT_STREAM_ENABLED=false).');
            $this->comment('Qo‘lda sinash: php artisan devices:stream-events --device=1');

            return self::SUCCESS;
        }

        if ($manualDevice) {
            return $this->runDeviceWorker($alertStreamService, (int) $deviceId);
        }

        if ($this->option('worker')) {
            $this->error('--worker rejimi --device bilan birga ishlatiladi.');

            return self::FAILURE;
        }

        return $this->runManager();
    }

    private function runManager(): int
    {
        $this->info('Hikvision alertStream manager ishga tushdi (bazadagi qurilmalar).');

        while (true) {
            $devices = Device::streamableFromDatabase();
            $activeIds = [];

            if ($devices->isEmpty()) {
                $this->comment('Bazada ISAPI ma’lumotli qurilma topilmadi.');
            }

            foreach ($devices as $device) {
                $activeIds[] = $device->id;

                if (isset($this->workers[$device->id]) && $this->workers[$device->id]->isRunning()) {
                    continue;
                }

                $process = new Process([
                    PHP_BINARY,
                    base_path('artisan'),
                    'devices:stream-events',
                    '--device='.$device->id,
                    '--worker',
                ]);

                $process->setTimeout(null);
                $process->start(function (string $type, string $output): void {
                    $this->output->write($output);
                });

                $this->workers[$device->id] = $process;

                $this->line(sprintf(
                    'Qurilma #%d (%s) uchun alertStream worker ishga tushdi.',
                    $device->id,
                    $device->ip_address,
                ));
            }

            foreach (array_keys($this->workers) as $workerDeviceId) {
                if (! in_array($workerDeviceId, $activeIds, true)) {
                    $this->workers[$workerDeviceId]->stop(5);
                    unset($this->workers[$workerDeviceId]);
                    $this->line(sprintf('Qurilma #%d worker to‘xtatildi (bazadan o‘chirilgan yoki ma’lumot yetishmayapti).', $workerDeviceId));
                }
            }

            sleep(max((int) config('hikvision.alert_stream_reconnect_seconds', 5), 1));
        }
    }

    private function runDeviceWorker(
        HikvisionAlertStreamService $alertStreamService,
        int $deviceId,
    ): int {
        $reconnectDelay = max((int) config('hikvision.alert_stream_reconnect_seconds', 30), 5);
        $lock = Cache::lock("hikvision.alert_stream.device.{$deviceId}", 86400);

        if (! $lock->get()) {
            $this->warn("Qurilma #{$deviceId} uchun alertStream worker allaqachon ishlayapti.");

            return self::SUCCESS;
        }

        try {
            while (true) {
                $device = Device::query()->find($deviceId);

                if ($device === null) {
                    $this->error("Qurilma bazadan topilmadi: {$deviceId}");

                    return self::FAILURE;
                }

                if (! $device->hasIsapiCredentials()) {
                    $this->warn(sprintf(
                        'Qurilma #%d ISAPI ma’lumotlari to‘liq emas — %d soniyadan keyin qayta tekshiriladi.',
                        $device->id,
                        $reconnectDelay,
                    ));
                    sleep($reconnectDelay);

                    continue;
                }

                $this->info(sprintf(
                    'Qurilma #%d (%s) uchun alertStream tinglanmoqda…',
                    $device->id,
                    $device->ip_address,
                ));

                try {
                    $alertStreamService->listen($device, function (array $event) use ($device): void {
                        $employeeNo = data_get($event, 'AccessControllerEvent.employeeNoString', '—');
                        $this->line(sprintf(
                            '[%s] #%d (%s) event: xodim %s',
                            now()->format('H:i:s'),
                            $device->id,
                            $device->ip_address,
                            $employeeNo,
                        ));
                    });
                } catch (\Throwable $exception) {
                    $this->warn(sprintf(
                        'Qurilma #%d alertStream xatosi: %s',
                        $device->id,
                        $exception->getMessage(),
                    ));
                }

                sleep($reconnectDelay);
                $this->comment(sprintf('Qurilma #%d uchun qayta ulanish…', $deviceId));
            }
        } finally {
            $lock->release();
        }
    }
}
