<?php

namespace App\Services\Attendance;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Davomat jadvali davri: hafta, ikki hafta yoki oy. Ish kunlari — dushanba–juma.
 */
final class AttendancePeriod
{
    public const TYPES = ['week', '2weeks', 'month'];

    private const WEEKDAYS = [1 => 'Du', 2 => 'Se', 3 => 'Cho', 4 => 'Pa', 5 => 'Ju'];

    private function __construct(
        public readonly string $type,
        public readonly CarbonImmutable $anchor,
        public readonly CarbonImmutable $start,
        public readonly CarbonImmutable $end,
    ) {}

    public static function make(string $type, CarbonInterface $anchor): self
    {
        $type = in_array($type, self::TYPES, true) ? $type : '2weeks';
        $anchor = CarbonImmutable::parse($anchor->toDateString());

        [$start, $end] = match ($type) {
            'week' => [$anchor->startOfWeek(CarbonInterface::MONDAY), $anchor->endOfWeek(CarbonInterface::SUNDAY)],
            '2weeks' => [
                $anchor->startOfWeek(CarbonInterface::MONDAY)->subWeek(),
                $anchor->endOfWeek(CarbonInterface::SUNDAY),
            ],
            'month' => [$anchor->startOfMonth(), $anchor->endOfMonth()],
        };

        return new self($type, $anchor, $start->startOfDay(), $end->endOfDay());
    }

    /**
     * @return list<string>
     */
    public function workDates(): array
    {
        $dates = [];
        for ($day = $this->start; $day->lte($this->end); $day = $day->addDay()) {
            if (! $day->isWeekend()) {
                $dates[] = $day->toDateString();
            }
        }

        return $dates;
    }

    /**
     * @return list<array{date: string, label: string, weekday: string, is_today: bool}>
     */
    public function columns(CarbonInterface $now): array
    {
        $today = $now->toDateString();

        return array_map(function (string $date) use ($today): array {
            $day = CarbonImmutable::parse($date);

            return [
                'date' => $date,
                'label' => $day->format('d.m'),
                'weekday' => self::WEEKDAYS[$day->dayOfWeekIso],
                'is_today' => $date === $today,
            ];
        }, $this->workDates());
    }

    public function previousAnchor(): string
    {
        return $this->shift(-1)->toDateString();
    }

    public function nextAnchor(): string
    {
        return $this->shift(1)->toDateString();
    }

    public function label(): string
    {
        $dates = $this->workDates();
        $first = CarbonImmutable::parse($dates[0]);
        $last = CarbonImmutable::parse($dates[array_key_last($dates)]);

        $from = $first->year === $last->year ? $first->format('d.m') : $first->format('d.m.Y');

        return $from.' – '.$last->format('d.m.Y');
    }

    /**
     * @return array{type: string, date: string, label: string, prev: string, next: string, start: string, end: string}
     */
    public function toArray(): array
    {
        $dates = $this->workDates();

        return [
            'type' => $this->type,
            'date' => $this->anchor->toDateString(),
            'label' => $this->label(),
            'prev' => $this->previousAnchor(),
            'next' => $this->nextAnchor(),
            'start' => $dates[0],
            'end' => $dates[array_key_last($dates)],
        ];
    }

    private function shift(int $direction): CarbonImmutable
    {
        return match ($this->type) {
            'week' => $this->anchor->addWeeks($direction),
            '2weeks' => $this->anchor->addWeeks(2 * $direction),
            'month' => $this->anchor->startOfMonth()->addMonthsNoOverflow($direction),
        };
    }
}
