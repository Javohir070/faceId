<?php

namespace App\Services\Hikvision;

use App\Models\Device;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IsapiClient
{
    public function forDevice(Device $device): PendingRequest
    {
        $options = [
            'auth' => [$device->user, $device->password, 'digest'],
            'connect_timeout' => config('hikvision.connect_timeout', 5),
            'timeout' => config('hikvision.timeout', 10),
        ];

        if ($device->use_https) {
            $options['verify'] = (bool) config('hikvision.verify_ssl', false);
        }

        return Http::withOptions($options)->acceptJson();
    }

    public function baseUrl(Device $device): string
    {
        return $device->isapiBaseUrl();
    }

    public function url(Device $device, string $path, array $query = []): string
    {
        $url = rtrim($this->baseUrl($device), '/').'/'.ltrim($path, '/');

        if ($query !== []) {
            $url .= '?'.http_build_query($query);
        }

        return $url;
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $json
     * @param  array<string, string>  $headers
     */
    public function request(
        Device $device,
        string $method,
        string $path,
        array $query = [],
        array $json = [],
        array $headers = [],
    ): Response {
        $url = $this->url($device, $path, $query);
        $client = $this->forDevice($device);

        if ($headers !== []) {
            $client = $client->withHeaders($headers);
        }

        $response = match (strtoupper($method)) {
            'GET' => $client->get($url),
            'POST' => $client->post($url, $json),
            'PUT' => $client->put($url, $json),
            'DELETE' => $client->delete($url, $json),
            default => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}"),
        };

        $this->logRequest($device, strtoupper($method), $url, $response);

        return $response;
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, string>  $headers
     */
    public function requestWithBody(
        Device $device,
        string $method,
        string $path,
        array $query = [],
        string $body = '',
        string $contentType = 'application/xml',
        array $headers = [],
    ): Response {
        $url = $this->url($device, $path, $query);
        $client = $this->forDevice($device)->withHeaders(array_merge([
            'Content-Type' => $contentType,
        ], $headers));

        $response = match (strtoupper($method)) {
            'GET' => $client->withBody($body, $contentType)->get($url),
            'POST' => $client->withBody($body, $contentType)->post($url),
            'PUT' => $client->withBody($body, $contentType)->put($url),
            'DELETE' => $client->withBody($body, $contentType)->delete($url),
            default => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}"),
        };

        $this->logRequest($device, strtoupper($method), $url, $response);

        return $response;
    }

    private function logRequest(Device $device, string $method, string $url, Response $response): void
    {
        Log::debug('Hikvision ISAPI request', [
            'device_id' => $device->id,
            'method' => $method,
            'url' => $url,
            'status' => $response->status(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $faceDataRecord
     */
    public function uploadMultipart(
        Device $device,
        string $path,
        array $query,
        array $faceDataRecord,
        string $imageBytes,
        string $filename,
    ): Response {
        $url = $this->url($device, $path, $query);

        return $this->forDevice($device)
            ->attach('FaceDataRecord', json_encode($faceDataRecord, JSON_UNESCAPED_UNICODE), 'FaceDataRecord.json')
            ->attach('img', $imageBytes, $filename)
            ->put($url);
    }

    /**
     * Rasm yoki boshqa binary javob uchun (Accept: application/json majburiy emas).
     *
     * @param  array<string, string>  $headers
     */
    public function download(Device $device, string $absoluteUrl, array $headers = []): Response
    {
        $options = [
            'auth' => [$device->user, $device->password, 'digest'],
            'connect_timeout' => config('hikvision.connect_timeout', 5),
            'timeout' => config('hikvision.timeout', 10),
        ];

        if ($device->use_https) {
            $options['verify'] = (bool) config('hikvision.verify_ssl', false);
        }

        $client = Http::withOptions($options)->withHeaders(array_merge([
            'Accept' => '*/*',
        ], $headers));

        $response = $client->get($absoluteUrl);

        Log::debug('Hikvision ISAPI download', [
            'device_id' => $device->id,
            'url' => $absoluteUrl,
            'status' => $response->status(),
            'content_type' => $response->header('Content-Type'),
        ]);

        return $response;
    }
}
