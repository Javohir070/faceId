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
    /**
     * Bugungi oxirgi o‘tishlar (kirish va chiqish) — eng yangisi birinchi. Bo‘lim scope Report modelida qo‘llanadi.
     * Tartib bazaga tushish ketma-ketligi bo‘yicha: qurilma soati noto‘g‘ri bo‘lsa ham ro‘yxat aralashmaydi.
     * Qurilma ulanganda eski (oflayn to‘plangan) hodisalarni ham yuboradi — ular monitorga chiqmaydi.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function recentPasses(int $limit = 7): Collection
    {
        return Report::query()
            ->whereBetween('occurred_at', [now()->startOfDay(), now()->endOfDay()])
            ->with([
                // Yuz rasmi (longText) yuklanmaydi — faqat bor/yo‘qligi.
                'employee' => fn ($query) => $query
                    ->select(['id', 'name', 'position', 'department_id', 'group_id'])
                    ->withExists('face'),
                'employee.department:id,name',
                'employee.group:id,name',
                'device:id,name',
            ])
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (Report $report): array => $this->mapPass($report))
            ->values();
    }

    /**
     * Bugungi ko‘rsatkichlar — o‘tishlar emas, takrorlanmas xodimlar soni.
     *
     * @return array{entered:int, exited:int, male:int, female:int, groups:list<array{id:int, name:string, entered:int}>}
     */
    public function todayStats(): array
    {
        $range = [now()->startOfDay(), now()->endOfDay()];

        $people = fn (int $type): int => Report::query()
            ->where('type', $type)
            ->whereBetween('occurred_at', $range)
            ->whereNotNull('employee_id')
            ->distinct()
            ->count('employee_id');

        $enteredByGender = fn (int $gender): int => Employee::query()
            ->where('gender', $gender)
            ->whereHas('reports', fn ($query) => $query->entry()->whereBetween('occurred_at', $range))
            ->count();

        return [
            'entered' => $people(Report::TYPE_ENTRY),
            'exited' => $people(Report::TYPE_EXIT),
            'male' => $enteredByGender(Employee::GENDER_MALE),
            'female' => $enteredByGender(Employee::GENDER_FEMALE),
            'groups' => $this->enteredByGroup($range),
        ];
    }

    /**
     * Har bir guruhdan bugun kirgan xodimlar soni (OAV, Homiy, Ishtirokchi va h.k.).
     * Bugun hech kim kirmagan guruh ham 0 bilan qaytadi — monitordagi plitkalar kun davomida o‘zgarmaydi.
     *
     * @param  array{0:CarbonInterface, 1:CarbonInterface}  $range
     * @return list<array{id:int, name:string, entered:int}>
     */
    public function enteredByGroup(array $range): array
    {
        $entered = Employee::query()
            ->whereNotNull('group_id')
            ->whereHas('reports', fn ($query) => $query->entry()->whereBetween('occurred_at', $range))
            ->groupBy('group_id')
            ->selectRaw('group_id, count(*) as total')
            ->pluck('total', 'group_id');

        return Group::query()
            ->orderBy('id')
            ->get(['id', 'name'])
            ->map(fn (Group $group): array => [
                'id' => $group->id,
                'name' => $group->name,
                'entered' => (int) ($entered[$group->id] ?? 0),
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
    private function mapPass(Report $report): array
    {
        $employee = $report->employee;

        return [
            'id' => $report->id,
            'employee_name' => $employee?->name ?? $report->employee_name,
            'position' => $employee?->position,
            'department_name' => $employee?->department?->name,
            'group_name' => $employee?->group?->name,
            'device_name' => $report->device?->name,
            'type' => $report->type,
            'type_label' => $report->type_label,
            'date' => $report->occurred_at?->format('Y-m-d'),
            'time' => $report->occurred_at?->format('H:i:s'),
            'face_url' => $employee !== null && $employee->face_exists
                ? route('admin.live.face', $employee->id)
                : null,
        ];
    }
}
