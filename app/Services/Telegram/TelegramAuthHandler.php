<?php

namespace App\Services\Telegram;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

class TelegramAuthHandler
{
    private const CACHE_TTL_MINUTES = 15;

    public function __construct(
        private readonly TelegramBotService $bot,
    ) {}

    /**
     * @param  array<string, mixed>  $message
     */
    public function handle(array $message): void
    {
        $chatId = (string) data_get($message, 'chat.id', '');
        $text = trim((string) ($message['text'] ?? ''));

        if ($chatId === '' || $text === '') {
            return;
        }

        if ($text === '/start' || str_starts_with($text, '/start ')) {
            $this->handleStart($chatId);

            return;
        }

        if ($text === '/unlink') {
            $this->handleUnlink($chatId);

            return;
        }

        $state = $this->getState($chatId);

        if ($state === null) {
            $this->bot->sendMessage($chatId, 'Boshlash uchun /start yuboring.');

            return;
        }

        if (($state['step'] ?? null) === 'awaiting_email') {
            $this->handleEmail($chatId, $text);

            return;
        }

        if (($state['step'] ?? null) === 'awaiting_password') {
            $this->handlePassword($chatId, $text, (string) ($state['email'] ?? ''));
        }
    }

    private function handleStart(string $chatId): void
    {
        $user = User::query()->where('telegram_chat_id', $chatId)->first();

        if ($user !== null) {
            $this->forgetState($chatId);
            $this->bot->sendMessage(
                $chatId,
                "Ulangan: {$user->email}. Chiqish: /unlink"
            );

            return;
        }

        $this->putState($chatId, ['step' => 'awaiting_email']);
        $this->bot->sendMessage($chatId, 'Email kiriting.');
    }

    private function handleUnlink(string $chatId): void
    {
        User::query()
            ->where('telegram_chat_id', $chatId)
            ->update(['telegram_chat_id' => null]);

        $this->forgetState($chatId);
        $this->bot->sendMessage($chatId, 'Telegram ulanish uzildi.');
    }

    private function handleEmail(string $chatId, string $email): void
    {
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->bot->sendMessage($chatId, 'Email noto\'g\'ri. Qayta kiriting.');

            return;
        }

        $this->putState($chatId, [
            'step' => 'awaiting_password',
            'email' => mb_strtolower($email),
        ]);
        $this->bot->sendMessage($chatId, 'Parol kiriting.');
    }

    private function handlePassword(string $chatId, string $password, string $email): void
    {
        $user = User::query()->where('email', $email)->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            $this->forgetState($chatId);
            $this->bot->sendMessage($chatId, 'Email yoki parol noto\'g\'ri.');

            return;
        }

        User::query()
            ->where('telegram_chat_id', $chatId)
            ->where('id', '!=', $user->id)
            ->update(['telegram_chat_id' => null]);

        $user->forceFill(['telegram_chat_id' => $chatId])->save();
        $this->forgetState($chatId);
        $this->bot->sendMessage($chatId, 'Muvaffaqiyatli ulandingiz.');
    }

    /**
     * @return array{step?: string, email?: string}|null
     */
    private function getState(string $chatId): ?array
    {
        $state = Cache::get($this->cacheKey($chatId));

        return is_array($state) ? $state : null;
    }

    /**
     * @param  array{step: string, email?: string}  $state
     */
    private function putState(string $chatId, array $state): void
    {
        Cache::put($this->cacheKey($chatId), $state, now()->addMinutes(self::CACHE_TTL_MINUTES));
    }

    private function forgetState(string $chatId): void
    {
        Cache::forget($this->cacheKey($chatId));
    }

    private function cacheKey(string $chatId): string
    {
        return 'telegram:auth:'.$chatId;
    }
}
