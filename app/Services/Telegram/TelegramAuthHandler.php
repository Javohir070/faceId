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
        private readonly TelegramReportService $reports,
    ) {}

    /**
     * @param  array<string, mixed>  $update
     */
    public function handleUpdate(array $update): void
    {
        if (isset($update['callback_query']) && is_array($update['callback_query'])) {
            $this->handleCallbackQuery($update['callback_query']);

            return;
        }

        $message = $update['message'] ?? null;

        if (is_array($message)) {
            $this->handleMessage($message);
        }
    }

    /**
     * @param  array<string, mixed>  $message
     */
    public function handle(array $message): void
    {
        $this->handleMessage($message);
    }

    /**
     * @param  array<string, mixed>  $callback
     */
    private function handleCallbackQuery(array $callback): void
    {
        $chatId = (string) data_get($callback, 'message.chat.id', '');
        $data = trim((string) ($callback['data'] ?? ''));
        $callbackId = (string) ($callback['id'] ?? '');

        if ($chatId === '' || $data === '') {
            return;
        }

        $this->bot->answerCallbackQuery($callbackId);

        $linked = User::query()->where('telegram_chat_id', $chatId)->first();

        if ($linked === null) {
            $this->bot->sendMessage($chatId, 'Avval /start orqali ulaning.');

            return;
        }

        if (str_starts_with($data, 'pref:')) {
            $this->applyPref($chatId, $linked, substr($data, 5));

            return;
        }

        if ($data === 'menu:main') {
            $this->sendMainMenu($chatId, $linked);

            return;
        }

        if (! str_starts_with($data, 'cmd:')) {
            return;
        }

        $command = '/'.substr($data, 4);
        $this->dispatchCommand($chatId, $linked, $command);
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function handleMessage(array $message): void
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

        $linked = User::query()->where('telegram_chat_id', $chatId)->first();

        if ($command === '/unlink') {
            $this->handleUnlink($chatId);

            return;
        }

        if ($linked !== null && in_array($command, ['/status', '/devices', '/expiry', '/settings', '/help', '/menu'], true)) {
            $this->dispatchCommand($chatId, $linked, $command === '/menu' ? '/help' : $command);

            return;
        }

        $state = $this->getState($chatId);

        if ($state !== null && ($state['step'] ?? null) === 'awaiting_settings' && $linked !== null) {
            $this->handleSettingsChoice($chatId, $linked, $text);

            return;
        }

        if ($state === null) {
            if ($linked !== null) {
                $this->sendMainMenu($chatId, $linked);
            } else {
                $this->bot->sendMessage($chatId, 'Boshlash uchun /start yuboring.');
            }

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

    private function dispatchCommand(string $chatId, User $user, string $command): void
    {
        if ($command === '/unlink') {
            $this->handleUnlink($chatId);

            return;
        }

        if (! $user->hasRole('super-admin')) {
            $this->bot->sendMessage($chatId, 'Bu buyruqlar faqat super-admin uchun.');

            return;
        }

        match ($command) {
            '/status' => $this->bot->sendMessage($chatId, $this->reports->statusText(), TelegramKeyboard::mainMenu()),
            '/devices' => $this->bot->sendMessage($chatId, $this->reports->offlineDevicesText(), TelegramKeyboard::mainMenu()),
            '/expiry' => $this->bot->sendMessage($chatId, $this->reports->expiryText(), TelegramKeyboard::mainMenu()),
            '/settings' => $this->handleSettingsPrompt($chatId),
            '/help' => $this->sendMainMenu($chatId, $user),
            default => $this->sendMainMenu($chatId, $user),
        };
    }

    private function sendMainMenu(string $chatId, User $user): void
    {
        if (! $user->hasRole('super-admin')) {
            $this->bot->sendMessage($chatId, "Ulangan: {$user->email}\nChiqish: /unlink");

            return;
        }

        $this->bot->sendMessage(
            $chatId,
            "Ulangan: {$user->email}\nKerakli bo‘limni tanlang:",
            TelegramKeyboard::mainMenu(),
        );
    }

    private function handleStart(string $chatId): void
    {
        $user = User::query()->where('telegram_chat_id', $chatId)->first();

        if ($user !== null) {
            $this->forgetState($chatId);
            $this->sendMainMenu($chatId, $user);

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
        $this->forgetState($chatId);
        $this->bot->sendMessage(
            $chatId,
            'Xabar filtrini tanlang:',
            TelegramKeyboard::settingsMenu(),
        );
    }

    private function applyPref(string $chatId, User $user, string $choice): void
    {
        if (! in_array($choice, ['all', 'device', 'expiry', 'none'], true)) {
            $this->bot->sendMessage($chatId, 'Noto‘g‘ri tanlov.', TelegramKeyboard::settingsMenu());

            return;
        }

        if (! $user->hasRole('super-admin')) {
            $this->bot->sendMessage($chatId, 'Bu buyruqlar faqat super-admin uchun.');

            return;
        }

        $user->forceFill(['telegram_notify_prefs' => $choice])->save();
        $this->bot->sendMessage(
            $chatId,
            "Xabar filtri: {$choice}",
            TelegramKeyboard::mainMenu(),
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
            $this->bot->sendMessage($chatId, 'Noto‘g‘ri tanlov.', TelegramKeyboard::settingsMenu());

            return;
        }

        $this->applyPref($chatId, $user, $choice);
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
        $this->sendMainMenu($chatId, $user->fresh() ?? $user);
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
