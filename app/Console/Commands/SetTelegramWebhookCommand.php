<?php

namespace App\Console\Commands;

use App\Services\Telegram\TelegramBotService;
use Illuminate\Console\Command;

class SetTelegramWebhookCommand extends Command
{
    protected $signature = 'telegram:set-webhook {url?}';

    protected $description = 'Telegram bot webhook URL ni o‘rnatadi';

    public function handle(TelegramBotService $bot): int
    {
        $url = $this->argument('url')
            ?: rtrim((string) config('app.url'), '/').'/api/telegram/webhook';
        $secret = (string) config('telegram.webhook_secret');

        if ((string) config('telegram.token') === '') {
            $this->error('TELEGRAM_BOT_TOKEN bo‘sh');

            return self::FAILURE;
        }

        if (! $bot->setWebhook($url, $secret)) {
            $this->error('Webhook o‘rnatilmadi');

            return self::FAILURE;
        }

        $this->info("Webhook o‘rnatildi: {$url}");

        return self::SUCCESS;
    }
}
