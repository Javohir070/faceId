<?php

namespace App\Services\Hikvision;

use App\Exceptions\HikvisionConnectionException;
use App\Models\Device;
use App\Services\Telegram\TelegramNotifier;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
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
        $webhookConfigured = $this->setupWebhook($resolved);

        Cache::forget($this->pingFailureCacheKey($device));

        $device->forceFill([
            'is_online' => true,
            'last_connected_at' => now(),
            'last_ping_at' => now(),
            'webhook_configured' => $webhookConfigured,
        ])->save();

        Log::info('Hikvision: qurilma ulandi', [
            'device_id' => $device->id,
            'url' => $device->isapiBaseUrl(),
            'webhook_configured' => $webhookConfigured,
        ]);

        if (! $webhookConfigured) {
            Log::warning('Hikvision: qurilma ulandi, lekin webhook sozlanmadi', [
                'device_id' => $device->id,
            ]);
        }

        return true;
    }

    /**
     * @param  bool  $probeAlternatives  true = qo‘lda tekshiruv (port/HTTPS qidiradi);
     *                                   false = scheduler (faqat saqlangan sozlamalar).
     */
    public function refreshOnlineStatus(Device $device, bool $probeAlternatives = false): bool
    {
        try {
            $resolved = $this->pingDevice($device, $probeAlternatives);

            if ($probeAlternatives) {
                $this->persistConnectionSettings($device, $resolved);
            }

            Cache::forget($this->pingFailureCacheKey($device));

            $device->forceFill([
                'is_online' => true,
                'last_connected_at' => now(),
                'last_ping_at' => now(),
            ])->save();

            return true;
        } catch (\Throwable $exception) {
            $wasOnline = (bool) $device->is_online;
            $failures = $this->incrementPingFailures($device);
            $threshold = max((int) config('hikvision.ping_offline_after_failures', 5), 1);

            $updates = [
                'last_ping_at' => now(),
            ];

            if ($failures >= $threshold) {
                $updates['is_online'] = false;
            }

            $device->forceFill($updates)->save();

            if ($wasOnline && ($updates['is_online'] ?? true) === false) {
                app(TelegramNotifier::class)->notifyAllLinkedUsers(
                    sprintf(
                        "⚠️ Qurilma ishlamayapti (oflayn)\nNomi: %s\nIP: %s\nTur: %s",
                        $device->name,
                        $device->ip_address,
                        $device->typeLabel(),
                    )
                );
            }

            Log::warning('Hikvision: qurilma ping muvaffaqiyatsiz', [
                'device_id' => $device->id,
                'url' => $device->isapiBaseUrl(),
                'failures' => $failures,
                'threshold' => $threshold,
                'probe' => $probeAlternatives,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function pingDevice(Device $device, bool $probeAlternatives = false): Device
    {
        try {
            $response = $this->client->request(
                $device,
                'GET',
                '/ISAPI/System/deviceInfo',
                ['format' => 'json'],
            );

            if ($response->successful()) {
                return $device;
            }

            if (! $probeAlternatives) {
                throw new HikvisionConnectionException(
                    'Qurilma javob bermadi: HTTP '.$response->status().' ['.$device->isapiBaseUrl().']',
                    $device,
                );
            }
        } catch (ConnectionException $exception) {
            if (! $probeAlternatives) {
                throw HikvisionConnectionException::fromThrowable($device, $exception);
            }
        }

        return $this->resolveWorkingConnection($device);
    }

    private function incrementPingFailures(Device $device): int
    {
        $key = $this->pingFailureCacheKey($device);
        $failures = (int) Cache::get($key, 0) + 1;

        Cache::put($key, $failures, now()->addHours(6));

        return $failures;
    }

    private function pingFailureCacheKey(Device $device): string
    {
        return 'hikvision.device.'.$device->id.'.ping_failures';
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

        if ($this->setupWebhookWithXml($device, $host, $serverPort, $path, $webhookUrl)) {
            return true;
        }

        return $this->setupWebhookWithJson($device, $host, $serverPort, $path, $webhookUrl);
    }

    private function setupWebhookWithXml(
        Device $device,
        string $host,
        int $serverPort,
        string $path,
        string $webhookUrl,
    ): bool {
        try {
            $response = $this->client->requestWithBody(
                $device,
                'PUT',
                '/ISAPI/Event/notification/httpHosts/1',
                [],
                $this->buildWebhookXml($host, $serverPort, $path),
            );
        } catch (ConnectionException $exception) {
            throw HikvisionConnectionException::fromThrowable($device, $exception);
        }

        if ($response->failed()) {
            Log::warning('Hikvision: webhook XML sozlash muvaffaqiyatsiz', [
                'device_id' => $device->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        }

        Log::info('Hikvision: webhook sozlandi (XML)', [
            'device_id' => $device->id,
            'url' => $webhookUrl,
        ]);

        return true;
    }

    private function setupWebhookWithJson(
        Device $device,
        string $host,
        int $serverPort,
        string $path,
        string $webhookUrl,
    ): bool {
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

        Log::info('Hikvision: webhook sozlandi (JSON)', [
            'device_id' => $device->id,
            'url' => $webhookUrl,
        ]);

        return true;
    }

    private function buildWebhookXml(string $host, int $serverPort, string $path): string
    {
        $escapedPath = htmlspecialchars($path, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $escapedHost = htmlspecialchars($host, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<HttpHostNotification version="2.0" xmlns="http://www.isapi.org/ver20/XMLSchema">'
            .'<id>1</id>'
            .'<url>'.$escapedPath.'</url>'
            .'<protocolType>HTTP</protocolType>'
            .'<parameterFormatType>JSON</parameterFormatType>'
            .'<addressingFormatType>ipaddress</addressingFormatType>'
            .'<ipAddress>'.$escapedHost.'</ipAddress>'
            .'<portNo>'.$serverPort.'</portNo>'
            .'<httpAuthenticationMethod>none</httpAuthenticationMethod>'
            .'<httpMethod>POST</httpMethod>'
            .'</HttpHostNotification>';
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
        $preferredException = null;
        $lastException = null;
        $index = 0;

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
                if ($index === 0) {
                    $preferredException = $exception;
                }

                $lastException = $exception;
                Log::debug('Hikvision: ulanish sinovi muvaffaqiyatsiz', [
                    'device_id' => $device->id,
                    'url' => $candidate->isapiBaseUrl(),
                    'message' => $exception->getMessage(),
                ]);
            }

            $index++;
        }

        // Oxirgi HTTPS probe SSL xatosi berishi mumkin — asosiy sozlama xatosini afzal ko‘ramiz.
        $exception = $preferredException ?? $lastException;

        if ($exception !== null) {
            throw HikvisionConnectionException::fromThrowable($device, $exception);
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
        $port = (int) $device->port;

        // Saqlangan sozlama birinchi; keyin standart HTTP/HTTPS.
        // https://IP:80 kabi nonsens kombinatsiyalar sinanmaydi (SSL xato xabarini chalg‘itadi).
        $combos = [
            ['use_https' => (bool) $device->use_https, 'port' => $port],
            ['use_https' => false, 'port' => 80],
            ['use_https' => true, 'port' => 443],
        ];

        if ($port !== 80 && $port !== 443) {
            $combos[] = ['use_https' => false, 'port' => $port];
            $combos[] = ['use_https' => true, 'port' => $port];
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
        $clone->id = $device->id;
        $clone->exists = true;
        $clone->applyIsapiConnectionSettings($useHttps, $port);

        // Encrypted cast: probe uchun ochiq parolni saqlab qo'yamiz.
        $clone->password = $device->password;

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
