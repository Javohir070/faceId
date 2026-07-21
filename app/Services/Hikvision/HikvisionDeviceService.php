<?php

namespace App\Services\Hikvision;

use App\Exceptions\HikvisionConnectionException;
use App\Models\Device;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;

class HikvisionDeviceService
{
    public function __construct(
        private readonly IsapiClient $client,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getDeviceInfo(Device $device): array
    {
        try {
            $response = $this->client->request(
                $device,
                'GET',
                '/ISAPI/System/deviceInfo',
                ['format' => 'json'],
            );
        } catch (ConnectionException $exception) {
            throw HikvisionConnectionException::fromThrowable($device, $exception);
        }

        if ($response->failed()) {
            throw new \RuntimeException(
                'Qurilma javob bermadi: HTTP '.$response->status().' ['.$device->isapiBaseUrl().']',
                $response->status(),
            );
        }

        return $response->json() ?? [];
    }

    public function testConnection(Device $device): bool
    {
        $resolved = $this->resolveWorkingConnection($device);
        $this->persistConnectionSettings($device, $resolved);

        $this->getDeviceInfo($resolved);

        return true;
    }

    public function connect(Device $device): bool
    {
        $resolved = $this->resolveWorkingConnection($device);
        $this->persistConnectionSettings($device, $resolved);

        $this->getDeviceInfo($resolved);
        $this->setupWebhook($resolved);

        $device->forceFill([
            'is_online' => true,
            'last_connected_at' => now(),
            'webhook_configured' => true,
        ])->save();

        Log::info('Hikvision: qurilma ulandi', [
            'device_id' => $device->id,
            'url' => $device->isapiBaseUrl(),
        ]);

        return true;
    }

    public function refreshOnlineStatus(Device $device): bool
    {
        $device->forceFill(['last_ping_at' => now()]);

        try {
            $resolved = $this->resolveWorkingConnection($device);
            $this->persistConnectionSettings($device, $resolved);

            $device->forceFill([
                'is_online' => true,
                'last_connected_at' => now(),
            ])->save();

            return true;
        } catch (\Throwable $exception) {
            $device->forceFill(['is_online' => false])->save();

            Log::debug('Hikvision: qurilma ping muvaffaqiyatsiz', [
                'device_id' => $device->id,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @return array{online:int, offline:int, skipped:int}
     */
    public function pingAllDevices(): array
    {
        $online = 0;
        $offline = 0;
        $skipped = 0;

        Device::query()
            ->orderBy('id')
            ->chunkById(20, function ($devices) use (&$online, &$offline, &$skipped): void {
                foreach ($devices as $device) {
                    if (! $device->hasIsapiCredentials()) {
                        $skipped++;

                        continue;
                    }

                    if ($this->refreshOnlineStatus($device)) {
                        $online++;
                    } else {
                        $offline++;
                    }
                }
            });

        Log::info('Hikvision: qurilmalar ping yakunlandi', [
            'online' => $online,
            'offline' => $offline,
            'skipped' => $skipped,
        ]);

        return [
            'online' => $online,
            'offline' => $offline,
            'skipped' => $skipped,
        ];
    }

    /**
     * @param  iterable<int, Device>  $devices
     */
    public function refreshOnlineStatuses(iterable $devices): void
    {
        foreach ($devices as $device) {
            $this->refreshOnlineStatus($device);
        }
    }

    public function disconnect(Device $device): void
    {
        $device->forceFill([
            'is_online' => false,
        ])->save();

        Log::info('Hikvision: qurilma uzildi', ['device_id' => $device->id]);
    }

    public function setupWebhook(Device $device): bool
    {
        [$webhookUrl, $host, $serverPort, $path] = $this->resolveWebhookTarget();

        try {
            $response = $this->client->request(
                $device,
                'PUT',
                '/ISAPI/Event/notification/httpHosts/1',
                ['format' => 'json'],
                [
                    'HttpHostNotification' => [
                        'id' => 1,
                        'url' => $path,
                        'protocolType' => 'HTTP',
                        'parameterFormatType' => 'JSON',
                        'addressingFormatType' => 'ipaddress',
                        'ipAddress' => $host,
                        'portNo' => $serverPort,
                        'httpAuthenticationMethod' => 'none',
                        'httpMethod' => 'POST',
                    ],
                ],
            );
        } catch (ConnectionException $exception) {
            throw HikvisionConnectionException::fromThrowable($device, $exception);
        }

        if ($response->failed()) {
            Log::error('Hikvision: webhook sozlashda xatolik', [
                'device_id' => $device->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        }

        Log::info('Hikvision: webhook sozlandi', [
            'device_id' => $device->id,
            'url' => $webhookUrl,
        ]);

        return true;
    }

    /**
     * @return array{0:string,1:string,2:int,3:string}
     */
    private function resolveWebhookTarget(): array
    {
        $configuredUrl = (string) (config('hikvision.webhook_url') ?: '');

        if ($configuredUrl !== '') {
            $parsed = parse_url($configuredUrl);

            if (! is_array($parsed) || empty($parsed['host'])) {
                throw new \RuntimeException('Webhook URL noto‘g‘ri sozlangan.');
            }

            $serverPort = isset($parsed['port']) ? (int) $parsed['port'] : ($parsed['scheme'] === 'https' ? 443 : 80);
            $path = $parsed['path'] ?? config('hikvision.webhook_path');

            if (! empty($parsed['query'])) {
                $path .= '?'.$parsed['query'];
            }

            return [$configuredUrl, $parsed['host'], $serverPort, $path];
        }

        $appUrl = parse_url((string) config('app.url'));
        $scheme = $appUrl['scheme'] ?? 'http';
        $host = (string) (config('hikvision.webhook_host') ?: ($appUrl['host'] ?? '127.0.0.1'));
        $serverPort = (int) (config('hikvision.webhook_port') ?: ($appUrl['port'] ?? ($scheme === 'https' ? 443 : 80)));
        $path = (string) config('hikvision.webhook_path');
        $secret = (string) config('hikvision.webhook_secret');

        if ($secret !== '') {
            $path = rtrim($path, '/').'/'.rawurlencode($secret);
        }

        $webhookUrl = sprintf('%s://%s:%d%s', $scheme, $host, $serverPort, $path);

        return [$webhookUrl, $host, $serverPort, $path];
    }

    public function openDoor(Device $device): bool
    {
        $doorNo = (int) config('hikvision.door_no', 1);

        try {
            $response = $this->client->request(
                $device,
                'PUT',
                "/ISAPI/AccessControl/RemoteControl/door/{$doorNo}",
                ['format' => 'json'],
                [
                    'RemoteControlDoor' => [
                        'cmd' => 'open',
                    ],
                ],
            );
        } catch (ConnectionException $exception) {
            throw HikvisionConnectionException::fromThrowable($device, $exception);
        }

        return $response->successful();
    }

    public function reboot(Device $device): bool
    {
        try {
            $response = $this->client->request(
                $device,
                'PUT',
                '/ISAPI/System/reboot',
            );
        } catch (ConnectionException $exception) {
            throw HikvisionConnectionException::fromThrowable($device, $exception);
        }

        if ($response->successful()) {
            $device->forceFill(['is_online' => false])->save();
        }

        return $response->successful();
    }

    private function resolveWorkingConnection(Device $device): Device
    {
        $lastException = null;

        foreach ($this->connectionCandidates($device) as $candidate) {
            try {
                $response = $this->client->request(
                    $candidate,
                    'GET',
                    '/ISAPI/System/deviceInfo',
                    ['format' => 'json'],
                );

                if ($response->successful()) {
                    Log::info('Hikvision: ishchi ulanish topildi', [
                        'device_id' => $device->id,
                        'url' => $candidate->isapiBaseUrl(),
                    ]);

                    return $candidate;
                }
            } catch (ConnectionException $exception) {
                $lastException = $exception;
                Log::debug('Hikvision: ulanish sinovi muvaffaqiyatsiz', [
                    'device_id' => $device->id,
                    'url' => $candidate->isapiBaseUrl(),
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        if ($lastException !== null) {
            throw HikvisionConnectionException::fromThrowable($device, $lastException);
        }

        throw new HikvisionConnectionException(
            'Qurilma javob bermadi. Port 80 (HTTP) yoki 443 (HTTPS) ni sinab ko‘ring. ['.$device->isapiBaseUrl().']',
            $device,
        );
    }

    /**
     * @return list<Device>
     */
    private function connectionCandidates(Device $device): array
    {
        $combos = [
            ['use_https' => (bool) $device->use_https, 'port' => (int) $device->port],
            ['use_https' => false, 'port' => 80],
            ['use_https' => true, 'port' => 443],
            ['use_https' => false, 'port' => (int) $device->port === 8000 ? 8000 : (int) $device->port],
            ['use_https' => true, 'port' => (int) $device->port],
        ];

        if ((int) $device->port !== 80) {
            $combos[] = ['use_https' => false, 'port' => 80];
        }

        if ((int) $device->port !== 443) {
            $combos[] = ['use_https' => true, 'port' => 443];
        }

        $unique = [];
        $candidates = [];

        foreach ($combos as $combo) {
            $key = ($combo['use_https'] ? '1' : '0').':'.$combo['port'];

            if (isset($unique[$key])) {
                continue;
            }

            $unique[$key] = true;
            $candidates[] = $this->cloneForProbe($device, $combo['use_https'], $combo['port']);
        }

        return $candidates;
    }

    private function cloneForProbe(Device $device, bool $useHttps, int $port): Device
    {
        $clone = $device->replicate();
        $clone->applyIsapiConnectionSettings($useHttps, $port);

        return $clone;
    }

    private function persistConnectionSettings(Device $device, Device $resolved): void
    {
        $settings = $resolved->isapiConnectionSettings();
        $current = $device->isapiConnectionSettings();

        if ($settings === $current) {
            return;
        }

        $device->applyIsapiConnectionSettings($settings['use_https'], $settings['port']);
        $device->save();

        Log::info('Hikvision: qurilma ulanish sozlamalari yangilandi', [
            'device_id' => $device->id,
            'use_https' => $settings['use_https'],
            'port' => $settings['port'],
            'url' => $device->isapiBaseUrl(),
        ]);
    }
}
