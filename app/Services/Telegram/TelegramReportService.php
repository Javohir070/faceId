<?php

namespace App\Services\Telegram;

use App\Models\Device;
use App\Models\Employee;
use App\Models\Report;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class TelegramReportService
{
    public function statusText(): string
    {
        $today = now()->startOfDay();
        $tomorrow = $today->copy()->addDay();

        $offlineCount = Device::query()->where('is_online', false)->count();
        $onlineCount = Device::query()->where('is_online', true)->count();

        $entries = Report::query()
            ->where('type', Report::TYPE_ENTRY)
            ->where('occurred_at', '>=', $today)
            ->where('occurred_at', '<', $tomorrow)
            ->count();

        $exits = Report::query()
            ->where('type', Report::TYPE_EXIT)
            ->where('occurred_at', '>=', $today)
            ->where('occurred_at', '<', $tomorrow)
            ->count();

        $warnDays = max((int) config('telegram.employee_expiry_warn_days', 3), 1);
        $expiring = Employee::query()
            ->where('valid_to_dt', '>', now())
            ->where('valid_to_dt', '<=', now()->addDays($warnDays))
            ->count();

        $expired = Employee::query()
            ->where('valid_to_dt', '<', now())
            ->count();

        return implode("\n", [
            '📊 Holat',
            "Qurilma: {$onlineCount} online, {$offlineCount} oflayn",
            "Bugun: {$entries} kirish, {$exits} chiqish",
            "Muddat: {$expiring} yaqin, {$expired} tugagan",
            '',
            'Buyruqlar: /devices /expiry /settings /status',
        ]);
    }

    public function offlineDevicesText(): string
    {
        $devices = Device::query()
            ->where('is_online', false)
            ->orderBy('name')
            ->get(['name', 'ip_address', 'type']);

        if ($devices->isEmpty()) {
            return '✅ Oflayn qurilma yo‘q.';
        }

        $lines = ['🔌 Oflayn qurilmalar:'];

        foreach ($devices as $device) {
            $lines[] = sprintf(
                '• %s (%s) — %s',
                $device->name,
                $device->ip_address,
                $device->typeLabel(),
            );
        }

        return implode("\n", $lines);
    }

    public function expiryText(): string
    {
        $warnDays = max((int) config('telegram.employee_expiry_warn_days', 3), 1);

        $expiring = Employee::query()
            ->where('valid_to_dt', '>', now())
            ->where('valid_to_dt', '<=', now()->addDays($warnDays))
            ->orderBy('valid_to_dt')
            ->limit(20)
            ->get(['name', 'card', 'valid_to_dt']);

        $expired = Employee::query()
            ->where('valid_to_dt', '<', now())
            ->orderByDesc('valid_to_dt')
            ->limit(20)
            ->get(['name', 'card', 'valid_to_dt']);

        if ($expiring->isEmpty() && $expired->isEmpty()) {
            return '✅ Yaqin/tugagan muddatli xodim yo‘q.';
        }

        $lines = [];

        if ($expiring->isNotEmpty()) {
            $lines[] = "⏰ {$warnDays} kun ichida tugaydi:";
            foreach ($expiring as $employee) {
                $lines[] = sprintf(
                    '• %s (%s) — %s',
                    $employee->name,
                    $employee->card,
                    $employee->valid_to_dt?->format('Y-m-d H:i') ?? '—',
                );
            }
        }

        if ($expired->isNotEmpty()) {
            if ($lines !== []) {
                $lines[] = '';
            }
            $lines[] = '❌ Muddati tugagan:';
            foreach ($expired as $employee) {
                $lines[] = sprintf(
                    '• %s (%s) — %s',
                    $employee->name,
                    $employee->card,
                    $employee->valid_to_dt?->format('Y-m-d H:i') ?? '—',
                );
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @return array{late: Collection<int, array{name: string, card: string, entered_at: string}>, absent: Collection<int, array{name: string, card: string}>, offline_devices: Collection<int, Device>}
     */
    public function digestForDate(CarbonInterface $day): array
    {
        $start = Carbon::parse($day)->startOfDay();
        $end = $start->copy()->addDay();

        $employees = Employee::query()
            ->with('schedule')
            ->whereNotNull('schedule_id')
            ->where('valid_from_dt', '<=', $end)
            ->where('valid_to_dt', '>=', $start)
            ->get();

        $firstEntries = Report::query()
            ->where('type', Report::TYPE_ENTRY)
            ->whereNotNull('employee_id')
            ->where('occurred_at', '>=', $start)
            ->where('occurred_at', '<', $end)
            ->orderBy('occurred_at')
            ->get(['employee_id', 'occurred_at'])
            ->unique('employee_id')
            ->keyBy('employee_id');

        $late = collect();
        $absent = collect();

        foreach ($employees as $employee) {
            $enterTime = $employee->schedule?->enter_time;

            if ($enterTime === null) {
                continue;
            }

            $deadline = $start->copy()->setTimeFromTimeString($enterTime->format('H:i:s'));
            $first = $firstEntries->get($employee->id);

            if ($first === null) {
                $absent->push([
                    'name' => $employee->name,
                    'card' => $employee->card,
                ]);

                continue;
            }

            if (Carbon::parse($first->occurred_at)->gt($deadline)) {
                $late->push([
                    'name' => $employee->name,
                    'card' => $employee->card,
                    'entered_at' => Carbon::parse($first->occurred_at)->format('H:i'),
                ]);
            }
        }

        $offline = Device::query()
            ->where('is_online', false)
            ->orderBy('name')
            ->get();

        return [
            'late' => $late,
            'absent' => $absent,
            'offline_devices' => $offline,
        ];
    }

    public function digestText(?CarbonInterface $day = null): string
    {
        $day ??= now()->subDay();
        $data = $this->digestForDate($day);

        $lines = [
            '📅 Kunlik hisobot: '.$day->format('Y-m-d'),
            sprintf('Kechikkan: %d', $data['late']->count()),
            sprintf('Kelmagan: %d', $data['absent']->count()),
            sprintf('Oflayn qurilma: %d', $data['offline_devices']->count()),
        ];

        if ($data['late']->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'Kechikkanlar (max 10):';
            foreach ($data['late']->take(10) as $row) {
                $lines[] = "• {$row['name']} ({$row['card']}) — {$row['entered_at']}";
            }
        }

        if ($data['absent']->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'Kelmaganlar (max 10):';
            foreach ($data['absent']->take(10) as $row) {
                $lines[] = "• {$row['name']} ({$row['card']})";
            }
        }

        if ($data['offline_devices']->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'Oflayn:';
            foreach ($data['offline_devices']->take(10) as $device) {
                $lines[] = "• {$device->name}";
            }
        }

        return implode("\n", $lines);
    }
}
