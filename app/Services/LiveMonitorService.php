<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Face;
use App\Models\Group;
use App\Models\Report;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class LiveMonitorService
{
    public function __construct(
        private readonly VisitAdjustmentService $adjustments,
    ) {}

    /**
     * Bugungi oxirgi kirishlar — eng yangisi birinchi (kirish monitori: chiqishlar ko‘rsatilmaydi).
     * Bo‘lim scope Report modelida qo‘llanadi.
     * Tartib bazaga tushish ketma-ketligi bo‘yicha: qurilma soati noto‘g‘ri bo‘lsa ham ro‘yxat aralashmaydi.
     * Qurilma ulanganda eski (oflayn to‘plangan) hodisalarni ham yuboradi — ular monitorga chiqmaydi.
     *
     * @param  (callable(int): string)|null  $faceUrl  yuz rasmi havolasi (zal ekranining ochiq havolasi uchun boshqa route)
     * @return Collection<int, array<string, mixed>>
     */
    public function recentPasses(int $limit = 7, ?callable $faceUrl = null): Collection
    {
        return Report::query()
            ->withVisitors()
            ->entry()
            ->whereBetween('occurred_at', [now()->startOfDay(), now()->endOfDay()])
            ->with([
                // Yuz rasmi (longText) yuklanmaydi — faqat bor/yo‘qligi.
                'employee' => fn ($query) => $query
                    ->withVisitors()
                    ->select(['id', 'name', 'gender', 'country', 'position', 'department_id', 'group_id'])
                    ->withExists('face'),
                'employee.department:id,name',
                'employee.group:id,name',
                'device:id,name',
            ])
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (Report $report): array => $this->mapPass($report, $faceUrl))
            ->values();
    }

    /**
     * Bugungi ko‘rsatkichlar — o‘tishlar emas, takrorlanmas xodimlar soni (+ admin qo‘lda qo‘shgan kirishlar).
     *
     * @return array{entered:int, male:int, female:int, local:int, foreign:int, groups:list<array{id:int, name:string, entered:int}>}
     */
    public function todayStats(): array
    {
        $range = [now()->startOfDay(), now()->endOfDay()];

        $enteredByGender = fn (int $gender): int => Employee::query()
            ->withVisitors()
            ->where('gender', $gender)
            ->whereHas('reports', fn ($query) => $query->withVisitors()->entry()->whereBetween('occurred_at', $range))
            ->count();

        $added = $this->adjustments->totals($range[0]);

        return [
            'entered' => Report::query()
                ->withVisitors()
                ->entry()
                ->whereBetween('occurred_at', $range)
                ->whereNotNull('employee_id')
                ->distinct()
                ->count('employee_id') + $added['entered'],
            'male' => $enteredByGender(Employee::GENDER_MALE) + $added['male'],
            'female' => $enteredByGender(Employee::GENDER_FEMALE) + $added['female'],
            ...$this->enteredByOrigin($range),
            'groups' => $this->enteredByGroup($range),
        ];
    }

    /**
     * Bugun kirgan xodimlar: mahalliy (O‘zbekiston) va xorijiy. Qo‘lda qo‘shilganlar mahalliyga qo‘shiladi.
     *
     * @param  array{0:CarbonInterface, 1:CarbonInterface}  $range
     * @return array{local:int, foreign:int}
     */
    public function enteredByOrigin(array $range): array
    {
        $entered = fn () => Employee::query()
            ->withVisitors()
            ->whereHas('reports', fn ($query) => $query->withVisitors()->entry()->whereBetween('occurred_at', $range));

        return [
            'local' => $entered()->where('country', Employee::LOCAL_COUNTRY)->count() + $this->adjustments->totals($range[0])['local'],
            'foreign' => $entered()->where('country', '!=', Employee::LOCAL_COUNTRY)->count(),
        ];
    }

    /**
     * Har bir guruhdan bugun kirgan xodimlar soni (OAV, Homiy, Ishtirokchi va h.k.).
     * Bugun hech kim kirmagan guruh ham 0 bilan qaytadi — monitordagi plitkalar kun davomida o‘zgarmaydi.
     * Qo‘lda qo‘shilgan kirishlar o‘z guruhiga qo‘shiladi.
     *
     * @param  array{0:CarbonInterface, 1:CarbonInterface}  $range
     * @return list<array{id:int, name:string, entered:int}>
     */
    public function enteredByGroup(array $range): array
    {
        $entered = Employee::query()
            ->withVisitors()
            ->whereNotNull('group_id')
            ->whereHas('reports', fn ($query) => $query->withVisitors()->entry()->whereBetween('occurred_at', $range))
            ->groupBy('group_id')
            ->selectRaw('group_id, count(*) as total')
            ->pluck('total', 'group_id');
        $added = $this->adjustments->totals($range[0])['groups'];

        return Group::query()
            ->orderBy('id')
            ->get(['id', 'name'])
            ->map(fn (Group $group): array => [
                'id' => $group->id,
                'name' => $group->name,
                'entered' => (int) ($entered[$group->id] ?? 0) + ($added[$group->id] ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{bytes:string, mime:string}|null
     */
    public function faceImage(Employee $employee): ?array
    {
        $face = Face::query()->where('employee_id', $employee->id)->value('face');

        if (! is_string($face) || trim($face) === '') {
            return null;
        }

        if (str_starts_with($face, 'data:')) {
            $face = (string) substr($face, (int) strpos($face, ',') + 1);
        }

        $bytes = base64_decode($face, true);

        if ($bytes === false || $bytes === '') {
            return null;
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: '';

        return [
            'bytes' => $bytes,
            'mime' => str_starts_with($mime, 'image/') ? $mime : 'image/jpeg',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapPass(Report $report, ?callable $faceUrl = null): array
    {
        $employee = $report->employee;

        return [
            'id' => $report->id,
            'employee_name' => $employee?->name ?? $report->employee_name,
            'gender' => $employee?->gender,
            'country' => $employee?->country,
            'country_name' => $employee?->countryName(),
            'position' => $employee?->position,
            'department_name' => $employee?->department?->name,
            'group_id' => $employee?->group_id,
            'group_name' => $employee?->group?->name,
            'device_name' => $report->device?->name,
            'type' => $report->type,
            'type_label' => $report->type_label,
            'date' => $report->occurred_at?->format('Y-m-d'),
            'time' => $report->occurred_at?->format('H:i:s'),
            'face_url' => $employee !== null && $employee->face_exists
                ? ($faceUrl !== null ? $faceUrl($employee->id) : route('admin.live.face', $employee->id))
                : null,
        ];
    }
}
