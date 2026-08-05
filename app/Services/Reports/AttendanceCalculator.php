<?php

namespace App\Services\Reports;

use Carbon\Carbon;

class AttendanceCalculator
{
    public function isNightShift(string $enterTime, string $exitTime): bool
    {
        return $this->normalizeTime($enterTime) > $this->normalizeTime($exitTime);
    }

    public function shiftMoment(Carbon $occurredAt): Carbon
    {
        return $occurredAt->copy()->subHours(12);
    }

    public function workDate(Carbon $occurredAt, bool $nightShift): string
    {
        $moment = $nightShift ? $this->shiftMoment($occurredAt) : $occurredAt;

        return $moment->toDateString();
    }

    public function lateSeconds(Carbon $firstEntry, string $enterTime, bool $nightShift): int
    {
        $actual = $nightShift ? $this->shiftMoment($firstEntry) : $firstEntry->copy();
        $scheduled = $this->scheduledMomentOnSameDay($actual, $enterTime, $nightShift);
        $diff = $actual->getTimestamp() - $scheduled->getTimestamp();

        return $diff > 0 ? $diff : 0;
    }

    public function earlySeconds(Carbon $lastExit, string $exitTime, bool $nightShift): int
    {
        $actual = $nightShift ? $this->shiftMoment($lastExit) : $lastExit->copy();
        $scheduled = $this->scheduledMomentOnSameDay($actual, $exitTime, $nightShift);
        $diff = $scheduled->getTimestamp() - $actual->getTimestamp();

        return $diff > 0 ? $diff : 0;
    }

    public function workedSeconds(?Carbon $firstEntry, ?Carbon $lastExit): int
    {
        if ($firstEntry === null || $lastExit === null) {
            return 0;
        }

        $seconds = $lastExit->getTimestamp() - $firstEntry->getTimestamp();

        return $seconds > 0 ? $seconds : 0;
    }

    private function scheduledMomentOnSameDay(Carbon $reference, string $time, bool $nightShift): Carbon
    {
        $normalized = $this->normalizeTime($time);

        if ($nightShift) {
            $normalized = Carbon::createFromFormat('H:i:s', $normalized)
                ->subHours(12)
                ->format('H:i:s');
        }

        return Carbon::parse($reference->toDateString().' '.$normalized);
    }

    private function normalizeTime(string $time): string
    {
        if (preg_match('/^\d{2}:\d{2}$/', $time) === 1) {
            return $time.':00';
        }

        return Carbon::parse($time)->format('H:i:s');
    }
}
