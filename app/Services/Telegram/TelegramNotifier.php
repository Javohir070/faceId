<?php

namespace App\Services\Telegram;

use App\Models\User;

class TelegramNotifier
{
    public function __construct(
        private readonly TelegramBotService $bot,
    ) {}

    public function notifyAllLinkedUsers(string $text): int
    {
        if ((string) config('telegram.token') === '') {
            return 0;
        }

        $chatIds = User::query()
            ->whereNotNull('telegram_chat_id')
            ->where('telegram_chat_id', '!=', '')
            ->pluck('telegram_chat_id');

        $sent = 0;

        foreach ($chatIds as $chatId) {
            if ($this->bot->sendMessage((string) $chatId, $text)) {
                $sent++;
            }
        }

        return $sent;
    }
}
