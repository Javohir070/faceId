<?php

namespace App\Services\Telegram;

use App\Models\Employee;

class EmployeeExpiryNotifier
{
    public function __construct(
        private readonly TelegramNotifier $notifier,
    ) {}

    /**
     * @return array{warned: int, expired: int}
     */
    public function notifyDue(): array
    {
        $warnDays = max((int) config('telegram.employee_expiry_warn_days', 3), 1);
        $now = now();
        $warnUntil = $now->copy()->addDays($warnDays);

        $warned = 0;
        $expired = 0;

        Employee::query()
            ->whereNull('expiry_warned_at')
            ->where('valid_to_dt', '>', $now)
            ->where('valid_to_dt', '<=', $warnUntil)
            ->orderBy('id')
            ->each(function (Employee $employee) use (&$warned): void {
                $this->notifier->notifyAllLinkedUsers(
                    sprintf(
                        "⏰ Xodim muddati tugashiga kam qoldi\nIsm: %s\nKarta: %s\nTugash: %s",
                        $employee->name,
                        $employee->card,
                        $employee->valid_to_dt?->format('Y-m-d H:i:s') ?? '—',
                    )
                );

                $employee->forceFill(['expiry_warned_at' => now()])->save();
                $warned++;
            });

        Employee::query()
            ->whereNull('expiry_notified_at')
            ->where('valid_to_dt', '<', $now)
            ->orderBy('id')
            ->each(function (Employee $employee) use (&$expired): void {
                $this->notifier->notifyAllLinkedUsers(
                    sprintf(
                        "❌ Xodim muddati tugadi\nIsm: %s\nKarta: %s\nTugash: %s",
                        $employee->name,
                        $employee->card,
                        $employee->valid_to_dt?->format('Y-m-d H:i:s') ?? '—',
                    )
                );

                $employee->forceFill(['expiry_notified_at' => now()])->save();
                $expired++;
            });

        return ['warned' => $warned, 'expired' => $expired];
    }
}
