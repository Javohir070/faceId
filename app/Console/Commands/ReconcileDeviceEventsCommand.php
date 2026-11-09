<?php

namespace App\Console\Commands;

use App\Services\Hikvision\EventReconciliationService;
use Illuminate\Console\Command;

class ReconcileDeviceEventsCommand extends Command
{
    protected $signature = 'devices:reconcile-events {--days=4 : Kechagi kun bilan birga nechta kun ko‘riladi}';

    protected $description = 'Qurilmadagi va tizimdagi kirish-chiqishlarni solishtiradi, yetishmaganini tortib oladi';

    public function handle(EventReconciliationService $service): int
    {
        $problems = $service->runNightly((int) $this->option('days'));

        $this->info(sprintf('Solishtirish yakunlandi: %d ta yangi muammo.', $problems->count()));

        return self::SUCCESS;
    }
}
