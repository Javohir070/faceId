<?php

namespace App\Console\Commands;

use App\Services\Telegram\EmployeeExpiryNotifier;
use Illuminate\Console\Command;

class NotifyEmployeeExpiryCommand extends Command
{
    protected $signature = 'employees:notify-expiry';

    protected $description = 'valid_to_dt yaqinlashgan/tugagan xodimlar haqida Telegram xabar yuboradi';

    public function handle(EmployeeExpiryNotifier $notifier): int
    {
        $result = $notifier->notifyDue();

        $this->info(sprintf(
            'Xodim muddati xabarlari: %d ogohlantirish, %d tugagan.',
            $result['warned'],
            $result['expired'],
        ));

        return self::SUCCESS;
    }
}
