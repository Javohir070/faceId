<?php

namespace App\Services\Hikvision;

use App\Exceptions\HikvisionConnectionException;
use App\Models\AccessControllerEvent;
use App\Models\Device;
use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;

class HikvisionEventPullService
{
    public function __construct(
        private readonly IsapiClient $client,
        private readonly ReportService $reportService,
        private readonly HikvisionDeviceService $deviceService,
    ) {}

    /**
     * @return array{imported:int, skipped:int, failed:int}
     */
    public function pullAllDevices(): array
    {
        $imported = 0;
        $skipped = 0;
        $failed = 0;

        Device::query()
            ->where('is_online', true)
            ->orderBy('id')
            ->each(function (Device $device) use (&$imported, &$skipped, &$failed): void {
                try {
                    $result = $this->pullDevice($device);
                    $imported += $result['imported'];
                    $skipped += $result['skipped'];
                } catch (\Throwable $exception) {
                    $failed++;
                    Log::warning('Hikvision: eventlarni tortib olishda xatolik', [
                        'device_id' => $device->id,
                        'message' => $exception->getMessage(),
                    ]);
                }
            });

        if ($imported > 0) {
            Log::info('Hikvision: eventlar tortib olindi', [
                'imported' => $imported,
                'skipped' => $skipped,
                'failed' => $failed,
            ]);
        }

        return compact('imported', 'skipped', 'failed');
    }

    /**
     * @return array{imported:int, skipped:int}
     */
    public function pullDevice(Device $device): array
    {
        if (! $device->hasIsapiCredentials()) {
            return ['imported' => 0, 'skipped' => 0];
        }

        $startTime = $this->lastPullStart($device);
        $endTime = $this->resolveDeviceNow($device);

        if ($endTime->lessThanOrEqualTo($startTime)) {
            $startTime = $endTime->copy()->subDay();
        }

        try {
            $response = $this->client->request(
                $device,
                'POST',
                '/ISAPI/AccessControl/AcsEvent',
                ['format' => 'json'],
                [
                    'AcsEventCond' => [
                        'searchID' => 'biocontrol-'.$device->id,
                        'searchResultPosition' => 0,
                        'maxResults' => 50,
                        'major' => 5,
                        'minor' => 75,
                        'startTime' => $startTime->format('Y-m-d\TH:i:s'),
                        'endTime' => $endTime->format('Y-m-d\TH:i:s'),
                    ],
                ],
            );
        } catch (ConnectionException $exception) {
            throw HikvisionConnectionException::fromThrowable($device, $exception);
        }

        if ($response->failed()) {
            throw new \RuntimeException(
                'AcsEvent javob bermadi: HTTP '.$response->status().' — '.$response->body(),
                $response->status(),
            );
        }

        $items = data_get($response->json(), 'AcsEvent.InfoList', []);

        if (! is_array($items)) {
            return ['imported' => 0, 'skipped' => 0];
        }

        if (array_key_exists('employeeNoString', $items) || array_key_exists('major', $items)) {
            $items = [$items];
        }

        $imported = 0;
        $skipped = 0;

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            if ($this->importEventItem($device, $item)) {
                $imported++;
            } else {
                $skipped++;
            }
        }

        return compact('imported', 'skipped');
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function importEventItem(Device $device, array $item): bool
    {
        $employeeNo = (string) (data_get($item, 'employeeNoString') ?? data_get($item, 'employeeNo') ?? '');

        if ($employeeNo === '') {
            return false;
        }

        $eventTime = data_get($item, 'time') ?? data_get($item, 'dateTime');

        if (! $eventTime) {
            return false;
        }

        $parsedTime = Carbon::parse($eventTime)->format('Y-m-d H:i:s');

        if ($this->eventAlreadyStored($device, $employeeNo, $parsedTime)) {
            return false;
        }

        $payload = [
            'ipAddress' => $device->ip_address,
            'dateTime' => Carbon::parse($eventTime)->toIso8601String(),
            'AccessControllerEvent' => [
                'employeeNoString' => $employeeNo,
                'cardNo' => data_get($item, 'cardNo'),
                'name' => data_get($item, 'name'),
                'majorEventType' => data_get($item, 'major'),
                'subEventType' => data_get($item, 'minor'),
                'currentVerifyMode' => data_get($item, 'currentVerifyMode', 'face'),
                'attendanceStatus' => data_get($item, 'attendanceStatus'),
            ],
        ];

        $report = $this->reportService->createFromWebhookPayload($payload);

        return $report !== null;
    }

    private function eventAlreadyStored(Device $device, string $employeeNo, string $eventTime): bool
    {
        return AccessControllerEvent::query()
            ->where('device_ip', $device->ip_address)
            ->where('employee_no', $employeeNo)
            ->where('event_time', $eventTime)
            ->exists();
    }

    private function lastPullStart(Device $device): Carbon
    {
        $lastEvent = AccessControllerEvent::query()
            ->where('device_ip', $device->ip_address)
            ->whereNotNull('event_time')
            ->latest('event_time')
            ->value('event_time');

        if ($lastEvent) {
            return Carbon::parse($lastEvent)->subMinute();
        }

        return now()->subDays(7);
    }

    private function resolveDeviceNow(Device $device): Carbon
    {
        try {
            $info = $this->deviceService->getDeviceInfo($device);
            $localTime = data_get($info, 'DeviceInfo.localTime')
                ?? data_get($info, 'localTime');

            if (is_string($localTime) && $localTime !== '') {
                return Carbon::parse($localTime);
            }
        } catch (\Throwable) {
            // Qurilma vaqtini o‘qib bo‘lmadi — server vaqtidan foydalanamiz.
        }

        return now();
    }
}
