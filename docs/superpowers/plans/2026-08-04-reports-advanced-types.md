# Reports Advanced Types Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** `/admin/reports` da eski tizimdagi 8 hisobot turi, datetime filter, tipga mos jadval/Excel — `ReportQueryService` orqali.

**Architecture:** `AttendanceReportType` (1–8) filter mode sifatida ishlaydi (`reports.type` DB qiymati emas). `ReportQueryService` barcha tip querylarini beradi; `ReportController` yupqa qoladi; Excel `forExport()` + dinamik headings; UI `columns` bo‘yicha render qiladi. Tungi smena hisobi `AttendanceCalculator` da.

**Tech Stack:** Laravel 12, PHP 8.2+, Inertia + React 19, Ant Design DatePicker, Maatwebsite Excel, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-08-04-reports-advanced-types-design.md`

## Global Constraints

- Parking/plate, bayramlar, `rate` ustuni yo‘q (spec YAGNI).
- Type 5–7: bo‘sh sana → bugun; max oraliq **93 kun**.
- Type 3/4/7/8: faqat `schedule_id` bor xodimlar.
- Tungi smena: `enter_time > exit_time` → `shift_dt = occurred_at - 12 hours` (eski tizimdek).
- Pull route’lar o‘zgarmaydi.
- JS avtomatik test yo‘q — frontend qo‘lda tekshiriladi.
- Har task oxirida faqat shu task fayllarini commit qiling.

## File map

| File | Responsibility |
|------|----------------|
| `app/Enums/AttendanceReportType.php` | 1–8 + `label()` |
| `app/Services/Reports/AttendanceCalculator.php` | Tungi smena / kechikish / erta / ish vaqti hisobi |
| `app/Services/ReportQueryService.php` | Filter + 8 tur + columns/paginate/forExport |
| `app/Http/Controllers/ReportController.php` | Validate + service chaqirish |
| `app/Exports/ReportsExport.php` | Dinamik headings + FromCollection |
| `resources/js/Pages/Admin/Reports/Index.jsx` | 8 tur, datetime, dinamik jadval |
| `tests/Unit/AttendanceCalculatorTest.php` | Tungi smena unit |
| `tests/Unit/ReportQueryServiceTest.php` | Tip query birlik/integratsiya |
| `tests/Feature/Admin/ReportPageTest.php` | Sahifa + tip happy-path |
| `tests/Feature/Admin/ReportExportTest.php` | Excel headings |

---

### Task 1: `AttendanceReportType` + `AttendanceCalculator`

**Files:**
- Create: `app/Enums/AttendanceReportType.php`
- Create: `app/Services/Reports/AttendanceCalculator.php`
- Create: `tests/Unit/AttendanceCalculatorTest.php`

**Interfaces:**
- Produces:
  - `AttendanceReportType: int` cases `Entry=1, Exit=2, Late=3, LeftEarly=4, Present=5, Absent=6, WorkTime=7, Individual=8`
  - `label(): string` (Kirish, Chiqish, Kechikkan, Erta ketgan, Kelgan, Kelmagan, Ish vaqti, Individual)
  - `AttendanceCalculator::isNightShift(string $enterTime, string $exitTime): bool`
  - `AttendanceCalculator::shiftMoment(\Carbon\Carbon $occurredAt): Carbon` — `$occurredAt->copy()->subHours(12)`
  - `AttendanceCalculator::workDate(Carbon $occurredAt, bool $nightShift): string` — `Y-m-d` (night bo‘lsa `shiftMoment` sanasi)
  - `AttendanceCalculator::lateSeconds(Carbon $firstEntry, string $enterTime, bool $nightShift): int` — musbat kechikish soniyasi, aks holda `0`
  - `AttendanceCalculator::earlySeconds(Carbon $lastExit, string $exitTime, bool $nightShift): int`
  - `AttendanceCalculator::workedSeconds(?Carbon $firstEntry, ?Carbon $lastExit): int`

- [ ] **Step 1: Failing unit test yozish**

`tests/Unit/AttendanceCalculatorTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Services\Reports\AttendanceCalculator;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AttendanceCalculatorTest extends TestCase
{
    #[Test]
    public function day_shift_detects_late_entry(): void
    {
        $calc = new AttendanceCalculator;
        $this->assertFalse($calc->isNightShift('09:00', '18:00'));

        $entry = Carbon::parse('2026-08-04 09:15:00');
        $this->assertSame(15 * 60, $calc->lateSeconds($entry, '09:00:00', false));
        $this->assertSame(0, $calc->lateSeconds(Carbon::parse('2026-08-04 08:50:00'), '09:00:00', false));
    }

    #[Test]
    public function night_shift_uses_shifted_clock_for_work_date_and_late(): void
    {
        $calc = new AttendanceCalculator;
        $this->assertTrue($calc->isNightShift('22:00', '06:00'));

        // 22:30 same calendar day → not late vs 22:00
        $this->assertSame(
            30 * 60,
            $calc->lateSeconds(Carbon::parse('2026-08-04 22:30:00'), '22:00:00', true),
        );

        // After midnight: 2026-08-05 00:30 → shiftMoment = 2026-08-04 12:30 → work date 2026-08-04
        $this->assertSame(
            '2026-08-04',
            $calc->workDate(Carbon::parse('2026-08-05 00:30:00'), true),
        );
    }

    #[Test]
    public function early_leave_and_worked_seconds(): void
    {
        $calc = new AttendanceCalculator;
        $this->assertSame(
            30 * 60,
            $calc->earlySeconds(Carbon::parse('2026-08-04 17:30:00'), '18:00:00', false),
        );
        $this->assertSame(
            8 * 3600,
            $calc->workedSeconds(
                Carbon::parse('2026-08-04 09:00:00'),
                Carbon::parse('2026-08-04 17:00:00'),
            ),
        );
        $this->assertSame(0, $calc->workedSeconds(Carbon::parse('2026-08-04 09:00:00'), null));
    }
}
```

- [ ] **Step 2: Test fail ekanini tekshirish**

Run: `php artisan test --filter=AttendanceCalculatorTest`
Expected: FAIL (class not found)

- [ ] **Step 3: Enum + calculator implementatsiya**

`app/Enums/AttendanceReportType.php`:

```php
<?php

namespace App\Enums;

enum AttendanceReportType: int
{
    case Entry = 1;
    case Exit = 2;
    case Late = 3;
    case LeftEarly = 4;
    case Present = 5;
    case Absent = 6;
    case WorkTime = 7;
    case Individual = 8;

    public function label(): string
    {
        return match ($this) {
            self::Entry => 'Kirish',
            self::Exit => 'Chiqish',
            self::Late => 'Kechikkan',
            self::LeftEarly => 'Erta ketgan',
            self::Present => 'Kelgan',
            self::Absent => 'Kelmagan',
            self::WorkTime => 'Ish vaqti',
            self::Individual => 'Individual',
        };
    }

    public function requiresSchedule(): bool
    {
        return in_array($this, [self::Late, self::LeftEarly, self::WorkTime, self::Individual], true);
    }

    public function requiresBoundedDates(): bool
    {
        return in_array($this, [self::Present, self::Absent, self::WorkTime], true);
    }
}
```

`app/Services/Reports/AttendanceCalculator.php` — Step 1 dagi interfeys bo‘yicha implement qiling. `lateSeconds` / `earlySeconds` da:
- day: solishtirish `H:i:s` vs schedule time.
- night: `shiftMoment($occurredAt)` ning `H:i:s` ni `shiftMoment` qilingan schedule time (`Carbon::parse($time)->subHours(12)->format('H:i:s')`) bilan solishtiring — eski `ADDTIME(..., '-12:00:00')` ekvivalenti.

- [ ] **Step 4: Testlar yashil**

Run: `php artisan test --filter=AttendanceCalculatorTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Enums/AttendanceReportType.php app/Services/Reports/AttendanceCalculator.php tests/Unit/AttendanceCalculatorTest.php
git commit -m "Add attendance report type enum and night-shift calculator"
```

---

### Task 2: `ReportQueryService` — raw logs (bo‘sh/1/2) + controller wiring

**Files:**
- Create: `app/Services/ReportQueryService.php`
- Create: `tests/Unit/ReportQueryServiceTest.php`
- Modify: `app/Http/Controllers/ReportController.php`
- Modify: `tests/Feature/Admin/ReportPageTest.php` (columns assert qo‘shish)

**Interfaces:**
- Consumes: `AttendanceReportType`, `AttendanceCalculator`, `Report`, existing filters
- Produces:
  - `columns(array $filters): array` — `list<array{key: string, label: string}>`
  - `paginate(array $filters, int $perPage = 25): \Illuminate\Contracts\Pagination\LengthAwarePaginator` — itemlar **array** (model emas)
  - `forExport(array $filters): \Illuminate\Support\Collection` — xuddi shu row arraylar, limit yo‘q
  - Raw row keys: `employee_name`, `group_name`, `position`, `device_name`, `type`, `type_label`, `occurred_at`
  - Raw columns labels: № handled in export/UI index; service columns without №: Ism, Guruh, Lavozim, Qurilma, Tur, Sana va vaqt

Row shape for raw:

```php
[
  'employee_name' => string,
  'group_name' => ?string,
  'position' => ?string,
  'device_name' => ?string,
  'type' => int,
  'type_label' => string,
  'occurred_at' => string, // Y-m-d H:i:s
]
```

- [ ] **Step 1: Failing test — raw filter + columns**

`tests/Unit/ReportQueryServiceTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Enums\AttendanceReportType;
use App\Models\Department;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Group;
use App\Models\Report;
use App\Models\Schedule;
use App\Services\ReportQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReportQueryServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function raw_logs_filter_by_entry_type_and_expose_columns(): void
    {
        $department = Department::factory()->create();
        $schedule = Schedule::factory()->create(['enter_time' => '09:00', 'exit_time' => '18:00']);
        $group = Group::factory()->create(['department_id' => $department->id, 'name' => 'G1']);
        $employee = Employee::factory()->create([
            'department_id' => $department->id,
            'schedule_id' => $schedule->id,
            'group_id' => $group->id,
            'position' => 'Dev',
            'name' => 'Ali',
        ]);
        $device = Device::factory()->create(['name' => 'Door 1']);

        Report::factory()->forEmployee($employee)->forDevice($device)->entry()->create([
            'occurred_at' => '2026-08-04 09:05:00',
        ]);
        Report::factory()->forEmployee($employee)->forDevice($device)->exit()->create([
            'occurred_at' => '2026-08-04 18:00:00',
        ]);

        $service = app(ReportQueryService::class);
        $filters = ['type' => AttendanceReportType::Entry->value];

        $columns = $service->columns($filters);
        $this->assertSame(['employee_name', 'group_name', 'position', 'device_name', 'type_label', 'occurred_at'], array_column($columns, 'key'));

        $page = $service->paginate($filters, 25);
        $this->assertCount(1, $page->items());
        $this->assertSame('Ali', $page->items()[0]['employee_name']);
        $this->assertSame('Kirish', $page->items()[0]['type_label']);
    }
}
```

Agar `Group::factory` mavjud bo‘lmasa, guruhsiz yaratib `group_name` ni `null` assert qiling.

- [ ] **Step 2: Fail tekshirish**

Run: `php artisan test --filter=ReportQueryServiceTest`
Expected: FAIL

- [ ] **Step 3: Service + controller**

`ReportQueryService`:
- `normalizeFilters(array $filters): array` — type int|null; datetime stringlarni saqlash.
- Raw path: hozirgi `ReportController::filteredReportsQuery` logikasini ko‘chiring; `employee.group` eager load; `department` o‘rniga jadvalda **guruh** ko‘rsatiladi (spec).
- `paginate`: raw uchun query `paginate` + `through`/`getCollection()->transform`.
- `forExport`: raw uchun `->get()` + map.
- Hozircha type 3–8 bo‘lsa bo‘sh paginator qaytaring (keyingi tasklar to‘ldiradi) **yoki** `InvalidArgumentException` — yaxshiroq: `match` da `default` → raw; 3–8 uchun empty collection + to‘g‘ri columns stub. Task 3 dan boshlab to‘ldiriladi.

`ReportController::index`:
- `$service = app(ReportQueryService::class)` yoki constructor inject.
- `filterRules`: `type` => `nullable|integer|in:1,2,3,4,5,6,7,8`; `date_from`/`date_to` => `nullable|date` (datetime OK).
- Response: `'reports' => $service->paginate($filters)`, `'columns' => $service->columns($filters)`, filters normalizatsiyasi (datetime stringlar qaytsin).
- Private `filteredReportsQuery` ni olib tashlang (service ga ko‘chgan).

`export` vaqtincha eski FromQuery bilan ishlashi mumkin — Task 6 da to‘liq almashtiriladi. Task 2 da export ham `forExport` + vaqtincha `ReportsExport` ni `FromCollection` ga o‘tkazing **agar** mavjud export test sinishi mumkin bo‘lsa — minimal: export service raw collection ishlatsin.

Minimal `ReportsExport` yangilanishi (Task 2):

```php
// constructor(Collection $rows, array $columns)
// FromCollection, WithHeadings, WithMapping, ShouldAutoSize
```

`ReportExportTest` dagi `query()->count()` assert ni `collection()->count() === 1` ga yangilang (yoki public `rows()`).

- [ ] **Step 4: Testlar**

Run: `php artisan test --filter=ReportQueryServiceTest`
Run: `php artisan test --filter=ReportPageTest`
Run: `php artisan test --filter=ReportExportTest`
Expected: PASS (mavjud + yangi)

`ReportPageTest` ga qo‘shing: `->has('columns')`.

- [ ] **Step 5: Commit**

```bash
git add app/Services/ReportQueryService.php app/Http/Controllers/ReportController.php app/Exports/ReportsExport.php tests/Unit/ReportQueryServiceTest.php tests/Feature/Admin/ReportPageTest.php tests/Feature/Admin/ReportExportTest.php
git commit -m "Extract ReportQueryService for raw entry/exit reports"
```

---

### Task 3: Type 3 (Kechikkan) + Type 4 (Erta ketgan)

**Files:**
- Modify: `app/Services/ReportQueryService.php`
- Modify: `tests/Unit/ReportQueryServiceTest.php`

**Interfaces:**
- Consumes: `AttendanceCalculator`, `NoteType::CameLate` / `LeftEarly`, `Employee.schedule`
- Produces late row:
  ```php
  ['employee_name','group_name','position','date','time','late','reason']
  ```
  early row: `early` o‘rniga `late` emas — key `early`
- Columns labels: Ism, Guruh, Lavozim, Sana, Vaqt, Kechikish|Erta, Sabab

**Algorithm (Late):**
1. Filter raw entry reports (`type=1`) + umumiy filterlar + `whereHas employee.schedule_id not null`.
2. PHP da employee+workDate bo‘yicha guruhlab `min(occurred_at)` oling (night: `workDate` calculator orqali).
3. `lateSeconds > 0` bo‘lsa qator qo‘shing; `late` ni `H:i:s` formatda (`gmdate` yoki `sprintf` soat:daq:son).
4. Note: `notes` where employee_id + type CameLate + dt = date.

**Left early:** oxirgi exit (`type=2`), `earlySeconds > 0`.

Kattaa dataset uchun SQL port mumkin, lekin birinchi versiyada PHP aggregation + `LengthAwarePaginator::make` qabul qilinadi (YAGNI: 93 kun cheklovi keyinroq type 5–7 da; type 3/4 uchun datetime filter yetarli).

- [ ] **Step 1: Failing tests**

`ReportQueryServiceTest` ga qo‘shing:

```php
#[Test]
public function late_report_includes_only_late_first_entries_with_note(): void
{
    $department = Department::factory()->create();
    $schedule = Schedule::factory()->create(['enter_time' => '09:00', 'exit_time' => '18:00']);
    $employee = Employee::factory()->create([
        'department_id' => $department->id,
        'schedule_id' => $schedule->id,
        'name' => 'Late Ali',
        'position' => 'Dev',
    ]);
    $device = Device::factory()->create();

    Report::factory()->forEmployee($employee)->forDevice($device)->entry()->create([
        'occurred_at' => '2026-08-04 09:20:00',
    ]);
    // on-time employee should not appear
    $onTime = Employee::factory()->create([
        'department_id' => $department->id,
        'schedule_id' => $schedule->id,
        'name' => 'On Time',
    ]);
    Report::factory()->forEmployee($onTime)->forDevice($device)->entry()->create([
        'occurred_at' => '2026-08-04 08:55:00',
    ]);

    \App\Models\Note::factory()->create([
        'employee_id' => $employee->id,
        'type' => \App\Enums\NoteType::CameLate,
        'dt' => '2026-08-04',
        'note' => 'Tirbandlik',
    ]);

    $page = app(ReportQueryService::class)->paginate([
        'type' => AttendanceReportType::Late->value,
        'date_from' => '2026-08-04 00:00:00',
        'date_to' => '2026-08-04 23:59:59',
    ], 25);

    $this->assertCount(1, $page->items());
    $this->assertSame('Late Ali', $page->items()[0]['employee_name']);
    $this->assertSame('00:20:00', $page->items()[0]['late']);
    $this->assertSame('Tirbandlik', $page->items()[0]['reason']);
}

#[Test]
public function left_early_report_detects_early_exit(): void
{
    $department = Department::factory()->create();
    $schedule = Schedule::factory()->create(['enter_time' => '09:00', 'exit_time' => '18:00']);
    $employee = Employee::factory()->create([
        'department_id' => $department->id,
        'schedule_id' => $schedule->id,
        'name' => 'Early Bob',
    ]);
    $device = Device::factory()->create();
    Report::factory()->forEmployee($employee)->forDevice($device)->exit()->create([
        'occurred_at' => '2026-08-04 17:00:00',
    ]);

    $page = app(ReportQueryService::class)->paginate([
        'type' => AttendanceReportType::LeftEarly->value,
        'date_from' => '2026-08-04 00:00:00',
        'date_to' => '2026-08-04 23:59:59',
    ], 25);

    $this->assertCount(1, $page->items());
    $this->assertSame('01:00:00', $page->items()[0]['early']);
}
```

- [ ] **Step 2: Fail → implement → pass**

Run: `php artisan test --filter=ReportQueryServiceTest`
Expected after implement: PASS

Seconds format helper:

```php
private function formatDuration(int $seconds): string
{
    $seconds = max(0, $seconds);
    return sprintf('%02d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
}
```

- [ ] **Step 3: Commit**

```bash
git add app/Services/ReportQueryService.php tests/Unit/ReportQueryServiceTest.php
git commit -m "Add late and left-early attendance report queries"
```

---

### Task 4: Type 5 (Kelgan) + Type 6 (Kelmagan) + date bounds validation

**Files:**
- Modify: `app/Services/ReportQueryService.php`
- Modify: `app/Http/Controllers/ReportController.php` (`filterRules` + custom validation)
- Modify: `tests/Unit/ReportQueryServiceTest.php`
- Modify: `tests/Feature/Admin/ReportPageTest.php`

**Interfaces:**
- `resolveDateBounds(array $filters, AttendanceReportType $type): array{0: Carbon, 1: Carbon}`
  - requiresBoundedDates: default today start/end if empty
  - if `diffInDays > 93` — validation exception (controller rule)
- Present row: `employee_name, group_name, position, date`
- Absent row: + `reason` (Absent note)

**Algorithm:**
1. Build date list from from→to (inclusive calendar days).
2. Employees matching filters (scope, schedule optional for 5/6 — **spec does not require schedule for present/absent**).
3. Present: employee has ≥1 report on that calendar date (`DATE(occurred_at)`).
4. Absent: no report that day; attach note if any.

- [ ] **Step 1: Failing tests**

```php
#[Test]
public function present_lists_employees_with_punch_on_day(): void
{
    // one employee with entry on 2026-08-04, one without
    // type=5, date_from/to that day → only punched employee
}

#[Test]
public function absent_lists_employees_without_punch(): void
{
    // inverse; include Absent note reason
}
```

Feature test:

```php
public function test_present_absent_reject_range_over_93_days(): void
{
    $admin = $this->createUserWithPermissions([
        config('menu_permissions.resources')['reports'],
    ]);

    $this->actingAs($admin)
        ->get(route('admin.reports.index', [
            'type' => 5,
            'date_from' => '2026-01-01 00:00:00',
            'date_to' => '2026-05-01 23:59:59',
        ]))
        ->assertSessionHasErrors(['date_to']); // or date_from
}
```

Controller validation (after basic rules):

```php
$type = isset($filters['type']) ? AttendanceReportType::tryFrom((int) $filters['type']) : null;
if ($type?->requiresBoundedDates()) {
    $from = isset($filters['date_from']) ? Carbon::parse($filters['date_from']) : now()->startOfDay();
    $to = isset($filters['date_to']) ? Carbon::parse($filters['date_to']) : now()->endOfDay();
    if ($from->diffInDays($to) > 93) {
        throw ValidationException::withMessages([
            'date_to' => 'Maksimal oraliq 93 kun.',
        ]);
    }
    $filters['date_from'] ??= $from->format('Y-m-d H:i:s');
    $filters['date_to'] ??= $to->format('Y-m-d H:i:s');
}
```

Service ham bo‘sh sanani default qilsin (export path uchun).

- [ ] **Step 2: Implement → pass**

Run: `php artisan test --filter=ReportQueryServiceTest`
Run: `php artisan test --filter=ReportPageTest`
Expected: PASS

- [ ] **Step 3: Commit**

```bash
git add app/Services/ReportQueryService.php app/Http/Controllers/ReportController.php tests/Unit/ReportQueryServiceTest.php tests/Feature/Admin/ReportPageTest.php
git commit -m "Add present and absent reports with 93-day bound"
```

---

### Task 5: Type 7 (Ish vaqti) + Type 8 (Individual)

**Files:**
- Modify: `app/Services/ReportQueryService.php`
- Modify: `tests/Unit/ReportQueryServiceTest.php`

**Interfaces:**
- Individual row: `employee_name, group_name, position, date, check_in, check_out` (datetime strings or `—`)
- WorkTime: dynamic columns — base keys + each date `Y-m-d` + `total`
  - `columns()` must use same `resolveDateBounds`
  - Row: name fields + `"2026-08-04" => "08:00:00"` + `total => "08:00:00"`

**Algorithm:**
1. Load entry/exit reports in range for scheduled employees.
2. Group by employee + workDate (night-aware).
3. first entry / last exit → workedSeconds / check_in / check_out.
4. WorkTime: pivot in PHP; paginate **employees** (not day rows).

- [ ] **Step 1: Failing tests**

```php
#[Test]
public function individual_shows_first_in_last_out_per_day(): void
{
    // entry 09:00, exit 18:00 → check_in/check_out set
}

#[Test]
public function work_time_pivots_hours_per_day_with_total(): void
{
    $service = app(ReportQueryService::class);
    $filters = [
        'type' => AttendanceReportType::WorkTime->value,
        'date_from' => '2026-08-04 00:00:00',
        'date_to' => '2026-08-05 23:59:59',
    ];
    $keys = array_column($service->columns($filters), 'key');
    $this->assertContains('2026-08-04', $keys);
    $this->assertContains('2026-08-05', $keys);
    $this->assertContains('total', $keys);
    // create punches both days; assert total duration
}
```

- [ ] **Step 2: Implement → pass**

Run: `php artisan test --filter=ReportQueryServiceTest`
Expected: PASS

- [ ] **Step 3: Commit**

```bash
git add app/Services/ReportQueryService.php tests/Unit/ReportQueryServiceTest.php
git commit -m "Add work-time and individual attendance reports"
```

---

### Task 6: Excel export — dinamik ustunlar barcha turlar uchun

**Files:**
- Modify: `app/Exports/ReportsExport.php`
- Modify: `app/Http/Controllers/ReportController.php` (`export` method)
- Modify: `tests/Feature/Admin/ReportExportTest.php`

**Interfaces:**
- `ReportsExport::__construct(Collection $rows, array $columns)`
- `headings(): ['№', ...labels]`
- `map($row): [++, ...values by column keys]`
- `FromCollection` (FromQuery olib tashlanadi)

- [ ] **Step 1: Failing export test for late headings**

```php
public function test_late_export_uses_late_headings(): void
{
    Excel::fake();
    // seed one late row setup (copy from unit test)
    $admin = $this->createUserWithPermissions([
        config('menu_permissions.resources')['reports'],
    ]);

    $this->actingAs($admin)
        ->get(route('admin.reports.export', [
            'type' => 3,
            'date_from' => '2026-08-04 00:00:00',
            'date_to' => '2026-08-04 23:59:59',
        ]))
        ->assertOk();

    Excel::assertDownloaded('hisobotlar.xlsx', function (ReportsExport $export) {
        return $export->headings() === ['№', 'Ism', 'Guruh', 'Lavozim', 'Sana', 'Vaqt', 'Kechikish', 'Sabab']
            && $export->collection()->count() === 1;
    });
}
```

Keep existing entry export test updated for FromCollection.

- [ ] **Step 2: Implement export class + controller**

```php
return Excel::download(
    new ReportsExport($service->forExport($filters), $service->columns($filters)),
    'hisobotlar.xlsx',
);
```

- [ ] **Step 3: Pass tests**

Run: `php artisan test --filter=ReportExportTest`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add app/Exports/ReportsExport.php app/Http/Controllers/ReportController.php tests/Feature/Admin/ReportExportTest.php
git commit -m "Export attendance reports with type-specific Excel columns"
```

---

### Task 7: Frontend — 8 tur, datetime, dinamik jadval

**Files:**
- Modify: `resources/js/Pages/Admin/Reports/Index.jsx`

**Interfaces:**
- Consumes Inertia props: `reports`, `columns` (`{key,label}[]`), `filters`, option lists
- Type options match `AttendanceReportType::label()`
- DateTime: `DatePicker showTime`, format `YYYY-MM-DD HH:mm:ss`, send same string
- Table: map `columns`; № = `(from - 1) + index + 1`; cell = `row[column.key] ?? '—'`
- WorkTime: date keys still render as normal cells
- Excel href: existing `exportHref()` using `filters` prop (already applied filters)

- [ ] **Step 1: Update typeOptions**

```js
const typeOptions = [
  { value: 1, label: 'Kirish' },
  { value: 2, label: 'Chiqish' },
  { value: 3, label: 'Kechikkan' },
  { value: 4, label: 'Erta ketgan' },
  { value: 5, label: 'Kelgan' },
  { value: 6, label: 'Kelmagan' },
  { value: 7, label: 'Ish vaqti' },
  { value: 8, label: 'Individual' },
];
```

- [ ] **Step 2: DatePicker showTime**

```jsx
<DatePicker
  showTime
  className="w-full"
  placeholder="Sana va vaqt dan"
  value={form.date_from ? dayjs(form.date_from) : null}
  onChange={(value) => updateField('date_from', value ? value.format('YYYY-MM-DD HH:mm:ss') : null)}
/>
```

Same for `date_to`.

- [ ] **Step 3: Dynamic table**

Replace hard-coded `<th>` / `<td>` with:

```jsx
<thead>
  <tr>
    <th className="w-12">№</th>
    {columns.map((col) => <th key={col.key}>{col.label}</th>)}
  </tr>
</thead>
<tbody>
  {reports.data.map((row, index) => (
    <tr key={row.id ?? `${row.employee_name}-${row.date ?? row.occurred_at}-${index}`}>
      <td>{(from || 1) + index}</td>
      {columns.map((col) => (
        <td key={col.key}>{row[col.key] ?? '—'}</td>
      ))}
    </tr>
  ))}
</tbody>
```

Default `columns = []` prop. Type badge styling optional for `type_label` only (YAGNI: plain text OK).

- [ ] **Step 4: Manual check**

Run: `npm run dev` (if needed) + oching `/admin/reports`
- Type dropdown 8 ta
- Datetime picker
- Generate late/present
- Excel download

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/Admin/Reports/Index.jsx
git commit -m "Update reports UI for eight types and datetime filters"
```

---

### Task 8: Feature coverage — har bir type happy-path + regressiya

**Files:**
- Modify: `tests/Feature/Admin/ReportPageTest.php`

**Interfaces:**
- Har type uchun kamida 1 ta Inertia assert (`has('reports.data', N)` + muhim maydon)

- [ ] **Step 1: Qo‘shish**

Minimal helper private method test class ichida seed qilish uchun. Tests:

1. `test_late_type_returns_late_rows` (type=3)
2. `test_left_early_type_returns_rows` (type=4)
3. `test_present_type_returns_rows` (type=5)
4. `test_absent_type_returns_rows` (type=6)
5. `test_work_time_type_returns_rows` (type=7)
6. `test_individual_type_returns_rows` (type=8)
7. Mavjud entry/exit/unauthorized saqlansin

Har birida `assertInertia` → `where('columns.0.key', ...)` tipga mos.

- [ ] **Step 2: Run full report suite**

Run: `php artisan test --filter=Report`
Expected: PASS (ReportPageTest, ReportExportTest, ReportPullPageTest, ReportQueryServiceTest, AttendanceCalculatorTest, ReportWebhookTest — webhook o‘zgarmagan bo‘lishi kerak)

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Admin/ReportPageTest.php
git commit -m "Cover all attendance report types in feature tests"
```

---

## Spec coverage checklist

| Spec requirement | Task |
|------------------|------|
| 8 tur | 1,3,4,5,7,8 |
| Datetime filter | 2,7 |
| Tungi smena | 1,3,5 |
| Notes sabab | 3,4 |
| 93 kun + default bugun (5–7) | 4 |
| Excel tipga mos | 6 |
| ReportQueryService | 2–5 |
| UI dinamik columns | 7 |
| Parking/bayram/rate yo‘q | — (qilinmaydi) |
| Pull o‘zgarmaydi | — |

## Self-review notes

- `columns(array $filters)` — WorkTime uchun sana kerak; design dagi `columns(?AttendanceReportType)` biroz kengaytirildi (filters).
- Export `FromCollection` — aggregated turlar uchun majburiy.
- Late/early PHP aggregation — to‘g‘ri; kerak bo‘lsa keyin SQL optimizatsiya.
