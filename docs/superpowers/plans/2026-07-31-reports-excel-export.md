# Reports Excel Export Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Admin → Hisobotlar (`/admin/reports`) sahifasida joriy filtrlarga mos barcha yozuvlarni `.xlsx` fayl sifatida yuklab olish tugmasini qo'shish.

**Architecture:** `maatwebsite/excel` paketi orqali `App\Exports\ReportsExport` (FromQuery + WithHeadings + WithMapping + ShouldAutoSize) yaratiladi. `ReportController`dagi filtr+query qurish logikasi `index()` va yangi `export()` o'rtasida umumiy private metodga chiqariladi (DRY). Frontend'da mavjud filtr state'idan foydalanib oddiy `<a href>` orqali fayl yuklanadi.

**Tech Stack:** Laravel 12 (PHP 8.2+), `maatwebsite/excel` (PhpSpreadsheet asosida), Inertia.js + React 19, PHPUnit.

## Global Constraints

- Export faqat `reports` permission'iga ega foydalanuvchilar uchun (mavjud `config('menu_permissions.resources')['reports']`, spec: "Ruxsat: mavjud `reports` permission").
- Export joriy ekranda qo'llanilgan filtrlarga mos **barcha** yozuvlarni o'z ichiga oladi, sahifalashsiz (spec: "Qamrov").
- Ustunlar aniq shu tartibda: №, Ism, Bo'lim, Qurilma, Tur, Sana va vaqt (spec: "Ustunlar").
- Yangi format/queue-based generatsiya qo'shilmaydi — sinxron `.xlsx` download yetarli (spec: "Chegaralar (YAGNI)").
- Loyihada JS test runner yo'q (`package.json`da yo'q) — frontend qismi qo'lda (dev server + brauzer) tekshiriladi, avtomatik JS test yozilmaydi.

---

### Task 1: `maatwebsite/excel` paketini o'rnatish

**Files:**
- Modify: `composer.json`, `composer.lock` (composer require orqali avtomatik)

**Interfaces:**
- Produces: `Maatwebsite\Excel\Facades\Excel` fasadi va `Maatwebsite\Excel\Concerns\{FromQuery,WithHeadings,WithMapping,ShouldAutoSize}` interfeyslari — Task 3 shulardan foydalanadi.

- [ ] **Step 1: Paketni o'rnatish**

Run: `composer require maatwebsite/excel`

- [ ] **Step 2: Mavjud test suite buzilmaganini tekshirish**

Run: `php artisan test`
Expected: barcha testlar avvalgidek (yangi paket hech narsani buzmasligi kerak — faqat `SanctumUserApiTest`dagi 2 ta eskirgan, ushbu ishga aloqasi yo'q test FAIL bo'lishi mumkin, boshqa hammasi PASS).

- [ ] **Step 3: Commit**

```bash
git add composer.json composer.lock
git commit -m "Hisobotlar excel export uchun maatwebsite/excel paketini qo'shish"
```

---

### Task 2: `ReportController` — filtr query qurish logikasini umumiy metodga chiqarish (refactor)

**Files:**
- Modify: `app/Http/Controllers/ReportController.php`

**Interfaces:**
- Consumes: `App\Models\Report` (mavjud), `Illuminate\Database\Eloquent\Builder`.
- Produces: `private function filterRules(): array` va `private function filteredReportsQuery(array $filters): Builder` — Task 3 export() metodi shulardan foydalanadi.

Bu — sof refactor (yangi xatti-harakat yo'q), shuning uchun yangi test yozilmaydi — mavjud `tests/Feature/Admin/ReportPageTest.php` regressiya himoyasi vazifasini bajaradi.

- [ ] **Step 1: Refactordan oldin mavjud testlarni yashil holatda tasdiqlash**

Run: `php artisan test --filter=ReportPageTest`
Expected: PASS (2 ta test)

- [ ] **Step 2: `ReportController.php`ga `Builder` import qo'shish**

`app/Http/Controllers/ReportController.php` faylida, mavjud use bayonotlari orasiga qo'shing:

```php
use Illuminate\Database\Eloquent\Builder;
```

- [ ] **Step 3: `index()` metodini refactor qilish**

`public function index(Request $request): Response` metodining butun tanasini quyidagicha almashtiring:

```php
    public function index(Request $request): Response
    {
        $filters = $request->validate($this->filterRules());

        $reports = $this->filteredReportsQuery($filters)
            ->paginate(25)
            ->withQueryString();

        $reports->getCollection()->transform(fn (Report $report): array => [
            'id' => $report->id,
            'employee_name' => $report->employee_name,
            'department_name' => $report->department?->name,
            'device_name' => $report->device?->name,
            'type' => $report->type,
            'type_label' => $report->type_label,
            'occurred_at' => $report->occurred_at?->format('Y-m-d H:i:s'),
        ]);

        return Inertia::render('Admin/Reports/Index', [
            'reports' => $reports,
            'filters' => [
                ...$filters,
                'type' => isset($filters['type']) ? (int) $filters['type'] : null,
            ],
            'employees' => Employee::query()->orderBy('name')->get(['id', 'name']),
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
            'devices' => Device::query()->ordered()->get(['id', 'name']),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'schedules' => Schedule::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }
```

- [ ] **Step 4: Umumiy `filterRules()` va `filteredReportsQuery()` metodlarini qo'shish**

`pullStore()` metodidan keyin, klassning yopilish qavsidan oldin qo'shing:

```php
    /**
     * @return array<string, array<int, mixed>>
     */
    private function filterRules(): array
    {
        return [
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'device_id' => ['nullable', 'integer', 'exists:devices,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'schedule_id' => ['nullable', 'integer', 'exists:schedules,id'],
            'type' => ['nullable', 'integer', 'in:1,2'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Report>
     */
    private function filteredReportsQuery(array $filters): Builder
    {
        $query = Report::query()
            ->with(['department:id,name', 'device:id,name'])
            ->latest('occurred_at');

        if (! empty($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        if (! empty($filters['department_id'])) {
            $query->where('department_id', $filters['department_id']);
        }

        if (! empty($filters['device_id'])) {
            $query->where('device_id', $filters['device_id']);
        }

        if (! empty($filters['category_id'])) {
            $query->whereHas('device', fn ($deviceQuery) => $deviceQuery->where('category_id', $filters['category_id']));
        }

        if (! empty($filters['schedule_id'])) {
            $query->whereHas('employee', fn ($employeeQuery) => $employeeQuery->where('schedule_id', $filters['schedule_id']));
        }

        if (! empty($filters['type'])) {
            $query->where('type', (int) $filters['type']);
        }

        if (! empty($filters['date_from'])) {
            $query->where('occurred_at', '>=', $filters['date_from'].' 00:00:00');
        }

        if (! empty($filters['date_to'])) {
            $query->where('occurred_at', '<=', $filters['date_to'].' 23:59:59');
        }

        return $query;
    }
```

- [ ] **Step 5: Testlarni qayta ishga tushirib, hali ham yashil ekanini tasdiqlash**

Run: `php artisan test --filter=ReportPageTest`
Expected: PASS (2 ta test, xatti-harakat o'zgarmagan)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/ReportController.php
git commit -m "ReportController filtr query logikasini umumiy metodga chiqarish"
```

---

### Task 3: Backend — `ReportsExport` klassi, `export()` endpoint va testlar

**Files:**
- Create: `app/Exports/ReportsExport.php`
- Modify: `app/Http/Controllers/ReportController.php`
- Modify: `routes/web.php`
- Modify: `resources/js/routes.js`
- Test: `tests/Feature/Admin/ReportExportTest.php`

**Interfaces:**
- Consumes: `ReportController::filterRules()`, `ReportController::filteredReportsQuery(array): Builder` (Task 2).
- Produces: `GET /admin/reports/export` route nomi `admin.reports.export`; `App\Exports\ReportsExport::__construct(Builder $query)`.

- [ ] **Step 1: Muvaffaqiyatli yuklab olish testini yozish**

`tests/Feature/Admin/ReportExportTest.php` faylini yarating:

```php
<?php

namespace Tests\Feature\Admin;

use App\Exports\ReportsExport;
use App\Models\Department;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Report;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    use InteractsWithPermissions;
    use RefreshDatabase;

    public function test_authorized_user_can_download_filtered_reports_excel(): void
    {
        Excel::fake();

        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['reports'],
        ]);

        $department = Department::factory()->create(['name' => 'IT']);
        $employee = Employee::factory()->create(['department_id' => $department->id]);
        $device = Device::factory()->create(['name' => 'Kirish 1']);

        Report::factory()->forEmployee($employee)->forDevice($device)->entry()->create([
            'occurred_at' => now(),
        ]);
        Report::factory()->exit()->create(['occurred_at' => now()]);

        $this->actingAs($admin)
            ->get(route('admin.reports.export', ['type' => Report::TYPE_ENTRY]))
            ->assertOk();

        Excel::assertDownloaded('hisobotlar.xlsx', function (ReportsExport $export) {
            return $export->query()->count() === 1;
        });
    }

    public function test_unauthorized_user_cannot_download_reports_excel(): void
    {
        $user = $this->createUserWithPermissions([
            config('menu_permissions.home'),
        ]);

        $this->actingAs($user)
            ->get(route('admin.reports.export'))
            ->assertForbidden();
    }
}
```

- [ ] **Step 2: Testni ishga tushirib, kutilgan sababdan yiqilishini tasdiqlash**

Run: `php artisan test --filter=ReportExportTest`
Expected: FAIL — `route('admin.reports.export')` mavjud emasligi haqida `RouteNotFoundException` (route hali yaratilmagani uchun — bu to'g'ri sabab).

- [ ] **Step 3: `ReportsExport` export klassini yaratish**

`app/Exports/ReportsExport.php` faylini yarating:

```php
<?php

namespace App\Exports;

use App\Models\Report;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ReportsExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    private int $rowNumber = 0;

    /**
     * @param  Builder<Report>  $query
     */
    public function __construct(private readonly Builder $query)
    {
    }

    /**
     * @return Builder<Report>
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
        return ['№', 'Ism', "Bo'lim", 'Qurilma', 'Tur', 'Sana va vaqt'];
    }

    /**
     * @param  Report  $report
     * @return array<int, mixed>
     */
    public function map($report): array
    {
        $this->rowNumber++;

        return [
            $this->rowNumber,
            $report->employee_name,
            $report->department?->name ?? '—',
            $report->device?->name ?? '—',
            $report->type_label,
            $report->occurred_at?->format('Y-m-d H:i:s'),
        ];
    }
}
```

- [ ] **Step 4: `ReportController`ga `export()` metodini qo'shish**

`app/Http/Controllers/ReportController.php` faylida, `use` bayonotlariga qo'shing:

```php
use App\Exports\ReportsExport;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
```

`pullStore()` metodidan keyin (Task 2da qo'shilgan `filterRules()`dan oldin) qo'shing:

```php
    public function export(Request $request): BinaryFileResponse
    {
        $filters = $request->validate($this->filterRules());

        $query = $this->filteredReportsQuery($filters);

        return Excel::download(new ReportsExport($query), 'hisobotlar.xlsx');
    }
```

- [ ] **Step 5: Route qo'shish**

`routes/web.php` faylida, `reports.index` route'idan keyin (`Route::get('reports', [ReportController::class, 'index'])...` qatoridan keyin) qo'shing:

```php
        Route::get('reports/export', [ReportController::class, 'export'])
            ->middleware('permission:'.$menuPermissions['reports'])
            ->name('reports.export');
```

- [ ] **Step 6: `resources/js/routes.js`ga route builder qo'shish**

`'admin.reports.index': () => '/admin/reports',` qatoridan keyin qo'shing:

```js
    'admin.reports.export': () => '/admin/reports/export',
```

- [ ] **Step 7: Testni qayta ishga tushirib, o'tganini tasdiqlash**

Run: `php artisan test --filter=ReportExportTest`
Expected: PASS (2 ta test)

- [ ] **Step 8: To'liq test suite bilan regressiya tekshiruvi**

Run: `php artisan test`
Expected: avvalgi natija + 2 ta yangi PASS test (faqat eskirgan 2 ta `SanctumUserApiTest` FAIL qolishi mumkin, boshqa hech narsa buzilmagan bo'lishi kerak).

- [ ] **Step 9: Commit**

```bash
git add app/Exports/ReportsExport.php app/Http/Controllers/ReportController.php routes/web.php resources/js/routes.js tests/Feature/Admin/ReportExportTest.php
git commit -m "Hisobotlar uchun Excel export backend qismini qo'shish"
```

---

### Task 4: Frontend — "Excel yuklab olish" tugmasi

**Files:**
- Modify: `resources/js/Pages/Admin/Reports/Index.jsx`

**Interfaces:**
- Consumes: `route('admin.reports.export')` (Task 3), komponentning mavjud `filters` propi.

- [ ] **Step 1: Export URL yaratuvchi funksiya va tugmani qo'shish**

`resources/js/Pages/Admin/Reports/Index.jsx` faylida, `PageHeader` bloki hozir shunday:

```jsx
            <PageHeader
                title="Hisobotlar"
                subtitle="Xodimlarning kirish va chiqish yozuvlari."
                actions={(
                    <Link
                        href={route('admin.reports.pull')}
                        className="inline-flex h-10 items-center rounded-lg bg-brand-600 px-4 text-sm font-bold text-white hover:bg-brand-700"
                    >
                        Qurilmadan tortish
                    </Link>
                )}
            />
```

Buni quyidagicha almashtiring (yuqorida, `resetFilters` funksiyasidan keyin, `const from = ...` qatoridan oldin, yangi funksiya qo'shiladi):

```jsx
    const exportHref = () => {
        const payload = Object.fromEntries(
            Object.entries(filters).filter(([, value]) => value !== null && value !== undefined && value !== ''),
        );
        const query = new URLSearchParams(payload).toString();
        const base = route('admin.reports.export');

        return query ? `${base}?${query}` : base;
    };
```

va `PageHeader` blokini:

```jsx
            <PageHeader
                title="Hisobotlar"
                subtitle="Xodimlarning kirish va chiqish yozuvlari."
                actions={(
                    <div className="flex gap-3">
                        <a
                            href={exportHref()}
                            className="inline-flex h-10 items-center rounded-lg border border-line bg-surface px-4 text-sm font-bold text-ink hover:bg-surface-muted"
                        >
                            Excel yuklab olish
                        </a>
                        <Link
                            href={route('admin.reports.pull')}
                            className="inline-flex h-10 items-center rounded-lg bg-brand-600 px-4 text-sm font-bold text-white hover:bg-brand-700"
                        >
                            Qurilmadan tortish
                        </Link>
                    </div>
                )}
            />
```

- [ ] **Step 2: Dev serverni ishga tushirish (agar ishlamayotgan bo'lsa)**

Run: `composer dev` (fon rejimida)

- [ ] **Step 3: Brauzerda qo'lda tekshirish**

1. `http://127.0.0.1:8000/admin/reports` sahifasiga kiring.
2. Bir nechta filtr qo'ying (masalan, Tur = "Kirish") va "Ko'rsatish" bosing.
3. "Excel yuklab olish" tugmasini bosing.
4. Yuklab olingan `.xlsx` faylni Excel/LibreOffice'da oching va tekshiring:
   - Sarlavha qatori: №, Ism, Bo'lim, Qurilma, Tur, Sana va vaqt.
   - Faqat qo'yilgan filtrga (Tur = Kirish) mos qatorlar borligini.
   - Qatorlar soni sahifadagi "jami X ta yozuv" bilan mos kelishini (sahifalashsiz, hammasi bitta faylda).
5. Filtrsiz holatda ham tugmani bosib, barcha yozuvlar tushishini tekshiring.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/Admin/Reports/Index.jsx
git commit -m "Hisobotlar sahifasiga Excel yuklab olish tugmasini qo'shish"
```

---

## Yakuniy tekshiruv

- [ ] `php artisan test` — to'liq suite yashil (eskirgan 2 ta SanctumUserApiTest testidan tashqari).
- [ ] Brauzerda `/admin/reports` sahifasida tugma ko'rinadi va ishlaydi (Task 4, Step 3).
- [ ] `reports` ruxsati bo'lmagan foydalanuvchi `/admin/reports/export`ga to'g'ridan-to'g'ri kirsa 403 oladi (ReportExportTest bilan qamrab olingan).
