<?php

namespace App\Services\Telegram;

class TelegramKeyboard
{
    /**
     * @return array{inline_keyboard: list<list<array{text: string, callback_data: string}>>}
     */
    public static function mainMenu(): array
    {
        return [
            'inline_keyboard' => [
                [
                    ['text' => '📊 Holat', 'callback_data' => 'cmd:status'],
                    ['text' => '🔌 Qurilmalar', 'callback_data' => 'cmd:devices'],
                ],
                [
                    ['text' => '⏰ Muddat', 'callback_data' => 'cmd:expiry'],
                    ['text' => '⚙️ Sozlama', 'callback_data' => 'cmd:settings'],
                ],
                [
                    ['text' => '🚪 Uzish', 'callback_data' => 'cmd:unlink'],
                ],
            ],
        ];
    }

    /**
     * @return array{inline_keyboard: list<list<array{text: string, callback_data: string}>>}
     */
    public static function settingsMenu(): array
    {
        return [
            'inline_keyboard' => [
                [
                    ['text' => 'Hammasi', 'callback_data' => 'pref:all'],
                    ['text' => 'Faqat qurilma', 'callback_data' => 'pref:device'],
                ],
                [
                    ['text' => 'Faqat muddat', 'callback_data' => 'pref:expiry'],
                    ['text' => 'O‘chirish', 'callback_data' => 'pref:none'],
                ],
                [
                    ['text' => '⬅️ Menyuga', 'callback_data' => 'menu:main'],
                ],
            ],
        ];
    }
}
