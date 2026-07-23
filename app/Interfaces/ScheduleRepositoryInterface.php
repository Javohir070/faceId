<?php

namespace App\Interfaces;

use App\Models\Schedule;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ScheduleRepositoryInterface
{
    public function paginate(int $perPage = 20): LengthAwarePaginator;

    public function allOrdered(): Collection;

    public function create(array $data): Schedule;

    public function update(Schedule $schedule, array $data): Schedule;

    public function delete(Schedule $schedule): void;

    public function hasEmployees(Schedule $schedule): bool;
}
