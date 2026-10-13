<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Services\Hikvision\HikvisionAlertStreamService;
use App\Services\Hikvision\HikvisionEventPullService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Process;

class StreamDeviceEventsCommand extends Command
{
    protected $signature = 'devices:stream-events
                            {--device= : Faqat shu qurilma ID si uchun stream}
                            {--worker : Ichki worker rejimi (manager tomonidan ishga tushiriladi)}
                            {--ensure : Cron uchun: manager ishlayotgan bo‘lsa jim chiqadi, ishlamayotgan bo‘lsa ishga tushiradi}';

    protected $description = 'Bazadagi Hikvision qurilmalaridan real vaqtda alertStream eventlarini qabul qiladi';

    private const LIVE_PULL_RETRY_SECONDS = 30;

    private const MANAGER_HEARTBEAT_KEY = 'hikvision.alert_stream.manager.heartbeat';

    private const MANAGER_HEARTBEAT_STALE_SECONDS = 60;

    /** @var array<int, Process> */
    private array $workers = [];

    /** @var array<int, int> Jonli tortish xato bergan qurilma => qayta urinish vaqti (timestamp) */
    private array $livePullRetryAt = [];

    public function handle(
        HikvisionAlertStreamService $alertStreamService,
        HikvisionEventPullService $eventPullService,
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

        return $this->runManager($eventPullService);
    }

    private function runManager(HikvisionEventPullService $eventPullService): int
    {
        // Qurilma bitta alertStream ulanishini qabul qiladi — ikkinchi manager faqat 404 oladi.
        $lastHeartbeat = (int) Cache::get(self::MANAGER_HEARTBEAT_KEY, 0);

        if (time() - $lastHeartbeat < self::MANAGER_HEARTBEAT_STALE_SECONDS) {
            if ($this->option('ensure')) {
                return self::SUCCESS;
            }

            $this->error('alertStream manager allaqachon boshqa terminalda ishlayapti. Avval uni to‘xtating.');

            return self::FAILURE;
        }

        $this->info('Hikvision alertStream manager ishga tushdi (bazadagi qurilmalar).');

        $livePull = (bool) config('hikvision.live_pull_enabled', true);
        $workerCheckSeconds = max((int) config('hikvision.alert_stream_reconnect_seconds', 5), 1);
        $tickSeconds = $livePull
            ? max((int) config('hikvision.live_pull_interval_seconds', 2), 1)
            : $workerCheckSeconds;
        $nextWorkerCheckAt = 0;

        while (true) {
            Cache::put(self::MANAGER_HEARTBEAT_KEY, time(), 300);

            if (time() < $nextWorkerCheckAt) {
                $this->pullLiveEvents($eventPullService);
                sleep($tickSeconds);

                continue;
            }

            $nextWorkerCheckAt = time() + $workerCheckSeconds;

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

                // Worker majburan to‘xtatilgan bo‘lsa (Ctrl+C) qulfi qolib ketadi — manager o‘z workerini qayta ochadi.
                Cache::lock($this->workerLockKey($device->id))->forceRelease();

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

            if ($livePull) {
                $this->pullLiveEvents($eventPullService);
            }

            sleep($tickSeconds);
        }
    }

    /**
     * Onlayn qurilmalardan bugungi yangi eventlarni tortadi (alertStream kechikkanda zaxira).
     */
    private function pullLiveEvents(HikvisionEventPullService $eventPullService): void
    {
        if (! config('hikvision.live_pull_enabled', true)) {
            return;
        }

        $devices = Device::streamableFromDatabase()->where('is_online', true);

        foreach ($devices as $device) {
            if (($this->livePullRetryAt[$device->id] ?? 0) > time()) {
                continue;
            }

            // Sekin javob beradigan qurilma tufayli manager "o‘lgan" deb hisoblanmasin.
            Cache::put(self::MANAGER_HEARTBEAT_KEY, time(), 300);

            try {
                $result = $eventPullService->pullToday($device);
                unset($this->livePullRetryAt[$device->id]);

                if ($result['imported'] > 0) {
                    $this->line(sprintf(
                        '[%s] #%d (%s) jonli tortish: %d yangi event.',
                        now()->format('H:i:s'),
                        $device->id,
                        $device->ip_address,
                        $result['imported'],
                    ));
                }
            } catch (\Throwable $exception) {
                // Javob bermayotgan qurilma har tikda timeout kutib, qolganlarini kechiktirmasin.
                $this->livePullRetryAt[$device->id] = time() + self::LIVE_PULL_RETRY_SECONDS;
                $this->warn(sprintf(
                    'Qurilma #%d jonli tortish xatosi: %s',
                    $device->id,
                    $exception->getMessage(),
                ));
            }
        }
    }

    private function workerLockKey(int $deviceId): string
    {
        return "hikvision.alert_stream.device.{$deviceId}";
    }

    private function runDeviceWorker(
        HikvisionAlertStreamService $alertStreamService,
        int $deviceId,
    ): int {
        $reconnectDelay = max((int) config('hikvision.alert_stream_reconnect_seconds', 30), 5);
        $lock = Cache::lock($this->workerLockKey($deviceId), 86400);

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
