<?php

namespace App\Services\Telegram;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramBotService
{
    public ?string $lastError = null;

    public function sendMessage(string $chatId, string $text): bool
    {
        $this->lastError = null;
        $token = (string) config('telegram.token');

        if ($token === '') {
            $this->lastError = 'TELEGRAM_BOT_TOKEN bo\'sh';
            Log::warning('Telegram: token bo\'sh, xabar yuborilmadi');

            return false;
        }

        try {
            $response = $this->http()
                ->post("https://api.telegram.org/bot{$token}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $text,
                ]);

            if (! $response->successful() || ! ($response->json('ok') ?? false)) {
                $this->lastError = $response->body();
                Log::warning('Telegram: sendMessage muvaffaqiyatsiz', [
                    'chat_id' => $chatId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            Log::warning('Telegram: sendMessage xato', [
                'chat_id' => $chatId,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function setWebhook(string $url, string $secretToken): bool
    {
        $this->lastError = null;
        $token = (string) config('telegram.token');

        if ($token === '') {
            $this->lastError = 'TELEGRAM_BOT_TOKEN bo\'sh';

            return false;
        }

        $payload = ['url' => $url];

        if ($secretToken !== '') {
            $payload['secret_token'] = $secretToken;
        }

        try {
            $response = $this->http()
                ->post("https://api.telegram.org/bot{$token}/setWebhook", $payload);

            if ($response->successful() && ($response->json('ok') ?? false)) {
                return true;
            }

            $this->lastError = $response->json('description') ?? $response->body();

            return false;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            Log::warning('Telegram: setWebhook xato', [
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function http(): PendingRequest
    {
        $request = Http::timeout(15);

        if (! (bool) config('telegram.http_verify', true)) {
            $request = $request->withOptions(['verify' => false]);
        }

        return $request;
    }
}
