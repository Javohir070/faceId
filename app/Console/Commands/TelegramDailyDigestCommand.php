<?php

namespace App\Console\Commands;

use App\Services\Telegram\TelegramNotifier;
use App\Services\Telegram\TelegramReportService;
use Illuminate\Console\Command;

class TelegramDailyDigestCommand extends Command
{
    protected $signature = 'telegram:daily-digest';

    protected $description = 'Kecha bo‘yicha Telegram kunlik hisobot yuboradi';

    public function handle(TelegramReportService $reports, TelegramNotifier $notifier): int
    {
        $text = $reports->digestText(now()->subDay());
        $sent = $notifier->notifyAllLinkedUsers($text, TelegramNotifier::CATEGORY_DIGEST);

        $this->info("Kunlik hisobot yuborildi: {$sent} ta chat.");

        return self::SUCCESS;
    }
}
