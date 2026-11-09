<?php

namespace App\Services\Hikvision;

use App\Models\Device;
use App\Models\Employee;
use App\Models\EventReconciliation;
use App\Models\Report;
use App\Services\Telegram\TelegramNotifier;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Qurilmadagi va tizimdagi kirish-chiqishlarni kunma-kun solishtiradi. Terminal faqat o‘qiladi;
 * yetishmagan event shu o‘qishda tizimga qo‘shiladi (takrorlar yozilmaydi).
 *
 * Sonlar faqat bizning xodimlar bo‘yicha: terminal boshqa tizimlar bilan umumiy, unda tizimimizda
 * yo‘q odamlar ham bor — ular "begona" sifatida faqat soni bilan alohida yoziladi.
 */
class EventReconciliationService
{
    public function __construct(
        private readonly HikvisionEventPullService $pullService,
        private readonly TelegramNotifier $notifier,
    ) {}

    public function reconcile(Device $device, CarbonInterface $date): EventReconciliation
    {
        $start = Carbon::parse($date->format('Y-m-d'), $this->eventTimezone())->startOfDay();
        $end = $start->copy()->endOfDay();

        if (! $device->is_online || ! $device->hasIsapiCredentials()) {
            return $this->save($device, $start, [
                'status' => EventReconciliation::STATUS_UNREACHABLE,
                'error' => $device->is_online ? 'Login/parol sozlanmagan' : 'Qurilma oflayn',
            ]);
        }

        $ourNumbers = Employee::query()->withoutGlobalScopes()->pluck('card')->flip();
        $ours = [];
        $strangers = [];
        $recovered = 0;

        try {
            $result = $this->pullService->pullDeviceRange(
                $device,
                $start,
                $end,
                function (string $employeeNo, string $time, string $outcome) use ($ourNumbers, &$ours, &$strangers, &$recovered): void {
                    // Tizim bir soniyadagi bir odamni bitta o‘tish deb yozadi, shu yerda ham shunday sanaladi.
                    $key = $employeeNo.'|'.$time;

                    if (! $ourNumbers->has($employeeNo)) {
                        $strangers[$key] = true;

                        return;
                    }

                    if ($outcome === 'imported' && ! isset($ours[$key])) {
                        $recovered++;
                    }

                    $ours[$key] = true;
                },
            );
        } catch (\Throwable $exception) {
            return $this->save($device, $start, [
                'status' => EventReconciliation::STATUS_UNREACHABLE,
                'error' => Str::limit($exception->getMessage(), 250),
            ]);
        }

        $systemCount = Report::query()
            ->withoutGlobalScopes()
            ->where('device_id', $device->id)
            ->whereNotNull('employee_id')
            ->whereBetween('occurred_at', [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')])
            ->count();

        $complete = ! $result['truncated'];

        return $this->save($device, $start, [
            'device_count' => count($ours),
            'system_count' => $systemCount,
            'recovered' => $recovered,
            'strangers' => count($strangers),
            'status' => $complete && count($ours) === $systemCount
                ? EventReconciliation::STATUS_OK
                : EventReconciliation::STATUS_MISMATCH,
            'error' => $complete ? null : 'Qurilmadagi eventlar to‘liq o‘qilmadi',
        ]);
    }

    /**
     * Kechagi kun doim tekshiriladi. Undan oldingi kunlar faqat hali tekshirilmagan yoki mos chiqmagan
     * bo‘lsa — qurilma bir kecha o‘chiq bo‘lgan bo‘lsa, keyingi kechalar o‘sha kunni to‘ldiradi.
     *
     * @return Collection<int, EventReconciliation> yangi paydo bo‘lgan muammolar
     */
    public function runNightly(int $days = 4): Collection
    {
        $yesterday = now($this->eventTimezone())->subDay()->startOfDay();
        $problems = collect();

        Device::query()->orderBy('id')->each(function (Device $device) use ($yesterday, $days, $problems): void {
            $deviceProblems = collect();

            for ($offset = 0; $offset < max($days, 1); $offset++) {
                $date = $yesterday->copy()->subDays($offset);
                $previous = EventReconciliation::query()
                    ->where('device_id', $device->id)
                    ->whereDate('date', $date->toDateString())
                    ->first();

                if ($offset > 0 && $previous?->status === EventReconciliation::STATUS_OK) {
                    continue;
                }

                $current = $this->reconcile($device, $date);

                // Bir xil muammo har kecha qayta yuborilmaydi.
                if ($current->status !== EventReconciliation::STATUS_OK && $previous?->status !== $current->status) {
                    $deviceProblems->push($current);
                }
            }

            if ($deviceProblems->isNotEmpty()) {
                $this->notifier->notifyDevice($device, $this->problemText($device, $deviceProblems));
                $problems->push(...$deviceProblems);
            }
        });

        return $problems;
    }

    /**
     * @param  Collection<int, EventReconciliation>  $problems
     */
    private function problemText(Device $device, Collection $problems): string
    {
        $lines = ["⚠️ Solishtirish: {$device->name}"];

        foreach ($problems->sortBy('date') as $problem) {
            $date = $problem->date->format('Y-m-d');
            $lines[] = $problem->status === EventReconciliation::STATUS_UNREACHABLE
                ? "{$date}: qurilma javob bermadi"
                : "{$date}: qurilmada {$problem->device_count}, tizimda {$problem->system_count}";
        }

        $lines[] = 'Batafsil: Event tortish → Solishtirish';

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function save(Device $device, CarbonInterface $date, array $values): EventReconciliation
    {
        // Sana ustuni bazaga qarab "Y-m-d" yoki "Y-m-d 00:00:00" bo‘lib saqlanadi, shuning uchun whereDate.
        $record = EventReconciliation::query()
            ->where('device_id', $device->id)
            ->whereDate('date', $date->toDateString())
            ->first() ?? new EventReconciliation(['device_id' => $device->id, 'date' => $date->toDateString()]);

        $record->fill([
            'device_count' => null,
            'system_count' => null,
            'recovered' => 0,
            'strangers' => null,
            'error' => null,
            ...$values,
            'checked_at' => now(),
        ])->save();

        return $record;
    }

    private function eventTimezone(): string
    {
        return (string) config('hikvision.event_timezone', config('app.timezone'));
    }
}
