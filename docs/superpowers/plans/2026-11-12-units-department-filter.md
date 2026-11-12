# Tarkibiy bo‘linmalar: bo‘lim filtri — reja

Spec: [2026-11-12-units-department-filter-design.md](../specs/2026-11-12-units-department-filter-design.md)

1. **Testlar (qizil).**
   - `tests/Feature/Admin/UnitBoardServiceTest.php`: ikki bo‘lim, `build(now(), false, $id)` — faqat shu bo‘lim
     kartochkalari, `counts` shu bo‘lim bo‘yicha.
   - `tests/Feature/Admin/UnitPageTest.php`: `?department_id=` bilan board va `filters`, `departments`,
     `departmentLocked`; cheklangan foydalanuvchi boshqa bo‘lim id si bilan bo‘sh board; `department_id=abc` xato.
2. **Servis.** `UnitBoardService::build(CarbonInterface $now, bool $withFaces, ?int $departmentId = null)`;
   `employees()` so‘roviga `->when($departmentId !== null, ...)`. `withoutGlobalScopes()` yo‘q.
3. **Controller.** `UnitController::index()` — `$request->validate($this->filterRules())`; props `board`,
   `filters`, `departments` (`Department::visibleTo`), `departmentLocked`.
4. **Sahifa.** `resources/js/Pages/Admin/Units/Index.jsx`: `Segmented` yonida `Select` (faqat `!departmentLocked`),
   `router.get(route('admin.units.index'), …, { preserveState, preserveScroll, replace })`; bo‘lim almashganda
   panel yopiladi.
5. **Tekshiruv.** `COMPOSER_PROCESS_TIMEOUT=0 composer test`, o‘zgargan PHP fayllarga `vendor/bin/pint`, brauzerda
   localhost (`laravel` + `vite` preview): tanlov, sonlar, 30 soniyalik yangilanishdan keyin tanlov saqlanishi.
