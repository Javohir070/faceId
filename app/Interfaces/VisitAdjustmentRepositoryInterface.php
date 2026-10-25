<?php

namespace App\Interfaces;

use App\Models\VisitAdjustment;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

interface VisitAdjustmentRepositoryInterface
{
    /**
     * @return Collection<int, VisitAdjustment>
     */
    public function forDate(CarbonInterface $day): Collection;

    public function create(array $data): VisitAdjustment;

    public function delete(VisitAdjustment $adjustment): void;
}
