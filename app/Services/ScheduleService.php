<?php

namespace App\Services;

use App\Interfaces\ScheduleRepositoryInterface;
use App\Models\Schedule;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ScheduleService
{
    public function __construct(
        private readonly ScheduleRepositoryInterface $scheduleRepository,
    ) {}

    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        return $this->scheduleRepository->paginate($perPage);
    }

    public function create(array $data): Schedule
    {
        return DB::transaction(fn (): Schedule => $this->scheduleRepository->create($data));
    }

    public function update(Schedule $schedule, array $data): Schedule
    {
        return DB::transaction(fn (): Schedule => $this->scheduleRepository->update($schedule, $data));
    }

    public function delete(Schedule $schedule): ?string
    {
        if ($this->scheduleRepository->hasEmployees($schedule)) {
            return 'Jadvalga biriktirilgan xodimlar bor. Avval xodimlarni o‘chiring yoki boshqa jadvalga o‘tkazing.';
        }

        DB::transaction(function () use ($schedule): void {
            $this->scheduleRepository->delete($schedule);
        });

        return null;
    }
}
