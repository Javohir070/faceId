<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Services\Hikvision\HikvisionEventParser;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class WebhookController extends Controller
{
    public function __construct(
        private readonly ReportService $reportService,
        private readonly HikvisionEventParser $eventParser,
    ) {}

    public function hikvision(Request $request): JsonResponse
    {
        $this->assertWebhookSecret($request);

        try {
            $event = $this->resolveEvent($request);

            if ($event === null) {
                Log::warning('Hikvision webhook: event ma’lumoti topilmadi', [
                    'content_type' => $request->header('Content-Type'),
                    'keys' => array_keys($request->all()),
                    'ip' => $request->ip(),
                ]);

                return response()->json([
                    'message' => 'event_log yoki AccessControllerEvent maydoni talab qilinadi.',
                ], 422);
            }

            $request->validate([
                'Picture' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            ]);

            $picturePath = null;

            if ($request->hasFile('Picture')) {
                $picturePath = $request->file('Picture')->store('hikvision', 'public');
            }

            $this->reportService->createFromWebhookPayload($event, $picturePath);

            return response()->json([
                'status' => 'success',
            ]);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::error('Hikvision webhook xatosi: '.$exception->getMessage(), [
                'exception' => $exception::class,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Server Error',
            ], 500);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveEvent(Request $request): ?array
    {
        foreach (['event_log', 'AccessControllerEvent'] as $field) {
            $raw = $request->input($field);

            if (is_array($raw)) {
                return $this->eventParser->normalizePayload($raw, $request->ip());
            }

            if (! is_string($raw) || trim($raw) === '') {
                continue;
            }

            $parsed = $this->eventParser->parseRaw(trim($raw), $request->ip());

            if ($parsed !== null) {
                return $parsed;
            }
        }

        $rawBody = trim($request->getContent());

        if ($rawBody === '') {
            return null;
        }

        if (str_starts_with($rawBody, '{') || str_starts_with($rawBody, '[')) {
            $decoded = json_decode($rawBody, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                if (isset($decoded['event_log']) || isset($decoded['AccessControllerEvent'])) {
                    return null;
                }

                return $this->eventParser->normalizePayload($decoded, $request->ip());
            }
        }

        return $this->eventParser->parseRaw($rawBody, $request->ip());
    }

    private function assertWebhookSecret(Request $request): void
    {
        if ($this->isTrustedDevice($request)) {
            return;
        }

        $secret = (string) config('hikvision.webhook_secret');

        if ($secret === '') {
            Log::warning('Hikvision webhook: secret sozlanmagan va so‘rov ishonchli qurilma IP dan emas.', [
                'ip' => $request->ip(),
                'trusted_device_ips' => Device::trustedIpAddresses(),
            ]);

            abort(503, 'Webhook secret sozlanmagan.');
        }

        $provided = (string) (
            $request->route('secret')
            ?? $request->header('X-Webhook-Secret')
            ?? $request->query('secret')
            ?? ''
        );

        if ($provided !== '' && hash_equals($secret, $provided)) {
            return;
        }

        Log::warning('Hikvision webhook: noto‘g‘ri secret', array_merge([
            'has_header' => $request->hasHeader('X-Webhook-Secret'),
            'has_query_secret' => $request->has('secret'),
            'has_path_secret' => $request->route('secret') !== null,
            'ip' => $request->ip(),
            'trusted_device_ips' => Device::trustedIpAddresses(),
        ], $this->webhookRequestDiagnostics($request)));

        abort(401, 'Unauthorized webhook.');
    }

    /**
     * @return array<string, mixed>
     */
    private function webhookRequestDiagnostics(Request $request): array
    {
        $rawBody = $request->getContent();
        $bodySize = strlen($rawBody);
        $contentType = (string) $request->header('Content-Type', '');

        return [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'user_agent' => $request->userAgent(),
            'content_type' => $contentType !== '' ? $contentType : null,
            'content_length_header' => $request->header('Content-Length'),
            'body_size' => $bodySize,
            'input_keys' => array_keys($request->all()),
            'forwarded_for' => $request->header('X-Forwarded-For'),
            'real_ip' => $request->header('X-Real-IP'),
            'body_preview' => $this->webhookBodyPreview($rawBody, $contentType),
        ];
    }

    private function webhookBodyPreview(string $rawBody, string $contentType): ?string
    {
        if ($rawBody === '') {
            return null;
        }

        if (str_contains(strtolower($contentType), 'multipart/')) {
            return '[multipart payload, preview skipped]';
        }

        if (! mb_check_encoding($rawBody, 'UTF-8')) {
            return '[binary payload, preview skipped]';
        }

        $preview = preg_replace('/\s+/', ' ', trim($rawBody)) ?? '';

        if ($preview === '') {
            return null;
        }

        return mb_strlen($preview) > 300 ? mb_substr($preview, 0, 300).'…' : $preview;
    }

    private function isTrustedDevice(Request $request): bool
    {
        if (! config('hikvision.webhook_trust_device_ip', true)) {
            return false;
        }

        $requestIp = $request->ip();

        if ($requestIp === null) {
            return false;
        }

        $trustedHosts = Device::trustedIpAddresses();

        return in_array($requestIp, $trustedHosts, true);
    }
}
