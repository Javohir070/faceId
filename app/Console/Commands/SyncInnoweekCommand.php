<?php

namespace App\Console\Commands;

use App\Services\Innoweek\InnoweekSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncInnoweekCommand extends Command
{
    protected $signature = 'innoweek:sync
                            {--for=0 : Shuncha soniya davomida har INNOWEEK_SYNC_INTERVAL da qayta so‘rash (0 — bir marta)}
                            {--full : Kursorni unutib, hamma ishtirokchilarni qaytadan olish}';

    protected $description = 'Innoweek API dan yangi va o‘zgargan ishtirokchilarni mehmon sifatida tortadi (joyida ro‘yxatdan o‘tganlar ham)';

    public function handle(InnoweekSyncService $syncService): int
    {
        $looping = (int) $this->option('for') > 0;
        $deadline = time() + max((int) $this->option('for'), 0);
        $interval = (int) config('innoweek.sync_interval_seconds', 20);
        $full = (bool) $this->option('full');

        do {
            try {
                $result = $syncService->sync($full);
            } catch (\Throwable $exception) {
                if (! $looping) {
                    $this->error('Innoweek dan tortib bo‘lmadi: '.$exception->getMessage());

                    return self::FAILURE;
                }

                // API javob bermasa — shu daqiqa to‘xtaydi, scheduler keyingi daqiqada qayta ishga tushiradi.
                Log::warning('Innoweek: ishtirokchilarni tortib bo‘lmadi', ['message' => $exception->getMessage()]);

                return self::FAILURE;
            }

            $full = false;

            if ($result['locked'] && ! $looping) {
                $this->comment('Boshqa innoweek:sync ishlayapti — o‘tkazib yuborildi.');
            } elseif ($result['received'] > 0 || ! $looping) {
                $this->line(sprintf(
                    '[%s] Innoweek: %d keldi — "%s" bo‘limiga %d yangi, %d yangilangan, %d oddiy xodim kartasi, %d o‘chirilgan.',
                    now()->format('H:i:s'),
                    $result['received'],
                    $result['department'],
                    $result['created'],
                    $result['updated'],
                    $result['skipped_staff'],
                    $result['skipped_deleted'],
                ));
            }

            if ($looping && time() + $interval < $deadline) {
                sleep($interval);
            }
        } while ($looping && time() + $interval < $deadline);

        return self::SUCCESS;
    }
}
