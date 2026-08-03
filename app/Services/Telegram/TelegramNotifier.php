<?php

namespace App\Services\Telegram;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class TelegramNotifier
{
    public const CATEGORY_DEVICE = 'device';

    public const CATEGORY_EXPIRY = 'expiry';

    public const CATEGORY_DIGEST = 'digest';

    public function __construct(
        private readonly TelegramBotService $bot,
    ) {}

    /**
     * @param  self::CATEGORY_*|string  $category
     */
    public function notifyAllLinkedUsers(string $text, string $category = self::CATEGORY_DEVICE): int
    {
        if ((string) config('telegram.token') === '') {
            return 0;
        }

        $chatIds = $this->recipientQuery($category)->pluck('telegram_chat_id');

        $sent = 0;

        foreach ($chatIds as $chatId) {
            if ($this->bot->sendMessage((string) $chatId, $text)) {
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * @param  self::CATEGORY_*|string  $category
     * @return Builder<User>
     */
    public function recipientQuery(string $category): Builder
    {
        $allowed = match ($category) {
            self::CATEGORY_DEVICE => ['all', 'device'],
            self::CATEGORY_EXPIRY => ['all', 'expiry'],
            self::CATEGORY_DIGEST => ['all'],
            default => ['all'],
        };

        return User::query()
            ->role('super-admin')
            ->whereNotNull('telegram_chat_id')
            ->where('telegram_chat_id', '!=', '')
            ->whereIn('telegram_notify_prefs', $allowed);
    }
}
