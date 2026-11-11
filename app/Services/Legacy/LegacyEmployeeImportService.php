<?php

namespace App\Services\Legacy;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Face;
use App\Models\Group;
use App\Models\Schedule;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * Eski Biocontrol tizimidan bir martalik ko‘chirish: bo‘limlar, guruhlar, xodimlar va yuz rasmlari.
 * Kelgan-ketganlar (reports_*), barmoq izlari va qurilma bog‘lanishlari ko‘chirilmaydi.
 *
 * Eski dump avval vaqtinchalik MySQL bazaga yuklanadi; bu servis o‘sha bazadan o‘qiydi.
 *
 * - Bo‘lim nomi bo‘yicha topiladi, bo‘lmasa yaratiladi.
 * - Eski daraxtda har bo‘lim tepasida shu nomli guruh bor — u tashlab yuboriladi, ichidagilar bo‘lim ostiga chiqadi.
 * - Xodim JSHSHIR (card) bo‘yicha topiladi: bor bo‘lsa bo‘limi, guruhi, ismi va muddati eski tizimdagidek qilinadi
 *   (lavozim eski tizimda to‘ldirilgan bo‘lsagina, jadval o‘zgarmaydi).
 * - Yuz rasmi faqat xodimda rasm bo‘lmasa qo‘shiladi.
 * - Terminallarga hech narsa yozilmaydi: xodimlar keyin Sinxronizatsiya orqali yuboriladi.
 * - Hammasi bitta tranzaksiyada; $dryRun bo‘lsa oxirida bekor qilinadi (nima bo‘lishini ko‘rish uchun).
 */
class LegacyEmployeeImportService
{
    /** Eski tizimda 1 — erkak, 2 — ayol, 0 — ko‘rsatilmagan. */
    private const GENDERS = [
        1 => Employee::GENDER_MALE,
        2 => Employee::GENDER_FEMALE,
    ];

    /**
     * @param  list<int>  $departmentIds  eski tizimdagi ko‘chiriladigan bo‘limlar
     * @return array{
     *     departments: array{created:int, matched:int},
     *     groups: array{created:int, matched:int, skipped_roots:int},
     *     employees: array{created:int, updated:int, unchanged:int},
     *     faces: array{added:int, kept:int, missing:int, invalid:int},
     *     marked_expired: int,
     *     warnings: list<array{legacy_id:int, name:string, reason:string}>,
     *     dry_run: bool
     * }
     */
    public function import(string $connection, array $departmentIds, bool $dryRun = false): array
    {
        $legacy = DB::connection($connection);
        $departmentIds = array_values(array_unique(array_map('intval', $departmentIds)));

        $allDepartments = $legacy->table('departments')->pluck('name', 'id')
            ->map(fn ($name): string => trim((string) $name));

        $missing = array_diff($departmentIds, $allDepartments->keys()->map(fn ($id): int => (int) $id)->all());

        if ($departmentIds === [] || $missing !== []) {
            throw new InvalidArgumentException('Eski bazada bunday bo‘lim yo‘q: '.implode(', ', $missing ?: ['(bo‘sh ro‘yxat)']));
        }

        $tree = $this->resolveGroupTree($legacy, $allDepartments);

        $result = [
            'departments' => ['created' => 0, 'matched' => 0],
            'groups' => ['created' => 0, 'matched' => 0, 'skipped_roots' => 0],
            'employees' => ['created' => 0, 'updated' => 0, 'unchanged' => 0],
            'faces' => ['added' => 0, 'kept' => 0, 'missing' => 0, 'invalid' => 0],
            'marked_expired' => 0,
            'warnings' => [],
            'dry_run' => $dryRun,
        ];

        DB::beginTransaction();

        try {
            $departmentMap = $this->importDepartments($departmentIds, $allDepartments, $result);
            $groupMap = $this->importGroups($tree, $departmentMap, $result);
            $scheduleMap = $this->resolveSchedules($legacy);

            $legacy->table('employees')
                ->whereIn('department', $departmentIds)
                ->orderBy('id')
                ->chunk(100, function (Collection $rows) use ($legacy, $departmentMap, $groupMap, $tree, $scheduleMap, &$result): void {
                    $faces = $legacy->table('faces')
                        ->whereIn('employee', $rows->pluck('id')->all())
                        ->pluck('face', 'employee');

                    foreach ($rows as $row) {
                        $this->importEmployee($row, $faces->get($row->id), $departmentMap, $groupMap, $tree, $scheduleMap, $result);
                    }
                });

            $dryRun ? DB::rollBack() : DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();

            throw $exception;
        }

        return $result;
    }

    /**
     * Har bir eski guruh uchun: qaysi bo‘limga tegishli, ota-onasi kim va u bo‘lim nomidagi tepa guruhmi.
     * Eski bazada guruh o‘ziga o‘zi ota-ona bo‘lishi va bo‘limi 0 bo‘lishi mumkin — bo‘lim ildizdan olinadi.
     *
     * @param  Collection<int|string, string>  $allDepartments
     * @return array<int, array{name:string, parent:int, department:int, department_root:bool, depth:int}>
     */
    private function resolveGroupTree(ConnectionInterface $legacy, Collection $allDepartments): array
    {
        $rows = $legacy->table('groups')->get(['id', 'name', 'parent', 'department'])->keyBy('id');
        $departmentByName = $allDepartments->mapWithKeys(fn (string $name, $id): array => [$this->normalize($name) => (int) $id]);

        $parentOf = [];
        foreach ($rows as $id => $row) {
            $parent = (int) $row->parent;
            $parentOf[(int) $id] = ($parent === (int) $id || ! $rows->has($parent)) ? 0 : $parent;
        }

        $tree = [];
        foreach ($rows as $id => $row) {
            $id = (int) $id;

            // Ildizgacha yuramiz (halqa bo‘lsa to‘xtaymiz).
            $root = $id;
            $depth = 0;
            $seen = [$id => true];
            while ($parentOf[$root] !== 0 && ! isset($seen[$parentOf[$root]])) {
                $root = $parentOf[$root];
                $seen[$root] = true;
                $depth++;
            }

            $department = (int) $row->department;
            if ($department === 0 || ! $allDepartments->has($department)) {
                $rootDepartment = (int) $rows[$root]->department;
                $department = $allDepartments->has($rootDepartment) && $rootDepartment !== 0
                    ? $rootDepartment
                    : ($departmentByName[$this->normalize((string) $rows[$root]->name)] ?? 0);
            }

            $name = trim((string) $row->name);

            $tree[$id] = [
                'name' => $name,
                'parent' => $parentOf[$id],
                'department' => $department,
                'department_root' => $parentOf[$id] === 0
                    && $department !== 0
                    && $this->normalize($name) === $this->normalize((string) $allDepartments->get($department)),
                'depth' => $depth,
            ];
        }

        return $tree;
    }

    /**
     * @param  list<int>  $departmentIds
     * @param  Collection<int|string, string>  $allDepartments
     * @return array<int, int> eski bo‘lim ID → yangi bo‘lim ID
     */
    private function importDepartments(array $departmentIds, Collection $allDepartments, array &$result): array
    {
        $map = [];

        foreach ($departmentIds as $legacyId) {
            $name = (string) $allDepartments->get($legacyId);
            $existing = Department::query()
                ->get(['id', 'name'])
                ->first(fn (Department $department): bool => $this->normalize($department->name) === $this->normalize($name));

            if ($existing !== null) {
                $map[$legacyId] = $existing->id;
                $result['departments']['matched']++;

                continue;
            }

            $map[$legacyId] = Department::query()->create(['name' => $name])->id;
            $result['departments']['created']++;
        }

        return $map;
    }

    /**
     * Ota-onalar avval yaratiladi. Bo‘lim nomidagi tepa guruh tashlab yuboriladi — bolalari bo‘lim ostiga chiqadi.
     * Qayta ishga tushirilsa (bo‘lim, ota-ona, nom) bo‘yicha topiladi — takror guruh yaratilmaydi.
     *
     * @param  array<int, array{name:string, parent:int, department:int, department_root:bool, depth:int}>  $tree
     * @param  array<int, int>  $departmentMap
     * @return array<int, int> eski guruh ID → yangi guruh ID
     */
    private function importGroups(array $tree, array $departmentMap, array &$result): array
    {
        $selected = array_filter($tree, fn (array $group): bool => isset($departmentMap[$group['department']]));
        uasort($selected, fn (array $a, array $b): int => $a['depth'] <=> $b['depth']);

        $map = [];

        foreach ($selected as $legacyId => $group) {
            if ($group['department_root']) {
                $result['groups']['skipped_roots']++;

                continue;
            }

            $parent = $group['parent'];
            // Ota-ona boshqa bo‘limda yoki tashlab yuborilgan tepa guruh bo‘lsa — bo‘lim ostiga chiqadi.
            $parentId = ($parent !== 0 && ($tree[$parent]['department'] ?? 0) === $group['department'])
                ? ($map[$parent] ?? null)
                : null;

            $attributes = [
                'department_id' => $departmentMap[$group['department']],
                'parent_id' => $parentId,
                'name' => $group['name'],
            ];

            $existing = Group::query()->withoutGlobalScopes()->where($attributes)->first();

            if ($existing !== null) {
                $map[$legacyId] = $existing->id;
                $result['groups']['matched']++;

                continue;
            }

            $map[$legacyId] = Group::query()->withoutGlobalScopes()->create($attributes)->id;
            $result['groups']['created']++;
        }

        return $map;
    }

    /**
     * Eski jadval yangi tizimdagi umumiy jadvalga vaqtlari bo‘yicha bog‘lanadi; mos kelmasa umumiy jadval yaratiladi.
     *
     * @return array<int, int> eski jadval ID → yangi jadval ID
     */
    private function resolveSchedules(ConnectionInterface $legacy): array
    {
        $general = Schedule::query()->withoutGlobalScopes()->whereNull('department_id')->orderBy('id')->get();
        $map = [];

        foreach ($legacy->table('schedules')->get(['id', 'name', 'enter_time', 'exit_time']) as $row) {
            $enter = substr((string) $row->enter_time, 0, 5);
            $exit = substr((string) $row->exit_time, 0, 5);

            $match = $general->first(fn (Schedule $schedule): bool => substr((string) $schedule->getRawOriginal('enter_time'), 0, 5) === $enter
                && substr((string) $schedule->getRawOriginal('exit_time'), 0, 5) === $exit);

            if ($match === null) {
                $match = Schedule::query()->withoutGlobalScopes()->create([
                    'department_id' => null,
                    'name' => trim((string) $row->name) ?: "{$enter}–{$exit}",
                    'enter_time' => $enter,
                    'exit_time' => $exit,
                ]);
                $general->push($match);
            }

            $map[(int) $row->id] = $match->id;
        }

        return $map;
    }

    /**
     * @param  array<int, int>  $departmentMap
     * @param  array<int, int>  $groupMap
     * @param  array<int, array{name:string, parent:int, department:int, department_root:bool, depth:int}>  $tree
     * @param  array<int, int>  $scheduleMap
     */
    private function importEmployee(
        object $row,
        ?string $face,
        array $departmentMap,
        array $groupMap,
        array $tree,
        array $scheduleMap,
        array &$result,
    ): void {
        $legacyDepartment = (int) $row->department;
        $name = trim((string) $row->name);
        $card = trim((string) $row->card);
        $validTo = Carbon::parse($row->valid_to_dt);

        $groupId = $this->resolveEmployeeGroup($row, $legacyDepartment, $groupMap, $tree, $name, $result);

        $values = [
            'name' => $name,
            'department_id' => $departmentMap[$legacyDepartment],
            'group_id' => $groupId,
            'valid_from_dt' => Carbon::parse($row->valid_from_dt),
            'valid_to_dt' => $validTo,
        ];

        $position = trim((string) $row->position);
        if ($position !== '') {
            $values['position'] = $position;
        }

        $gender = self::GENDERS[(int) $row->gender] ?? null;

        // JSHSHIR butun tizimda yagona: boshqa bo‘lim yoki "Qurilmadan" dagi xodim ham topiladi.
        $employee = Employee::query()->withoutGlobalScopes()->where('card', $card)->first();

        if ($employee === null) {
            $employee = Employee::query()->withoutGlobalScopes()->create($values + [
                'card' => $card,
                'gender' => $gender,
                'schedule_id' => $scheduleMap[(int) $row->schedule] ?? $scheduleMap[array_key_first($scheduleMap)],
            ]);
            $result['employees']['created']++;
        } else {
            if ($employee->gender === null && $gender !== null) {
                $values['gender'] = $gender;
            }

            $employee->fill($values);

            if ($employee->isDirty()) {
                $employee->save();
                $result['employees']['updated']++;
            } else {
                $result['employees']['unchanged']++;
            }
        }

        // Muddati allaqachon tugagan xodim uchun Telegram'ga eski xabar ketmasin.
        if ($validTo->isPast() && $employee->expiry_notified_at === null) {
            $employee->forceFill(['expiry_warned_at' => now(), 'expiry_notified_at' => now()])->save();
            $result['marked_expired']++;
        }

        $this->importFace($employee, $face, $result);
    }

    /**
     * @param  array<int, int>  $groupMap
     * @param  array<int, array{name:string, parent:int, department:int, department_root:bool, depth:int}>  $tree
     */
    private function resolveEmployeeGroup(object $row, int $legacyDepartment, array $groupMap, array $tree, string $name, array &$result): ?int
    {
        $legacyGroup = (int) $row->group_id;

        if ($legacyGroup === 0) {
            return null;
        }

        $group = $tree[$legacyGroup] ?? null;

        if ($group === null) {
            $result['warnings'][] = ['legacy_id' => (int) $row->id, 'name' => $name, 'reason' => "guruh #{$legacyGroup} eski bazada yo‘q — guruhsiz qoldi"];

            return null;
        }

        if ($group['department'] !== $legacyDepartment) {
            $result['warnings'][] = ['legacy_id' => (int) $row->id, 'name' => $name, 'reason' => "«{$group['name']}» guruhi boshqa bo‘limda — guruhsiz qoldi"];

            return null;
        }

        // Bo‘lim nomidagi tepa guruhda turgan xodim bo‘limning o‘zida qoladi.
        return $group['department_root'] ? null : ($groupMap[$legacyGroup] ?? null);
    }

    private function importFace(Employee $employee, ?string $face, array &$result): void
    {
        if (Face::query()->where('employee_id', $employee->id)->exists()) {
            $result['faces']['kept']++;

            return;
        }

        $face = $face !== null ? preg_replace('/\s+/', '', $face) : '';

        if ($face === '') {
            $result['faces']['missing']++;

            return;
        }

        $binary = base64_decode($face, true);

        if ($binary === false || @getimagesizefromstring($binary) === false) {
            $result['faces']['invalid']++;

            return;
        }

        Face::query()->create(['employee_id' => $employee->id, 'face' => $face]);
        $result['faces']['added']++;
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value)));
    }
}
