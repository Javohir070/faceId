<?php

namespace App\Services\Hikvision;

use App\Exceptions\HikvisionConnectionException;
use App\Models\AccessControllerEvent;
use App\Models\Device;
use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;

class HikvisionEventPullService
{
    /** Ko‘p Hikvision qurilmalar maxResults ni 30 ga kesadi. */
    private const MAX_RESULTS_PER_PAGE = 30;

    /** Bir qurilma / bir so‘rov uchun xavfsizlik limiti (sahifa emas — event soni). */
    private const MAX_EVENTS_PER_DEVICE = 100000;

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
     * @param  list<int>  $deviceIds
     * @return array{imported:int, skipped:int, skipped_duplicate:int, skipped_invalid:int, failed:int, devices:int, truncated:bool}
     */
    public function pullDevicesInRange(array $deviceIds, Carbon $startTime, Carbon $endTime): array
    {
        $imported = 0;
        $skippedDuplicate = 0;
        $skippedInvalid = 0;
        $failed = 0;
        $devices = 0;
        $truncated = false;

        Device::query()
            ->whereIn('id', $deviceIds)
            ->orderBy('id')
            ->each(function (Device $device) use ($startTime, $endTime, &$imported, &$skippedDuplicate, &$skippedInvalid, &$failed, &$devices, &$truncated): void {
                if (! $device->hasIsapiCredentials()) {
                    $failed++;

                    return;
                }

                $devices++;

                try {
                    $result = $this->pullDeviceRange($device, $startTime->copy(), $endTime->copy());
                    $imported += $result['imported'];
                    $skippedDuplicate += $result['skipped_duplicate'];
                    $skippedInvalid += $result['skipped_invalid'];
                    $truncated = $truncated || $result['truncated'];
                } catch (\Throwable $exception) {
                    $failed++;
                    Log::warning('Hikvision: sana oralig‘ida event tortib olishda xatolik', [
                        'device_id' => $device->id,
                        'start' => $startTime->toDateTimeString(),
                        'end' => $endTime->toDateTimeString(),
                        'message' => $exception->getMessage(),
                    ]);
                }
            });

        Log::info('Hikvision: qo‘lda eventlar tortib olindi', [
            'devices' => $devices,
            'imported' => $imported,
            'skipped_duplicate' => $skippedDuplicate,
            'skipped_invalid' => $skippedInvalid,
            'failed' => $failed,
            'truncated' => $truncated,
            'start' => $startTime->toDateTimeString(),
            'end' => $endTime->toDateTimeString(),
        ]);

        return [
            'imported' => $imported,
            'skipped' => $skippedDuplicate + $skippedInvalid,
            'skipped_duplicate' => $skippedDuplicate,
            'skipped_invalid' => $skippedInvalid,
            'failed' => $failed,
            'devices' => $devices,
            'truncated' => $truncated,
        ];
    }

    /**
     * @return array{imported:int, skipped:int, skipped_duplicate:int, skipped_invalid:int, truncated:bool}
     */
    public function pullDevice(Device $device): array
    {
        if (! $device->hasIsapiCredentials()) {
            return [
                'imported' => 0,
                'skipped' => 0,
                'skipped_duplicate' => 0,
                'skipped_invalid' => 0,
                'truncated' => false,
            ];
        }

        $startTime = $this->lastPullStart($device);
        $endTime = $this->resolveDeviceNow($device);

        if ($endTime->lessThanOrEqualTo($startTime)) {
            $startTime = $endTime->copy()->subDay();
        }

        return $this->pullDeviceRange($device, $startTime, $endTime);
    }

    /**
     * Faqat bugungi yangi eventlar — jonli monitor uchun tez-tez chaqiriladi.
     * alertStream qurilmada to‘planib qolgan eski eventlarni yuborayotgan paytda ham
     * bugungi o‘tishlar kechikmasligi uchun.
     *
     * @return array{imported:int, skipped:int, skipped_duplicate:int, skipped_invalid:int, truncated:bool}
     */
    public function pullToday(Device $device): array
    {
        $dayStart = now()->startOfDay();

        $lastToday = AccessControllerEvent::query()
            ->where('device_ip', $device->ip_address)
            ->where('event_time', '>=', $dayStart)
            ->max('event_time');

        $startTime = $lastToday !== null
            ? Carbon::parse($lastToday)->subSeconds(5)
            : $dayStart;

        // Qurilma soati serverdan oldinda bo‘lishi mumkin — yuqori chegara keng olinadi.
        return $this->pullDeviceRange($device, $startTime, now()->addDay());
    }

    /**
     * @return array{imported:int, skipped:int, skipped_duplicate:int, skipped_invalid:int, truncated:bool}
     */
    public function pullDeviceRange(Device $device, Carbon $startTime, Carbon $endTime): array
    {
        if ($endTime->lessThan($startTime)) {
            return [
                'imported' => 0,
                'skipped' => 0,
                'skipped_duplicate' => 0,
                'skipped_invalid' => 0,
                'truncated' => false,
            ];
        }

        $imported = 0;
        $skippedDuplicate = 0;
        $skippedInvalid = 0;
        $position = 0;
        $processed = 0;
        $truncated = false;
        $status = '';
        // Hikvision pagination: bir xil searchID + o‘sib boruvchi searchResultPosition.
        $searchId = sprintf('biocontrol-%d-%s', $device->id, $startTime->format('YmdHis'));

        while ($processed < self::MAX_EVENTS_PER_DEVICE) {
            $response = $this->fetchAcsEvents($device, $startTime, $endTime, $position, $searchId);
            $payload = $response->json();
            $items = $this->normalizeInfoList(data_get($payload, 'AcsEvent.InfoList', []));
            $status = (string) data_get($payload, 'AcsEvent.responseStatusStrg', '');
            $totalMatches = (int) data_get($payload, 'AcsEvent.totalMatches', 0);

            foreach ($items as $item) {
                $outcome = $this->importEventItem($device, $item);
                if ($outcome === 'imported') {
                    $imported++;
                } elseif ($outcome === 'duplicate') {
                    $skippedDuplicate++;
                } else {
                    $skippedInvalid++;
                }
                $processed++;
            }

            $matchCount = count($items);
            if ($matchCount === 0 || $status !== 'MORE') {
                break;
            }

            $position += $matchCount;

            if ($totalMatches > 0 && $position >= $totalMatches) {
                break;
            }
        }

        if ($status === 'MORE' && $processed >= self::MAX_EVENTS_PER_DEVICE) {
            $truncated = true;
            Log::warning('Hikvision: event tortish limiti yetdi', [
                'device_id' => $device->id,
                'processed' => $processed,
                'start' => $startTime->toDateTimeString(),
                'end' => $endTime->toDateTimeString(),
            ]);
        }

        return [
            'imported' => $imported,
            'skipped' => $skippedDuplicate + $skippedInvalid,
            'skipped_duplicate' => $skippedDuplicate,
            'skipped_invalid' => $skippedInvalid,
            'truncated' => $truncated,
        ];
    }

    /**
     * @return Response
     */
    private function fetchAcsEvents(
        Device $device,
        Carbon $startTime,
        Carbon $endTime,
        int $position,
        string $searchId,
    ) {
        try {
            $response = $this->client->request(
                $device,
                'POST',
                '/ISAPI/AccessControl/AcsEvent',
                ['format' => 'json'],
                [
                    'AcsEventCond' => [
                        'searchID' => $searchId,
                        'searchResultPosition' => $position,
                        'maxResults' => self::MAX_RESULTS_PER_PAGE,
                        // major=5: AccessControllerEvent; minor=0: barcha turlar
                        // (identifikatorsiz eshik eventlari import qilinmaydi).
                        'major' => 5,
                        'minor' => 0,
                        'startTime' => $startTime->format('Y-m-d\TH:i:sP'),
                        'endTime' => $endTime->format('Y-m-d\TH:i:sP'),
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

        return $response;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizeInfoList(mixed $items): array
    {
        if (! is_array($items) || $items === []) {
            return [];
        }

        if (array_key_exists('employeeNoString', $items) || array_key_exists('major', $items)) {
            return [$items];
        }

        return array_values(array_filter($items, 'is_array'));
    }

    /**
     * @param  array<string, mixed>  $item
     * @return 'imported'|'duplicate'|'invalid'
     */
    private function importEventItem(Device $device, array $item): string
    {
        $employeeNo = (string) (data_get($item, 'employeeNoString') ?? data_get($item, 'employeeNo') ?? '');
        $cardNo = (string) (data_get($item, 'cardNo') ?? '');
        $name = (string) (data_get($item, 'name') ?? '');

        if ($employeeNo === '' && $cardNo !== '') {
            $employeeNo = $cardNo;
        }

        if ($employeeNo === '' && $name === '') {
            return 'invalid';
        }

        $eventTime = data_get($item, 'time') ?? data_get($item, 'dateTime');

        if (! $eventTime) {
            return 'invalid';
        }

        $parsedTime = Carbon::parse($eventTime)->format('Y-m-d H:i:s');

        if ($employeeNo !== '' && $this->eventAlreadyStored($device, $employeeNo, $parsedTime)) {
            return 'duplicate';
        }

        $payload = [
            'ipAddress' => $device->ip_address,
            'dateTime' => Carbon::parse($eventTime)->toIso8601String(),
            'AccessControllerEvent' => [
                'employeeNoString' => $employeeNo !== '' ? $employeeNo : null,
                'cardNo' => $cardNo !== '' ? $cardNo : null,
                'name' => $name !== '' ? $name : null,
                'majorEventType' => data_get($item, 'major'),
                'subEventType' => data_get($item, 'minor'),
                'currentVerifyMode' => data_get($item, 'currentVerifyMode', 'face'),
                'attendanceStatus' => data_get($item, 'attendanceStatus'),
            ],
        ];

        $report = $this->reportService->createFromWebhookPayload($payload);

        return $report !== null ? 'imported' : 'duplicate';
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
