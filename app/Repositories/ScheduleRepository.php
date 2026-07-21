<?php

namespace App\Repositories;

use App\Interfaces\ScheduleRepositoryInterface;
use App\Models\Schedule;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ScheduleRepository implements ScheduleRepositoryInterface
{
    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        return Schedule::query()
            ->latest('enter_time')
            ->paginate($perPage);
    }

    public function allOrdered(): Collection
    {
        return Schedule::query()
            ->orderBy('name')
            ->get();
    }

    public function create(array $data): Schedule
    {
        return Schedule::query()->create($data);
    }

    public function update(Schedule $schedule, array $data): Schedule
    {
        $schedule->update($data);

        return $schedule->fresh();
    }

    public function delete(Schedule $schedule): void
    {
        $schedule->delete();
    }
}
