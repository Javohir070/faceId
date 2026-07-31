# Employees Excel Export Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Admin → Xodimlar (`/admin/employees`) sahifasida joriy filtrlarga mos barcha xodimlarni `.xlsx` fayl sifatida yuklab olish tugmasini qo'shish.

**Architecture:** `EmployeeRepository`dagi filtr logikasi `filteredQuery()` metodiga chiqariladi (`paginate()` ham shundan foydalanadi). `App\Exports\EmployeesExport` (FromQuery + WithHeadings + WithMapping + ShouldAutoSize) shu query'ni Excel qatorlariga aylantiradi. Xuddi Reports export bilan bir xil pattern (`docs/superpowers/plans/2026-07-31-reports-excel-export.md`).

**Tech Stack:** Laravel 12, `maatwebsite/excel` (allaqachon o'rnatilgan), Inertia.js + React 19, PHPUnit.

## Global Constraints

- Export faqat `employees` permission'iga ega foydalanuvchilar uchun.
- Export joriy ekranda qo'llanilgan filtrlarga (search, department_id, schedule_id, device_id, has_face) mos **barcha** yozuvlarni o'z ichiga oladi, sahifalashsiz.
- Ustunlar: №, F.I.Sh., Karta, Jins, Lavozim, Bo'lim, Jadval, Yuz, Amal qilish (dan), Amal qilish (gacha).
- Yuz rasmi (face) Excel'ga tasvir sifatida joylanmaydi — faqat bor/yo'q holati.
- `Route::resource('employees', ...)` `GET employees/{employee}` (show) route'ini ro'yxatdan o'tkazadi — `employees/export` shu resource route'idan **oldin** ro'yxatdan o'tkazilishi shart, aks holda Laravel "export"ni `{employee}` parametri deb tushunib show() metodiga yuboradi (mavjud `employees/pull` route'lari xuddi shu sababdan resource'dan oldin turibdi — shu patternga ergashiladi).

---

### Task 1: `EmployeeRepository` — filtr logikasini `filteredQuery()` metodiga chiqarish (refactor)

**Files:**
- Modify: `app/Interfaces/EmployeeRepositoryInterface.php`
- Modify: `app/Repositories/EmployeeRepository.php`
- Modify: `app/Services/EmployeeService.php`

**Interfaces:**
- Produces: `EmployeeRepositoryInterface::filteredQuery(array $filters = []): Builder`, `EmployeeService::getFilteredQuery(array $filters = []): Builder` — Task 2 export() shulardan foydalanadi.

Bu — sof refactor, yangi test yozilmaydi — mavjud `tests/Feature/Admin/EmployeeAdminTest.php` regressiya himoyasi vazifasini bajaradi.

- [ ] **Step 1: Refactordan oldin mavjud testni yashil holatda tasdiqlash**

Run: `php artisan test --filter=EmployeeAdminTest`
Expected: PASS

- [ ] **Step 2: `EmployeeRepositoryInterface`ga metod qo'shish**

`app/Interfaces/EmployeeRepositoryInterface.php` faylida, `use` bayonotlariga qo'shing:

```php
use Illuminate\Database\Eloquent\Builder;
```

`paginate()` metodi e'lonidan oldin qo'shing:

```php
    /**
     * @param  array{search?:?string, department_id?:?int, schedule_id?:?int, device_id?:?int, has_face?:?string}  $filters
     * @return Builder<Employee>
     */
    public function filteredQuery(array $filters = []): Builder;

```

- [ ] **Step 3: `EmployeeRepository`da `filteredQuery()` va `paginate()`ni refactor qilish**

`app/Repositories/EmployeeRepository.php` faylida, `use` bayonotlariga qo'shing:

```php
use Illuminate\Database\Eloquent\Builder;
```

`public function paginate(...)` metodining butun tanasini quyidagicha almashtiring:

```php
    /**
     * @param  array{search?:?string, department_id?:?int, schedule_id?:?int, device_id?:?int, has_face?:?string}  $filters
     * @return Builder<Employee>
     */
    public function filteredQuery(array $filters = []): Builder
    {
        $query = Employee::query()
            ->with(['department:id,name', 'schedule:id,name', 'face:id,employee_id']);

        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('card', 'like', '%'.$search.'%');
            });
        }

        if (! empty($filters['department_id'])) {
            $query->where('department_id', $filters['department_id']);
        }

        if (! empty($filters['schedule_id'])) {
            $query->where('schedule_id', $filters['schedule_id']);
        }

        if (! empty($filters['device_id'])) {
            $query->whereHas(
                'devices',
                fn ($deviceQuery) => $deviceQuery->where('devices.id', $filters['device_id']),
            );
        }

        if (($filters['has_face'] ?? null) === '1') {
            $query->whereHas('face');
        } elseif (($filters['has_face'] ?? null) === '0') {
            $query->whereDoesntHave('face');
        }

        return $query->latest();
    }

    public function paginate(int $perPage = 12, array $filters = []): LengthAwarePaginator
    {
        return $this->filteredQuery($filters)
            ->paginate($perPage)
            ->withQueryString();
    }
```

- [ ] **Step 4: `EmployeeService`ga `getFilteredQuery()` qo'shish**

`app/Services/EmployeeService.php` faylida, `use` bayonotlariga qo'shing:

```php
use Illuminate\Database\Eloquent\Builder;
```

`getSchedulesForForm()` metodidan keyin qo'shing:

```php
    /**
     * @param  array{search?:?string, department_id?:?int, schedule_id?:?int, device_id?:?int, has_face?:?string}  $filters
     * @return Builder<Employee>
     */
    public function getFilteredQuery(array $filters = []): Builder
    {
        return $this->employeeRepository->filteredQuery($filters);
    }
```

- [ ] **Step 5: Testni qayta ishga tushirib, hali ham yashil ekanini tasdiqlash**

Run: `php artisan test --filter=EmployeeAdminTest`
Expected: PASS (xatti-harakat o'zgarmagan)

- [ ] **Step 6: To'liq test suite bilan regressiya tekshiruvi**

Run: `php artisan test`
Expected: avvalgi natija bilan bir xil (faqat eskirgan 2 ta `SanctumUserApiTest` FAIL qolishi mumkin).

- [ ] **Step 7: Commit**

```bash
git add app/Interfaces/EmployeeRepositoryInterface.php app/Repositories/EmployeeRepository.php app/Services/EmployeeService.php
git commit -m "EmployeeRepository filtr logikasini umumiy metodga chiqarish"
```

---

### Task 2: Backend — `EmployeesExport` klassi, `export()` endpoint va testlar

**Files:**
- Create: `app/Exports/EmployeesExport.php`
- Modify: `app/Http/Controllers/EmployeeController.php`
- Modify: `routes/web.php`
- Modify: `resources/js/routes.js`
- Test: `tests/Feature/Admin/EmployeeExportTest.php`

**Interfaces:**
- Consumes: `EmployeeService::getFilteredQuery(array): Builder` (Task 1).
- Produces: `GET /admin/employees/export` route nomi `admin.employees.export`; `App\Exports\EmployeesExport::__construct(Builder $query)`.

- [ ] **Step 1: Muvaffaqiyatli yuklab olish testini yozish**

`tests/Feature/Admin/EmployeeExportTest.php` faylini yarating:

```php
<?php

namespace Tests\Feature\Admin;

use App\Exports\EmployeesExport;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class EmployeeExportTest extends TestCase
{
    use InteractsWithPermissions;
    use RefreshDatabase;

    public function test_authorized_user_can_download_filtered_employees_excel(): void
    {
        Excel::fake();

        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['employees'],
        ]);

        $department = Department::factory()->create(['name' => 'IT']);
        $otherDepartment = Department::factory()->create(['name' => 'HR']);
        Schedule::factory()->create();

        Employee::factory()->create(['name' => 'Ali Valiyev', 'department_id' => $department->id]);
        Employee::factory()->create(['name' => 'Vali Aliyev', 'department_id' => $otherDepartment->id]);

        $this->actingAs($admin)
            ->get(route('admin.employees.export', ['department_id' => $department->id]))
            ->assertOk();

        Excel::assertDownloaded('xodimlar.xlsx', function (EmployeesExport $export) {
            return $export->query()->count() === 1;
        });
    }

    public function test_unauthorized_user_cannot_download_employees_excel(): void
    {
        $user = $this->createUserWithPermissions([
            config('menu_permissions.home'),
        ]);

        $this->actingAs($user)
            ->get(route('admin.employees.export'))
            ->assertForbidden();
    }
}
```

- [ ] **Step 2: Testni ishga tushirib, kutilgan sababdan yiqilishini tasdiqlash**

Run: `php artisan test --filter=EmployeeExportTest`
Expected: FAIL — `route('admin.employees.export')` mavjud emasligi haqida `RouteNotFoundException`.

- [ ] **Step 3: `EmployeesExport` export klassini yaratish**

`app/Exports/EmployeesExport.php` faylini yarating:

```php
<?php

namespace App\Exports;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class EmployeesExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    private int $rowNumber = 0;

    /**
     * @param  Builder<Employee>  $query
     */
    public function __construct(private readonly Builder $query)
    {
    }

    /**
     * @return Builder<Employee>
     */
    public function query(): Builder
    {
        return $this->query;
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['№', 'F.I.Sh.', 'Karta', 'Jins', 'Lavozim', "Bo'lim", 'Jadval', 'Yuz', 'Amal qilish (dan)', 'Amal qilish (gacha)'];
    }

    /**
     * @param  Employee  $employee
     * @return array<int, mixed>
     */
    public function map($employee): array
    {
        $this->rowNumber++;

        return [
            $this->rowNumber,
            $employee->name,
            $employee->card,
            $employee->genderLabel(),
            $employee->position ?? '—',
            $employee->department?->name ?? '—',
            $employee->schedule?->name ?? '—',
            $employee->face !== null ? 'Bor' : "Yo'q",
            $employee->valid_from_dt?->format('Y-m-d H:i'),
            $employee->valid_to_dt?->format('Y-m-d H:i'),
        ];
    }
}
```

- [ ] **Step 4: `EmployeeController`ga `export()` va `filterRules()` metodlarini qo'shish**

`app/Http/Controllers/EmployeeController.php` faylida, `use` bayonotlariga qo'shing:

```php
use App\Exports\EmployeesExport;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
```

`index()` metodining boshidagi inline validatsiya massivini almashtiring — hozir shunday:

```php
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'schedule_id' => ['nullable', 'integer', 'exists:schedules,id'],
            'device_id' => ['nullable', 'integer', 'exists:devices,id'],
            'has_face' => ['nullable', 'in:0,1'],
        ]);
```

buni:

```php
        $filters = $request->validate($this->filterRules());
```

Klassning yopilish qavsidan oldin (oxirgi metod — `destroy()`dan keyin) qo'shing:

```php
    public function export(Request $request): BinaryFileResponse
    {
        $filters = $request->validate($this->filterRules());

        $query = $this->employeeService->getFilteredQuery($filters);

        return Excel::download(new EmployeesExport($query), 'xodimlar.xlsx');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function filterRules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'schedule_id' => ['nullable', 'integer', 'exists:schedules,id'],
            'device_id' => ['nullable', 'integer', 'exists:devices,id'],
            'has_face' => ['nullable', 'in:0,1'],
        ];
    }
```

- [ ] **Step 5: Route qo'shish — resource route'idan OLDIN**

`routes/web.php` faylida, `employees/pull` POST route'idan keyin, **`Route::resource('employees', EmployeeController::class)` qatoridan oldin** qo'shing:

```php
        Route::get('employees/export', [EmployeeController::class, 'export'])
            ->middleware('permission:'.$menuPermissions['employees'])
            ->name('employees.export');
```

Bu tartib muhim: `Route::resource()` `GET employees/{employee}` (show) route'ini ro'yxatdan o'tkazadi — agar `employees/export` shundan keyin qo'yilsa, Laravel "export"ni `{employee}` parametri deb tushunib qoladi.

- [ ] **Step 6: `resources/js/routes.js`ga route builder qo'shish**

`'admin.employees.pull.store': () => '/admin/employees/pull',` qatoridan keyin qo'shing:

```js
    'admin.employees.export': () => '/admin/employees/export',
```

- [ ] **Step 7: Testni qayta ishga tushirib, o'tganini tasdiqlash**

Run: `php artisan test --filter=EmployeeExportTest`
Expected: PASS (2 ta test)

- [ ] **Step 8: To'liq test suite bilan regressiya tekshiruvi**

Run: `php artisan test`
Expected: avvalgi natija + 2 ta yangi PASS test (faqat eskirgan 2 ta `SanctumUserApiTest` FAIL qolishi mumkin).

- [ ] **Step 9: Commit**

```bash
git add app/Exports/EmployeesExport.php app/Http/Controllers/EmployeeController.php routes/web.php resources/js/routes.js tests/Feature/Admin/EmployeeExportTest.php
git commit -m "Xodimlar uchun Excel export backend qismini qo'shish"
```

---

### Task 3: Frontend — "Excel yuklab olish" tugmasi

**Files:**
- Modify: `resources/js/Pages/Admin/Employees/Index.jsx`

**Interfaces:**
- Consumes: `route('admin.employees.export')` (Task 2), komponentning mavjud `filters` propi.

- [ ] **Step 1: Export URL yaratuvchi funksiya va tugmani qo'shish**

`resources/js/Pages/Admin/Employees/Index.jsx` faylida, `resetFilters` funksiyasidan keyin, `return (` dan oldin qo'shing:

```jsx
    const exportHref = () => {
        const payload = Object.fromEntries(
            Object.entries(filters).filter(([, value]) => value !== null && value !== undefined && value !== ''),
        );
        const query = new URLSearchParams(payload).toString();
        const base = route('admin.employees.export');

        return query ? `${base}?${query}` : base;
    };
```

`PageHeader` blokidagi `actions` hozir shunday:

```jsx
                actions={(
                    <div className="flex flex-wrap items-center gap-2">
                        <Link
                            href={route('admin.employees.pull')}
                            className="inline-flex h-11 items-center rounded-lg border border-line px-4 text-sm font-semibold text-ink hover:bg-surface-muted"
                        >
                            Qurilmadan tortish
                        </Link>
```

Buni quyidagicha almashtiring (yangi `<a>` "Qurilmadan tortish"dan oldin qo'shiladi):

```jsx
                actions={(
                    <div className="flex flex-wrap items-center gap-2">
                        <a
                            href={exportHref()}
                            className="inline-flex h-11 items-center rounded-lg border border-line px-4 text-sm font-semibold text-ink hover:bg-surface-muted"
                        >
                            Excel yuklab olish
                        </a>
                        <Link
                            href={route('admin.employees.pull')}
                            className="inline-flex h-11 items-center rounded-lg border border-line px-4 text-sm font-semibold text-ink hover:bg-surface-muted"
                        >
                            Qurilmadan tortish
                        </Link>
```

- [ ] **Step 2: Dev server ishlayotganini tekshirish**

Agar `composer dev` fon jarayoni ishlamayotgan bo'lsa, ishga tushiring. Vite HMR o'zgarishlarni avtomatik qabul qilishi kerak.

- [ ] **Step 3: Brauzerda qo'lda tekshirish**

1. `http://127.0.0.1:8000/admin/employees` sahifasiga kiring.
2. Bir nechta filtr qo'ying (masalan, Bo'lim tanlang) va "Filtrlash" bosing.
3. "Excel yuklab olish" tugmasini bosing.
4. Yuklab olingan `.xlsx` faylni tekshiring:
   - Sarlavha qatori: №, F.I.Sh., Karta, Jins, Lavozim, Bo'lim, Jadval, Yuz, Amal qilish (dan), Amal qilish (gacha).
   - Faqat qo'yilgan filtrga mos qatorlar borligini.
5. Filtrsiz holatda ham tugmani bosib, barcha xodimlar tushishini tekshiring.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/Admin/Employees/Index.jsx
git commit -m "Xodimlar sahifasiga Excel yuklab olish tugmasini qo'shish"
```

---

## Yakuniy tekshiruv

- [ ] `php artisan test` — to'liq suite yashil (eskirgan 2 ta SanctumUserApiTest testidan tashqari).
- [ ] Brauzerda `/admin/employees` sahifasida tugma ko'rinadi va ishlaydi.
- [ ] `employees` ruxsati bo'lmagan foydalanuvchi `/admin/employees/export`ga to'g'ridan-to'g'ri kirsa 403 oladi.
