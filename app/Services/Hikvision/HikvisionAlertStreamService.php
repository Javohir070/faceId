<?php

namespace App\Services\Hikvision;

use App\Models\Device;
use App\Services\ReportService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\StreamInterface;

class HikvisionAlertStreamService
{
    public function __construct(
        private readonly IsapiClient $client,
        private readonly HikvisionEventParser $parser,
        private readonly ReportService $reportService,
    ) {}

    /**
     * @param  callable(array<string, mixed>): void|null  $onEvent
     */
    public function listen(Device $device, ?callable $onEvent = null): void
    {
        if (! $device->hasIsapiCredentials()) {
            throw new \InvalidArgumentException('Qurilma ISAPI ma’lumotlari to‘liq emas.');
        }

        $url = $this->client->url($device, '/ISAPI/Event/notification/alertStream');
        $httpClient = $this->createStreamClient($device);

        Log::info('Hikvision: alertStream ulanishi ochilmoqda', [
            'device_id' => $device->id,
            'url' => $url,
        ]);

        try {
            $response = $httpClient->request('GET', $url, ['stream' => true]);
        } catch (GuzzleException $exception) {
            Log::warning('Hikvision: alertStream ulanishi muvaffaqiyatsiz', [
                'device_id' => $device->id,
                'url' => $url,
                'message' => $exception->getMessage(),
            ]);

            throw new \RuntimeException(
                'alertStream ulanishi muvaffaqiyatsiz: '.$exception->getMessage(),
                0,
                $exception,
            );
        }

        if ($response->getStatusCode() >= 400) {
            Log::warning('Hikvision: alertStream javob bermadi', [
                'device_id' => $device->id,
                'url' => $url,
                'status' => $response->getStatusCode(),
                'body' => (string) $response->getBody(),
            ]);

            throw new \RuntimeException(
                'alertStream javob bermadi: HTTP '.$response->getStatusCode(),
                $response->getStatusCode(),
            );
        }

        $device->forceFill([
            'is_online' => true,
            'last_connected_at' => now(),
        ])->save();

        Log::info('Hikvision: alertStream ulandi', [
            'device_id' => $device->id,
            'ip_address' => $device->ip_address,
        ]);

        $this->consumeStream($device, $response->getBody(), $onEvent);
    }

    /**
     * @param  callable(array<string, mixed>): void|null  $onEvent
     */
    private function consumeStream(Device $device, StreamInterface $stream, ?callable $onEvent): void
    {
        $buffer = '';
        $boundary = null;
        $deviceIp = $device->ip_address;

        while (! $stream->eof()) {
            $chunk = $stream->read(8192);

            if ($chunk === '') {
                usleep(100_000);

                continue;
            }

            $events = $this->parser->extractEventsFromMultipart($chunk, $boundary, $buffer);

            foreach ($events as $event) {
                $this->handleEvent($device, $event, $onEvent, $deviceIp);
            }
        }

        Log::warning('Hikvision: alertStream uzildi', [
            'device_id' => $device->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $event
     * @param  callable(array<string, mixed>): void|null  $onEvent
     */
    private function handleEvent(Device $device, array $event, ?callable $onEvent, string $deviceIp): void
    {
        if (! isset($event['ipAddress'])) {
            $event['ipAddress'] = $deviceIp;
        }

        $report = $this->reportService->createFromWebhookPayload($event);

        Log::debug('Hikvision: alertStream event qayta ishlandi', [
            'device_id' => $device->id,
            'employee_no' => data_get($event, 'AccessControllerEvent.employeeNoString'),
            'report_created' => $report !== null,
        ]);

        if ($onEvent !== null) {
            $onEvent($event);
        }
    }

    private function createStreamClient(Device $device): Client
    {
        $options = [
            'auth' => [$device->user, $device->password, 'digest'],
            'connect_timeout' => config('hikvision.alert_stream_connect_timeout', 15),
            'timeout' => config('hikvision.alert_stream_timeout', 0),
            'http_errors' => false,
        ];

        if ($device->use_https) {
            $options['verify'] = (bool) config('hikvision.verify_ssl', false);
        }

        return new Client($options);
    }
}
