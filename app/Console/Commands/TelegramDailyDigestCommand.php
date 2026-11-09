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
        $day = now()->subDay();
        // Har kim o‘z bo‘limi va o‘z qurilmalari bo‘yicha hisobot oladi.
        $sent = $notifier->notifyEachLinkedUser(
            TelegramNotifier::CATEGORY_DIGEST,
            fn (): string => $reports->digestText($day),
        );

        $this->info("Kunlik hisobot yuborildi: {$sent} ta chat.");

        return self::SUCCESS;
    }
}
