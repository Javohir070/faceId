<?php

namespace App\Repositories;

use App\Interfaces\VisitAdjustmentRepositoryInterface;
use App\Models\VisitAdjustment;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class VisitAdjustmentRepository implements VisitAdjustmentRepositoryInterface
{
    /**
     * @return Collection<int, VisitAdjustment>
     */
    public function forDate(CarbonInterface $day): Collection
    {
        return VisitAdjustment::query()
            ->with(['group:id,name', 'user:id,name'])
            ->whereDate('date', $day->toDateString())
            ->latest('id')
            ->get();
    }

    public function create(array $data): VisitAdjustment
    {
        return VisitAdjustment::query()->create($data);
    }

    public function delete(VisitAdjustment $adjustment): void
    {
        $adjustment->delete();
    }
}
