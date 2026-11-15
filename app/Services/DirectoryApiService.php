<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Group;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Ma'lumotnoma API ning o'qish qismi (docs/directory-api.md).
 *
 * Foydalanuvchi yo'q — so'rov Basic auth bilan keladi, sessiya ochilmaydi.
 * Shuning uchun bo'lim cheklovi (BelongsToDepartmentScope) ataylab olib
 * tashlanadi: tashqi tizim butun tashkilotni ko'rishi kerak, va brauzerda
 * ochiq sessiya javobni o'zgartirib yubormasligi kerak.
 */
class DirectoryApiService
{
    public const LIMIT_DEFAULT = 200;

    public const LIMIT_MAX = 1000;

    /**
     * Bo'limlar (tashkilot darajasi) — har birida nechta guruh va xodim borligi bilan.
     *
     * @return list<array<string, mixed>>
     */
    public function departments(): array
    {
        return $this->bolimlarSorovi()
            ->orderBy('name')
            ->get()
            ->map(fn (Department $bolim): array => $this->bolim($bolim))
            ->all();
    }

    /**
     * Bitta bo'lim id bo'yicha — ro'yxatdagi bilan bir xil maydonlar.
     * Topilmasa `null`.
     *
     * @return array<string, mixed>|null
     */
    public function department(int $id): ?array
    {
        $bolim = $this->bolimlarSorovi()->find($id);

        return $bolim ? $this->bolim($bolim) : null;
    }

    /**
     * @return Builder<Department>
     */
    private function bolimlarSorovi(): Builder
    {
        return Department::query()
            ->withCount([
                'employees' => fn (Builder $q) => $q->withoutGlobalScopes(),
                'groups' => fn (Builder $q) => $q->withoutGlobalScopes(),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function bolim(Department $bolim): array
    {
        return [
            'id' => $bolim->id,
            'name' => $bolim->name,
            'employees_count' => (int) $bolim->employees_count,
            'groups_count' => (int) $bolim->groups_count,
        ];
    }

    /**
     * Guruhlar (tarkibiy bo'linmalar) — daraxt `parent_id` orqali.
     *
     * @return list<array<string, mixed>>
     */
    public function groups(?int $departmentId = null): array
    {
        return Group::query()
            ->withoutGlobalScopes()
            ->when($departmentId !== null, fn (Builder $q) => $q->where('department_id', $departmentId))
            ->withCount(['employees' => fn (Builder $q) => $q->withoutGlobalScopes()])
            ->orderBy('department_id')
            ->orderBy('name')
            ->get()
            ->map(fn (Group $guruh): array => [
                'id' => $guruh->id,
                'name' => $guruh->name,
                'department_id' => $guruh->department_id,
                'parent_id' => $guruh->parent_id,
                'employees_count' => (int) $guruh->employees_count,
            ])
            ->all();
    }

    /**
     * Xodimlar. `updated_since` — oxirgi tortishdan keyin o'zgarganlarini olish uchun.
     *
     * @return array{total: int, page: int, limit: int, has_more: bool, items: list<array<string, mixed>>}
     */
    public function employees(
        ?int $departmentId = null,
        ?CarbonInterface $updatedSince = null,
        int $limit = self::LIMIT_DEFAULT,
        int $page = 1,
    ): array {
        $limit = max(1, min($limit, self::LIMIT_MAX));
        $page = max(1, $page);

        $query = Employee::query()
            ->withoutGlobalScopes()
            ->with([
                'department' => fn ($q) => $q->select('id', 'name'),
                'group' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'name'),
            ])
            ->when($departmentId !== null, fn (Builder $q) => $q->where('employees.department_id', $departmentId))
            ->when($updatedSince !== null, fn (Builder $q) => $q->where('employees.updated_at', '>=', $updatedSince))
            ->orderBy('employees.id');

        $total = (clone $query)->toBase()->getCountForPagination();

        $xodimlar = $query
            ->forPage($page, $limit)
            ->get()
            ->map(fn (Employee $xodim): array => $this->xodim($xodim))
            ->all();

        return [
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'has_more' => $page * $limit < $total,
            'items' => $xodimlar,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function xodim(Employee $xodim): array
    {
        $holat = $xodim->validityStatus();

        return [
            'id' => $xodim->id,
            // JSHSHIR. Tashqi tizim xodimni aynan shu bo'yicha moslashtiradi —
            // shuning uchun u javobdan olib tashlanmaydi. Qurilmadan tortilgan
            // eski yozuvlarda 14 xonali bo'lmasligi mumkin.
            'card' => $xodim->card,
            'name' => $xodim->name,
            'last_name' => $xodim->last_name,
            'first_name' => $xodim->first_name,
            'middle_name' => $xodim->middle_name,
            'gender' => $xodim->gender,
            'position' => $xodim->position,
            'department_id' => $xodim->department_id,
            'department' => $xodim->department?->name,
            'group_id' => $xodim->group_id,
            'group' => $xodim->group?->name,
            'valid_from' => $xodim->valid_from_dt?->format('Y-m-d H:i:s'),
            'valid_to' => $xodim->valid_to_dt?->format('Y-m-d H:i:s'),
            'validity' => $holat,
            // Muddati tugashiga yaqin xodim ham ishlayapti — `active` faqat
            // tugagan yoki hali boshlanmagan xodimda `false` bo'ladi.
            'active' => ! in_array($holat, [Employee::VALIDITY_EXPIRED, Employee::VALIDITY_UPCOMING], true),
            'updated_at' => $xodim->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
