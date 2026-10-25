<?php

namespace App\Services;

use App\Interfaces\VisitAdjustmentRepositoryInterface;
use App\Models\Group;
use App\Models\User;
use App\Models\VisitAdjustment;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Jonli statistikaga qo‘lda qo‘shilgan kirishlar. Qoidalar: docs/superpowers/specs/2026-10-25-visit-adjustments-design.md
 */
class VisitAdjustmentService
{
    /** Qo‘shilgan kirishlarning erkaklar ulushi; qolgani ayollar. */
    public const MALE_SHARE = 0.7;

    /** Guruh tanlanmasa — shu nomli guruhga qo‘shiladi. */
    public const DEFAULT_GROUP = 'Ishtirokchi';

    public function __construct(
        private readonly VisitAdjustmentRepositoryInterface $repository,
    ) {}

    /**
     * @return Collection<int, VisitAdjustment>
     */
    public function forDate(CarbonInterface $day): Collection
    {
        return $this->repository->forDate($day);
    }

    /**
     * @param  array{date: string, entered: int, group_id?: ?int, note?: ?string}  $data
     */
    public function create(array $data, ?User $user): VisitAdjustment
    {
        $entered = (int) $data['entered'];
        $male = (int) round($entered * self::MALE_SHARE);
        $group = isset($data['group_id']) ? Group::query()->find($data['group_id']) : null;

        return $this->repository->create([
            'date' => $data['date'],
            'department_id' => $group?->department_id ?? $user?->department_id,
            'group_id' => $group?->id,
            'entered' => $entered,
            'male' => $male,
            'female' => $entered - $male,
            'note' => $data['note'] ?? null,
            'user_id' => $user?->id,
        ]);
    }

    public function delete(VisitAdjustment $adjustment): void
    {
        $this->repository->delete($adjustment);
    }

    /**
     * Kun bo‘yicha jami: hammasi mahalliy (`local`), guruhlar — `group_id => son`.
     *
     * @return array{entered: int, male: int, female: int, local: int, groups: array<int, int>}
     */
    public function totals(CarbonInterface $day): array
    {
        $rows = VisitAdjustment::query()
            ->whereDate('date', $day->toDateString())
            ->get(['group_id', 'entered', 'male', 'female']);

        $groups = [];
        foreach ($rows->whereNotNull('group_id') as $row) {
            $groups[$row->group_id] = ($groups[$row->group_id] ?? 0) + $row->entered;
        }

        return [
            'entered' => (int) $rows->sum('entered'),
            'male' => (int) $rows->sum('male'),
            'female' => (int) $rows->sum('female'),
            'local' => (int) $rows->sum('entered'),
            'groups' => $groups,
        ];
    }

    /**
     * Shu oraliqda qo‘shilgan (`created_at`) va oraliq tugaydigan kunga (bugun) tegishli kirishlar — "so‘nggi 1 soatda".
     */
    public function enteredBetween(CarbonInterface $from, CarbonInterface $to): int
    {
        return (int) VisitAdjustment::query()
            ->whereDate('date', Carbon::parse($to)->toDateString())
            ->whereBetween('created_at', [$from, $to])
            ->sum('entered');
    }

    public function defaultGroupId(): ?int
    {
        return Group::query()->where('name', self::DEFAULT_GROUP)->orderBy('id')->value('id');
    }
}
