<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Services\Units\UnitBoardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tarkibiy bo‘linmalar: guruhlar bo‘yicha kim hozir binoda. Faqat o‘qiydi; sahifa `board` ni 30 soniyada qayta so‘raydi.
 */
class UnitController extends Controller
{
    public function __construct(
        private readonly UnitBoardService $board,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate($this->filterRules());
        $departmentId = isset($filters['department_id']) ? (int) $filters['department_id'] : null;

        // Yuz rasmi `admin.employees.face` orqali keladi va u faqat Xodimlar ruxsatini qabul qiladi.
        $withFaces = $request->user()->can(config('menu_permissions.resources')['employees']);

        return Inertia::render('Admin/Units/Index', [
            'board' => fn () => $this->board->build(now(), $withFaces, $departmentId),
            'filters' => ['department_id' => $departmentId],
            'departments' => fn () => Department::query()->visibleTo($request->user())->orderBy('name')->get(['id', 'name']),
            // Bo‘limga cheklangan foydalanuvchida bitta bo‘lim bor — tanlov sahifada yashiriladi.
            'departmentLocked' => $request->user()->isDepartmentScoped(),
        ]);
    }

    /**
     * @return array<string, list<string>>
     */
    private function filterRules(): array
    {
        return [
            // `exists` yo‘q (Davomatdagidek): topilmagan yoki ko‘rinmaydigan bo‘lim bo‘sh sahifa beradi.
            'department_id' => ['nullable', 'integer'],
        ];
    }
}
