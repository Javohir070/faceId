<?php

namespace App\Services\Telegram;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

class TelegramAuthHandler
{
    private const CACHE_TTL_MINUTES = 15;

    private const HELP_LINKED = "Buyruqlar:\n/status — holat\n/devices — oflayn qurilmalar\n/expiry — muddatlar\n/settings — xabar filtri\n/unlink — uzish";

    public function __construct(
        private readonly TelegramBotService $bot,
        private readonly TelegramReportService $reports,
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

        $command = strtolower(explode(' ', $text, 2)[0]);
        $command = explode('@', $command)[0];

        if ($command === '/start') {
            $this->handleStart($chatId);

            return;
        }

        if ($command === '/unlink') {
            $this->handleUnlink($chatId);

            return;
        }

        $linked = User::query()->where('telegram_chat_id', $chatId)->first();

        if ($linked !== null && in_array($command, ['/status', '/devices', '/expiry', '/settings', '/help'], true)) {
            if (! $linked->hasRole('super-admin')) {
                $this->bot->sendMessage($chatId, 'Bu buyruqlar faqat super-admin uchun.');

                return;
            }

            match ($command) {
                '/status' => $this->bot->sendMessage($chatId, $this->reports->statusText()),
                '/devices' => $this->bot->sendMessage($chatId, $this->reports->offlineDevicesText()),
                '/expiry' => $this->bot->sendMessage($chatId, $this->reports->expiryText()),
                '/settings' => $this->handleSettingsPrompt($chatId),
                '/help' => $this->bot->sendMessage($chatId, self::HELP_LINKED),
            };

            return;
        }

        $state = $this->getState($chatId);

        if ($state !== null && ($state['step'] ?? null) === 'awaiting_settings' && $linked !== null) {
            $this->handleSettingsChoice($chatId, $linked, $text);

            return;
        }

        if ($state === null) {
            $this->bot->sendMessage(
                $chatId,
                $linked !== null
                    ? self::HELP_LINKED
                    : 'Boshlash uchun /start yuboring.'
            );

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
            $help = $user->hasRole('super-admin')
                ? "\n\n".self::HELP_LINKED
                : "\nChiqish: /unlink";
            $this->bot->sendMessage($chatId, "Ulangan: {$user->email}.{$help}");

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

    private function handleSettingsPrompt(string $chatId): void
    {
        $this->putState($chatId, ['step' => 'awaiting_settings']);
        $this->bot->sendMessage(
            $chatId,
            "Xabar filtri (raqam yoki kod yuboring):\n1) all — hammasi\n2) device — faqat qurilma\n3) expiry — faqat muddat\n4) none — o‘chirish"
        );
    }

    private function handleSettingsChoice(string $chatId, User $user, string $text): void
    {
        $map = [
            '1' => 'all',
            'all' => 'all',
            '2' => 'device',
            'device' => 'device',
            '3' => 'expiry',
            'expiry' => 'expiry',
            '4' => 'none',
            'none' => 'none',
        ];

        $choice = $map[mb_strtolower(trim($text))] ?? null;

        if ($choice === null) {
            $this->bot->sendMessage($chatId, 'Noto‘g‘ri tanlov. 1–4 yoki all/device/expiry/none yuboring.');

            return;
        }

        $user->forceFill(['telegram_notify_prefs' => $choice])->save();
        $this->forgetState($chatId);
        $this->bot->sendMessage($chatId, "Xabar filtri: {$choice}");
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

        $suffix = $user->hasRole('super-admin')
            ? "\n\n".self::HELP_LINKED
            : '';

        $this->bot->sendMessage($chatId, 'Muvaffaqiyatli ulandingiz.'.$suffix);
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
