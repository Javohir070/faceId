<?php

namespace App\Console\Commands;

use App\Services\Turnstile\TurnstilePullService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PullTurnstilePassesCommand extends Command
{
    protected $signature = 'turnstile:pull
                            {--for=0 : Shuncha soniya davomida har TURNSTILE_POLL_SECONDS da qayta tortish (0 — bir marta)}
                            {--since= : Birinchi ishga tushishda shu sanadan boshlab olish (YYYY-MM-DD, standart: bugun)}
                            {--rescan : Kursorni unutib, --since (standart: bugun) dan qayta o‘qish — masalan, yangi mehmonlar import qilingandan keyin}';

    protected $description = 'Turniket bazasidagi (gecishareketleri) o‘tishlarni Jonli kuzatuv va Tashrif statistikasiga tortadi';

    public function handle(TurnstilePullService $pullService): int
    {
        $since = $this->option('since');

        if ($since !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $since) !== 1) {
            $this->error('--since formati: YYYY-MM-DD');

            return self::FAILURE;
        }

        $since = $since !== null ? Carbon::parse((string) $since)->startOfDay() : null;

        // Noma’lum karta o‘tishlari kursor ortida qoladi; karta egasi keyin import qilinsa, qayta o‘qish ularni oladi
        // (allaqachon yozilganlari ReportService da takror deb o‘tkaziladi).
        if ($this->option('rescan')) {
            Cache::forget(TurnstilePullService::CURSOR_KEY);
        }
        $deadline = time() + max((int) $this->option('for'), 0);
        $pollSeconds = (int) config('turnstile.poll_seconds', 2);
        $looping = (int) $this->option('for') > 0;

        do {
            try {
                $result = $pullService->pull($since);
            } catch (\Throwable $exception) {
                if (! $looping) {
                    $this->error('Turniket bazasidan o‘qib bo‘lmadi: '.$exception->getMessage());

                    return self::FAILURE;
                }

                // Turniket serveri javob bermasa — shu daqiqa to‘xtaydi, scheduler keyingi daqiqada qayta ishga tushiradi
                // (har 2 soniyada timeout kutib, logni to‘ldirmasin).
                Log::warning('Turniket: o‘tishlarni tortib bo‘lmadi', ['message' => $exception->getMessage()]);

                return self::FAILURE;
            }

            if ($result['locked'] && ! $looping) {
                $this->comment('Boshqa turnstile:pull ishlayapti — o‘tkazib yuborildi.');
            } elseif ($result['read'] > 0 || $result['recovered'] > 0 || ! $looping) {
                $this->line(sprintf(
                    '[%s] Turniket: %d o‘qildi — %d yangi, %d takror, %d noma’lum karta, %d rad etilgan, %d kechikib qo‘shildi (kursor %d).',
                    now()->format('H:i:s'),
                    $result['read'],
                    $result['imported'],
                    $result['duplicate'],
                    $result['unknown_card'],
                    $result['rejected'],
                    $result['recovered'],
                    $result['cursor'],
                ));
            }

            if ($looping && time() + $pollSeconds < $deadline) {
                sleep($pollSeconds);
            }
        } while ($looping && time() + $pollSeconds < $deadline);

        return self::SUCCESS;
    }
}
