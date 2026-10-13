<?php

namespace App\Services\Hikvision;

use App\Models\Device;
use App\Services\ReportService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\FnStream;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class HikvisionAlertStreamService
{
    /**
     * @param  (callable(RequestInterface, array<string, mixed>): PromiseInterface)|null  $httpHandler  Testlar uchun (MockHandler)
     */
    public function __construct(
        private readonly IsapiClient $client,
        private readonly HikvisionEventParser $parser,
        private readonly ReportService $reportService,
        private $httpHandler = null,
    ) {}

    /**
     * Ulanish uzilmaguncha bloklaydi; har bir event kelishi bilan qayta ishlanadi.
     *
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

        $buffer = '';
        $boundary = null;
        $streaming = false;
        $errorBody = '';

        try {
            $response = $httpClient->request('GET', $url, [
                // Digest auth ning birinchi 401 (challenge) javobi ham shu yerdan o‘tadi.
                'on_headers' => function (ResponseInterface $response) use ($device, &$streaming): void {
                    $streaming = $response->getStatusCode() < 400;

                    if ($streaming) {
                        $this->markConnected($device);
                    }
                },
                'sink' => FnStream::decorate(Utils::streamFor(''), [
                    'write' => function (string $chunk) use ($device, $onEvent, &$buffer, &$boundary, &$streaming, &$errorBody): int {
                        if (! $streaming) {
                            $errorBody = substr($errorBody.$chunk, 0, 2048);

                            return strlen($chunk);
                        }

                        foreach ($this->parser->extractEventsFromMultipart($chunk, $boundary, $buffer) as $event) {
                            $this->handleEvent($device, $event, $onEvent, (string) $device->ip_address);
                        }

                        return strlen($chunk);
                    },
                ]),
            ]);
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
                'body' => $errorBody,
            ]);

            throw new \RuntimeException(
                'alertStream javob bermadi: HTTP '.$response->getStatusCode(),
                $response->getStatusCode(),
            );
        }

        Log::warning('Hikvision: alertStream uzildi', [
            'device_id' => $device->id,
        ]);
    }

    private function markConnected(Device $device): void
    {
        $device->forceFill([
            'is_online' => true,
            'last_connected_at' => now(),
        ])->save();

        Log::info('Hikvision: alertStream ulandi', [
            'device_id' => $device->id,
            'ip_address' => $device->ip_address,
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

        try {
            $report = $this->reportService->createFromWebhookPayload($event);
        } catch (\Throwable $exception) {
            // Bitta event xatosi butun streamni uzmasin.
            Log::error('Hikvision: alertStream eventni saqlashda xatolik', [
                'device_id' => $device->id,
                'message' => $exception->getMessage(),
            ]);

            return;
        }

        Log::debug('Hikvision: alertStream event qayta ishlandi', [
            'device_id' => $device->id,
            'employee_no' => data_get($event, 'AccessControllerEvent.employeeNoString'),
            'report_created' => $report !== null,
        ]);

        if ($onEvent !== null) {
            $onEvent($event);
        }
    }

    /**
     * Guzzle `stream` opsiyasi PHP stream handleriga o‘tadi va u Digest auth ni
     * qo‘llamaydi (har doim 401). Shu sabab curl handler + `sink` orqali o‘qiladi.
     */
    private function createStreamClient(Device $device): Client
    {
        $options = [
            'handler' => HandlerStack::create($this->httpHandler ?? new CurlHandler),
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
