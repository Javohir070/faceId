<?php

namespace App\Services\Telegram;

use App\Models\TelegramGroup;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class TelegramAuthHandler
{
    private const CACHE_TTL_MINUTES = 15;

    /** Shuncha noto‘g‘ri paroldan keyin chat ham, email ham bloklanadi. */
    private const MAX_PASSWORD_ATTEMPTS = 5;

    private const LOCK_SECONDS = 15 * 60;

    public function __construct(
        private readonly TelegramBotService $bot,
        private readonly TelegramReportService $reports,
    ) {}

    /**
     * @param  array<string, mixed>  $update
     */
    public function handleUpdate(array $update): void
    {
        // Bot guruhdan chiqarilsa, guruh unutiladi.
        if (isset($update['my_chat_member']) && is_array($update['my_chat_member'])) {
            $status = data_get($update, 'my_chat_member.new_chat_member.status');

            if (in_array($status, ['left', 'kicked'], true)) {
                TelegramGroup::query()->where('chat_id', (string) data_get($update, 'my_chat_member.chat.id'))->delete();
            }

            return;
        }

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

        // Guruhda email va parol so‘ralmaydi, oddiy suhbatga esa bot javob bermaydi.
        if (data_get($message, 'chat.type', 'private') !== 'private') {
            match ($command) {
                '/start' => $this->bot->sendMessage(
                    $chatId,
                    'Ulanish faqat bot bilan shaxsiy chatda: botga alohida yozing. Keyin shu guruhda /ulash yozsangiz, qurilma xabarlari shu yerga keladi.',
                ),
                '/ulash' => $this->bindGroup($chatId, $message),
                '/uzish' => $this->unbindGroup($chatId, $message),
                default => null,
            };

            return;
        }

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
            $this->handlePassword($chatId, $text, (string) ($state['email'] ?? ''), (int) ($message['message_id'] ?? 0));
        }
    }

    private function dispatchCommand(string $chatId, User $user, string $command): void
    {
        if ($command === '/unlink') {
            $this->handleUnlink($chatId);

            return;
        }

        // Super-admin hammasini, boshqa foydalanuvchi faqat o‘z bo‘limi va o‘z qurilmalarini ko‘radi.
        $scoped = fn (\Closure $text): string => TelegramNotifier::asUser($user, $text);

        match ($command) {
            '/status' => $this->bot->sendMessage($chatId, $scoped(fn () => $this->reports->statusText()), TelegramKeyboard::mainMenu()),
            '/devices' => $this->bot->sendMessage($chatId, $scoped(fn () => $this->reports->offlineDevicesText()), TelegramKeyboard::mainMenu()),
            '/expiry' => $this->bot->sendMessage($chatId, $scoped(fn () => $this->reports->expiryText()), TelegramKeyboard::mainMenu()),
            '/settings' => $this->handleSettingsPrompt($chatId),
            '/help' => $this->sendMainMenu($chatId, $user),
            default => $this->sendMainMenu($chatId, $user),
        };
    }

    private function sendMainMenu(string $chatId, User $user): void
    {
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

        if ($this->rejectIfLocked($chatId, $this->chatThrottleKey($chatId))) {
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
        $this->bot->sendMessage($chatId, 'Parol kiriting. Xavfsizlik uchun xabaringiz darhol o‘chiriladi.');
    }

    private function handlePassword(string $chatId, string $password, string $email, int $messageId): void
    {
        // Parol chat tarixida qolmasligi uchun natijadan qat’i nazar o‘chiriladi.
        $this->bot->deleteMessage($chatId, $messageId);

        $chatKey = $this->chatThrottleKey($chatId);
        $emailKey = 'telegram-login:email:'.$email;

        if ($this->rejectIfLocked($chatId, $chatKey) || $this->rejectIfLocked($chatId, $emailKey)) {
            return;
        }

        $user = User::query()->where('email', $email)->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            RateLimiter::hit($chatKey, self::LOCK_SECONDS);
            RateLimiter::hit($emailKey, self::LOCK_SECONDS);
            $this->forgetState($chatId);
            $this->bot->sendMessage($chatId, 'Email yoki parol noto\'g\'ri.');

            return;
        }

        RateLimiter::clear($chatKey);
        RateLimiter::clear($emailKey);

        User::query()
            ->where('telegram_chat_id', $chatId)
            ->where('id', '!=', $user->id)
            ->update(['telegram_chat_id' => null]);

        $user->forceFill(['telegram_chat_id' => $chatId])->save();
        $this->forgetState($chatId);
        $this->sendMainMenu($chatId, $user->fresh() ?? $user);
    }

    /**
     * Guruhni shaxsiy chatda ulangan foydalanuvchi bog‘laydi, parol guruhda so‘ralmaydi.
     * Shaxsiy chat id Telegram foydalanuvchi id'si bilan bir xil, shuning uchun from.id bo‘yicha topiladi.
     *
     * @param  array<string, mixed>  $message
     */
    private function bindGroup(string $chatId, array $message): void
    {
        $user = $this->linkedSender($message);

        if ($user === null) {
            $this->bot->sendMessage($chatId, 'Avval bot bilan shaxsiy chatda ulaning (/start), keyin shu guruhda /ulash yozing.');

            return;
        }

        TelegramGroup::query()->updateOrCreate(
            ['chat_id' => $chatId],
            ['user_id' => $user->id, 'title' => data_get($message, 'chat.title')],
        );

        $this->bot->sendMessage($chatId, sprintf(
            "Guruh ulandi. Endi %s uzilsa yoki qayta ulansa, shu yerga xabar keladi.\nUzish: /uzish",
            $user->isDepartmentScoped() ? 'sizga biriktirilgan qurilmalar' : 'barcha qurilmalar',
        ));
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function unbindGroup(string $chatId, array $message): void
    {
        $group = TelegramGroup::query()->where('chat_id', $chatId)->first();

        if ($group === null) {
            $this->bot->sendMessage($chatId, 'Bu guruh ulanmagan.');

            return;
        }

        $user = $this->linkedSender($message);

        if ($user === null || ($user->id !== $group->user_id && $user->isDepartmentScoped())) {
            $this->bot->sendMessage($chatId, 'Guruhni faqat uni ulagan foydalanuvchi yoki super-admin uza oladi.');

            return;
        }

        $group->delete();
        $this->bot->sendMessage($chatId, 'Guruh uzildi: endi bu yerga xabar kelmaydi.');
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function linkedSender(array $message): ?User
    {
        $fromId = (string) data_get($message, 'from.id', '');

        return $fromId === '' ? null : User::query()->where('telegram_chat_id', $fromId)->first();
    }

    private function chatThrottleKey(string $chatId): string
    {
        return 'telegram-login:chat:'.$chatId;
    }

    private function rejectIfLocked(string $chatId, string $key): bool
    {
        if (! RateLimiter::tooManyAttempts($key, self::MAX_PASSWORD_ATTEMPTS)) {
            return false;
        }

        $this->forgetState($chatId);
        $this->bot->sendMessage($chatId, sprintf(
            'Juda ko‘p noto‘g‘ri urinish. %d daqiqadan keyin qayta urinib ko‘ring.',
            max((int) ceil(RateLimiter::availableIn($key) / 60), 1),
        ));

        return true;
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
