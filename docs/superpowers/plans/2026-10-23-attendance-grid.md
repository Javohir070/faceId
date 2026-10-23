# Davomat jadvali (barcha xodimlar) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** `/admin/attendance` — barcha xodimlar uchun xodim × ish kuni katakli davomat jadvali, statistika, kun tafsiloti oynasi, izoh ("ariza") qo‘shish, arizalar tabi va Excel.

**Architecture:** `AttendancePeriod` (davr va ish kunlari) + `AttendanceGridService` (kataklar, statistika, kun tafsiloti; mavjud `AttendanceCalculator` ustida) → `AttendanceController` (Inertia, og‘ir proplar closure ichida — qisman qayta yuklashda hisoblanmaydi) → `Admin/Attendance/Index.jsx` va `Components/Attendance/*`. Faqat bazadan o‘qiladi; izoh yozish mavjud `NoteService::create` orqali.

**Tech Stack:** Laravel 12, Inertia 3 + React 19, AntD 6, Tailwind, maatwebsite/excel, PHPUnit (SQLite).

**Spec:** `docs/superpowers/specs/2026-10-23-attendance-grid-design.md`

## Global Constraints

- Ish kunlari — dushanba–juma; shanba/yakshanba hech qayerda yo‘q.
- Tushlik — har yakunlangan xodim-kun uchun 3600 s ishlagan vaqtdan ham, rejadan ham ayiriladi (`max(0, …)`).
- Holat ustunligi: `future` → `excused` (har qanday izoh) → `late` (`lateSeconds > 0`) → `on_time` → `absent` (sana < bugun) → `pending` (bugun, kirish yo‘q). Muddatdan tashqari xodim-kun — `inactive`, hisobga kirmaydi.
- Davomiylik formati: `81 s 24 d` (soat butun, daqiqa 2 xonali).
- Huquq nomi `'Davomat'` (`config('menu_permissions.resources.attendance')`); izoh qo‘shish qo‘shimcha `'Izohlar'` talab qiladi.
- Bo‘lim cheklovi: `Employee`/`Report` global scope orqali; `withoutGlobalScopes()` ishlatilmaydi.
- Qurilmalarga hech qanday so‘rov yo‘q.
- Izoh sanasi serverdan `Y-m-d` satr bo‘lib keladi (`$note->dt->toDateString()`), JSON dagi `Note::dt` ga tayanilmaydi.
- Barcha UI matnlari, flash va izohlar o‘zbekcha; ikonalar faqat `@ant-design/icons`; navigatsiya Inertia `router`/`useForm`; marshrutlar `resources/js/routes.js` da.
- Testlar: `composer test` (SQLite); vaqt `$this->travelTo(Carbon::parse('2026-10-23 12:00:00'))` (juma).

## Review Focus

- Xodim davr o‘rtasida ishga kirgan (`valid_from_dt` davr ichida) → oldingi kunlar `inactive`, "Kelmagan" emas — Task 2 testi `test_days_outside_employee_validity_are_inactive`.
- Tungi smena (20:00–08:00) → ertalabki chiqish kirgan kuniga yoziladi, ishlagan/rejada tushlik ayiriladi — Task 2 testi `test_night_shift_attributes_morning_exit_to_previous_day`.
- Bugun hali kelmagan xodim → `pending`, statistikada hech qayerda yo‘q, "Kelmagan" emas — Task 2 testi `test_today_without_entry_is_pending_and_not_counted`.
- Oy davri yil oxirini kesib o‘tadi (dekabr→yanvar) → oldinga/orqaga tugmasi to‘g‘ri oyga o‘tadi, yorliqda ikkala yil — Task 1 testi `test_month_navigation_crosses_year`.
- Bo‘lim bilan cheklangan foydalanuvchi boshqa bo‘lim xodimining kun tafsilotini (`day_employee_id`) so‘rasa → `day` null, ma’lumot sizmaydi — Task 4 testi `test_day_detail_respects_department_scope`.

---

### Task 1: `AttendancePeriod` — davr, ish kunlari, navigatsiya

**Files:**
- Create: `app/Services/Attendance/AttendancePeriod.php`
- Test: `tests/Unit/Attendance/AttendancePeriodTest.php`

**Interfaces:**
- Produces:
  - `AttendancePeriod::make(string $type, CarbonInterface $anchor): self` — `$type` ∈ `week|2weeks|month` (boshqasi → `2weeks`).
  - public readonly `string $type`, `CarbonImmutable $anchor`, `CarbonImmutable $start` (00:00), `CarbonImmutable $end` (23:59:59).
  - `workDates(): list<string>` — `Y-m-d`, dushanba–juma, `$start..$end`.
  - `columns(CarbonInterface $now): list<array{date: string, label: string, weekday: string, is_today: bool}>` — `label` `d.m`, `weekday` ∈ `Du, Se, Cho, Pa, Ju`.
  - `previousAnchor(): string`, `nextAnchor(): string` (`Y-m-d`), `label(): string`.
  - `toArray(CarbonInterface $now): array{type, date, label, prev, next, start, end}` (`start`/`end` = birinchi/oxirgi ish kuni `Y-m-d`).

- [ ] **Step 1: Write the failing tests**

```php
public function test_two_weeks_period_covers_previous_and_current_week(): void
{
    $p = AttendancePeriod::make('2weeks', Carbon::parse('2026-10-23'));
    $this->assertSame('2026-10-12', $p->start->toDateString());
    $this->assertSame('2026-10-25', $p->end->toDateString());
    $this->assertCount(10, $p->workDates());
    $this->assertSame(['2026-10-12', '2026-10-16'], [$p->workDates()[0], $p->workDates()[4]]);
    $this->assertNotContains('2026-10-17', $p->workDates());
    $this->assertSame('12.10 – 23.10.2026', $p->label());
    $this->assertSame('2026-10-09', $p->previousAnchor());
    $this->assertSame('2026-11-06', $p->nextAnchor());
}

public function test_week_and_month_periods(): void
{
    $w = AttendancePeriod::make('week', Carbon::parse('2026-10-21'));
    $this->assertSame(['2026-10-19', '2026-10-25'], [$w->start->toDateString(), $w->end->toDateString()]);
    $this->assertSame('2026-10-14', $w->previousAnchor());

    $m = AttendancePeriod::make('month', Carbon::parse('2026-10-23'));
    $this->assertSame(['2026-10-01', '2026-10-31'], [$m->start->toDateString(), $m->end->toDateString()]);
    $this->assertCount(22, $m->workDates());
    $this->assertSame('2026-09-01', $m->previousAnchor());
}

public function test_month_navigation_crosses_year(): void
{
    $m = AttendancePeriod::make('month', Carbon::parse('2026-12-15'));
    $this->assertSame('2027-01-01', $m->nextAnchor());
    $two = AttendancePeriod::make('2weeks', Carbon::parse('2027-01-02')); // shanba
    $this->assertSame('21.12.2026 – 01.01.2027', $two->label());
}

public function test_columns_mark_today_and_weekday_names(): void
{
    $cols = AttendancePeriod::make('2weeks', Carbon::parse('2026-10-23'))->columns(Carbon::parse('2026-10-23 12:00'));
    $this->assertSame(['date' => '2026-10-12', 'label' => '12.10', 'weekday' => 'Du', 'is_today' => false], $cols[0]);
    $this->assertSame(['Du', 'Se', 'Cho', 'Pa', 'Ju'], array_column(array_slice($cols, 0, 5), 'weekday'));
    $this->assertTrue($cols[9]['is_today']);
}

public function test_unknown_type_falls_back_to_two_weeks(): void
{
    $this->assertSame('2weeks', AttendancePeriod::make('year', Carbon::parse('2026-10-23'))->type);
}
```

- [ ] **Step 2: Run** `php artisan test tests/Unit/Attendance/AttendancePeriodTest.php` — Expected: FAIL (class not found).
- [ ] **Step 3: Implement `AttendancePeriod`.** `week`: langar haftasi (`startOfWeek(Carbon::MONDAY)`..`endOfWeek(Carbon::SUNDAY)`), surish ±7 kun. `2weeks`: `start = haftaBoshi − 7 kun`, `end = haftaOxiri`, surish ±14 kun. `month`: `startOfMonth..endOfMonth`, surish — oyning 1-kuni ±1 oy. Yorliq: bir yil bo‘lsa `d.m – d.m.Y`, aks holda `d.m.Y – d.m.Y`.
- [ ] **Step 4: Run** the same command — Expected: PASS.
- [ ] **Step 5: Commit** `git add app/Services/Attendance/AttendancePeriod.php tests/Unit/Attendance/AttendancePeriodTest.php && git commit -m "davomat: davr va ish kunlari (AttendancePeriod)"`

---

### Task 2: `AttendanceGridService::build` — kataklar va statistika

**Files:**
- Create: `app/Services/Attendance/AttendanceGridService.php`
- Test: `tests/Feature/Attendance/AttendanceGridServiceTest.php`

**Interfaces:**
- Consumes: `AttendancePeriod` (Task 1), `App\Services\Reports\AttendanceCalculator`, `Report::TYPE_ENTRY/TYPE_EXIT`, `Note`.
- Produces:
  - `build(Collection $employees, AttendancePeriod $period, CarbonInterface $now): array{rows: list<Row>, stats: Stats}` — `$employees` tartibi saqlanadi; `schedule` va `department` munosabatlari yuklangan bo‘lishi kerak (yuklanmagan bo‘lsa servis o‘zi `loadMissing` qiladi).
  - `Row = array{id: int, name: string, position: ?string, department: array{id: int, name: string}, worked_seconds: int, worked: string, cells: list<Cell>}`
  - `Cell = array{date: string, status: 'on_time'|'late'|'excused'|'absent'|'future'|'pending'|'inactive', in: ?string, out: ?string, inside: bool, early: bool, worked_seconds: int}` — `in`/`out` `H:i`; `inside` = kunning oxirgi o‘tishi kirish **va** sana bugun.
  - `Stats = array{work_days: int, elapsed_days: int, avg_arrival: ?string, on_time: int, late: int, excused: int, absent: int, early: int, percent: array{on_time: int, late: int, excused: int, absent: int}, worked_seconds: int, worked: string, plan_seconds: int, plan: string}`
  - `public static formatDuration(int $seconds): string` — `81 s 24 d`, `0 s 00 d`.

- [ ] **Step 1: Write the failing tests** (yordamchi: `punch($employee, 'Y-m-d H:i', 'in'|'out')` → `Report::factory()->forEmployee(...)->entry()|exit()->create(['occurred_at' => ...])`; jadval `Schedule::factory()->create(['enter_time' => '09:00', 'exit_time' => '18:00'])`; xodim `valid_from_dt` 2026-01-01, `valid_to_dt` 2027-12-31; `travelTo('2026-10-23 12:00:00')`).

```php
public function test_screenshot_example(): void
{
    // Spec "Tekshiruv misoli": 10 kun, 16.10 ga Note (CameLate), 23.10 faqat 06:34 kirish.
    $result = $this->service->build(collect([$employee]), AttendancePeriod::make('2weeks', now()), now());
    $s = $result['stats'];
    $this->assertSame([10, 10, '08:03'], [$s['elapsed_days'], $s['work_days'], $s['avg_arrival']]);
    $this->assertSame([8, 1, 1, 0, 2], [$s['on_time'], $s['late'], $s['excused'], $s['absent'], $s['early']]);
    $this->assertSame(['on_time' => 80, 'late' => 10, 'excused' => 10, 'absent' => 0], $s['percent']);
    $this->assertSame(['81 s 24 d', '72 s 00 d'], [$s['worked'], $s['plan']]);
    $cells = collect($result['rows'][0]['cells'])->keyBy('date');
    $this->assertSame('excused', $cells['2026-10-16']['status']);
    $this->assertSame('late', $cells['2026-10-19']['status']);
    $this->assertSame(['on_time', '06:34', null, true], [$cells['2026-10-23']['status'], $cells['2026-10-23']['in'], $cells['2026-10-23']['out'], $cells['2026-10-23']['inside']]);
    $this->assertSame(['08:14', '18:46', 34320], [$cells['2026-10-12']['in'], $cells['2026-10-12']['out'], $cells['2026-10-12']['worked_seconds']]);
    $this->assertSame('81 s 24 d', $result['rows'][0]['worked']);
}

public function test_absent_and_excused_absence(): void
// 2weeks, o‘tish yo‘q; 2026-10-14 ga Note(Absent). O‘tgan 9 kun (12–22.10): 14.10 excused, qolgan 8 absent;
// 23.10 (bugun) pending. Kutilgan: absent 8, excused 1, plan '72 s 00 d', worked '0 s 00 d', avg_arrival null.

public function test_today_without_entry_is_pending_and_not_counted(): void
// faqat 22.10 08:00/18:00; 23.10 → status 'pending', on_time 1, absent 8 (12–21.10 dan 22.10 tashqari), percent maxraj 9.

public function test_future_days_are_blank(): void
// travelTo('2026-10-21 12:00'): 22.10 va 23.10 → 'future', work_days 10, elapsed_days 8.

public function test_late_by_one_second(): void
// 20.10 09:00:01 kirish → 'late'; 21.10 09:00:00 → 'on_time'.

public function test_days_outside_employee_validity_are_inactive(): void
// valid_from_dt 2026-10-15 → 12–14.10 'inactive', absent ularni sanamaydi (absent 6 = 15,16,19,20,21,22).

public function test_night_shift_attributes_morning_exit_to_previous_day(): void
// jadval 20:00–08:00; kirish 2026-10-19 19:55, chiqish 2026-10-20 08:05 → 19.10: on_time, in '19:55', out '08:05',
// worked_seconds 40200 (12:10 − 1 soat); shu kun rejasi 39600; 20.10 da hech qanday o‘tish yo‘q.

public function test_weekend_punches_are_ignored(): void
// 2026-10-17 (shanba) 10:00 kirish → cells da 17.10 yo‘q, statistikaga ta’sir yo‘q.

public function test_early_leave_counts_only_finished_days(): void
// 22.10 08:00/17:00 → early 1; 23.10 (bugun) 08:00 kirish, 13:00 chiqish, 14:00 kirish → inside true, early hali 1 (2 emas).

public function test_employee_without_valid_day_is_omitted(): void
// valid_to_dt 2026-10-01 → rows [], stats.on_time/absent 0, work_days 10.

public function test_format_duration(): void
{
    $this->assertSame('81 s 24 d', AttendanceGridService::formatDuration(293040));
    $this->assertSame('0 s 05 d', AttendanceGridService::formatDuration(300));
}
```

(Izohdagi raqamlarni testga aniq `assertSame` qilib yozing.)

- [ ] **Step 2: Run** `php artisan test tests/Feature/Attendance/AttendanceGridServiceTest.php` — Expected: FAIL.
- [ ] **Step 3: Implement `build()`.** Ikki so‘rov: (1) `Report` — `employee_id IN (...)`, `occurred_at` `start 00:00` .. `end + 1 kun 12:00`, `orderBy('occurred_at')->orderBy('id')`, faqat kerakli ustunlar (`id, employee_id, type, occurred_at`), `cursor()`; har o‘tish `calculator->workDate(occurred_at, night)` bo‘yicha xodim-kunga, `workDates()` da bo‘lmaganlari tashlanadi. (2) `Note` — `employee_id IN (...)`, `whereDate('dt', '>=', start)`, `<= end`, kalit `employee_id|dt->toDateString()`. Birinchi kirish = eng erta kirish, oxirgi chiqish = eng kech chiqish, `inside` = oxirgi o‘tish kirish va sana bugun. Yakunlangan kun: sana < bugun yoki (sana = bugun va oxirgi o‘tish chiqish). `early` faqat yakunlangan kunda. Davrda amal qilish kuni bo‘lmagan xodim natijaga kirmaydi. Reja: jadval davomiyligi (tungi smenada +24 soat) − 3600. O‘rtacha kelish: birinchi kirishi bor xodim-kunlar (holati `inactive`/`future` bo‘lmagan) kirish vaqtining sutkadagi soniyalari o‘rtachasi (tungi smenada `shiftMoment`), `H:i`, yo‘q bo‘lsa `null`. Foiz `round(son * 100 / (on_time+late+excused+absent))`, maxraj 0 → 0.
- [ ] **Step 4: Run** the same command — Expected: PASS.
- [ ] **Step 5: Commit** `git commit -m "davomat: kataklar va statistika (AttendanceGridService)"`

---

### Task 3: Kun tafsiloti — `AttendanceGridService::day`

**Files:**
- Modify: `app/Services/Attendance/AttendanceGridService.php`
- Test: `tests/Feature/Attendance/AttendanceDayDetailTest.php`

**Interfaces:**
- Produces: `day(Employee $employee, string $date, CarbonInterface $now): array{employee: array{id: int, name: string, position: ?string}, date: string, status: string, in: ?string, out: ?string, inside: bool, worked: string, schedule: string, punches: list<array{time: string, type: 'entry'|'exit', label: string, place: string}>, notes: list<array{id: int, type: int, type_label: string, note: string}>}` — `status`, `in`, `out`, `inside`, `worked` aynan `build()` dagi shu katak bilan bir xil (bitta xodimga `build` chaqirib olinadi); `schedule` = `'09:00–18:00'` (en-dash); `label` `Kirdi`/`Chiqdi`; `place` = `'kategoriya · qurilma'`, qurilma yo‘q bo‘lsa `'Noma’lum qurilma'`.

- [ ] **Step 1: Write the failing test** `test_day_detail_lists_punches_and_notes`: qurilma `Device::factory()->create(['name' => '1-kirish'])` kategoriyasi `Category` nomi `'Bosh bino'`; 13.10: 08:51 kirish, 13:04 chiqish, 13:55 kirish, 18:13 chiqish (hammasi shu qurilmada); Note(LeftEarly, 'Shifokorga') 13.10. Kutilgan: `status` `'excused'`, `in` `'08:51'`, `out` `'18:13'`, `worked` `'8 s 22 d'`, `schedule` `'09:00–18:00'`, `punches` 4 ta, `punches[1]` = `['time' => '13:04', 'type' => 'exit', 'label' => 'Chiqdi', 'place' => 'Bosh bino · 1-kirish']`, `notes[0]['type_label']` `'Erta ketdi'`. Ikkinchi test `test_day_detail_without_device_or_notes`: qurilmasiz kirish → `place` `'Noma’lum qurilma'`, `notes` `[]`.
- [ ] **Step 2: Run** `php artisan test tests/Feature/Attendance/AttendanceDayDetailTest.php` — FAIL.
- [ ] **Step 3: Implement `day()`** — o‘tishlar `Report::with('device.category')`, o‘sha ish kuniga tushadiganlar (`workDate`), vaqt bo‘yicha.
- [ ] **Step 4: Run** — PASS.
- [ ] **Step 5: Commit** `git commit -m "davomat: kun tafsiloti"`

---

### Task 4: Controller, marshrutlar, huquq, izoh qo‘shish

**Files:**
- Create: `app/Http/Controllers/AttendanceController.php`
- Create: `database/migrations/2026_10_23_120000_add_attendance_permission.php` (`2026_08_03_161000_add_groups_permission.php` namunasi, kalit `attendance`)
- Modify: `config/menu_permissions.php` (`'attendance' => 'Davomat'`, `reports` dan keyin), `routes/web.php`, `app/Http/Middleware/HandleInertiaRequests.php` (`'attendance' => $user->can($menuPermissions['attendance'])`), `resources/js/routes.js`, `resources/js/config/adminNav.js` (`{ key: 'attendance', href: route('admin.attendance.index'), label: 'Davomat', icon: ScheduleOutlined }` — "Hisobotlar" dan oldin)
- Test: `tests/Feature/Admin/AttendancePageTest.php`

**Interfaces:**
- Consumes: Task 1–3.
- Produces (marshrutlar `admin.` guruhida):
  - `GET attendance` → `index`, `admin.attendance.index`, `permission:Davomat`.
  - `GET attendance/export` → `export`, `admin.attendance.export`, `permission:Davomat` (Task 5 to‘ldiradi).
  - `POST attendance/notes` → `storeNote(StoreNoteRequest)`, `admin.attendance.notes.store`, `permission:Davomat` + `permission:Izohlar`; `NoteService::create($request->validated())`, `back()->with('success', 'Izoh muvaffaqiyatli yaratildi.')`.
  - `routes.js`: `'admin.attendance.index': () => '/admin/attendance'`, `'admin.attendance.export': () => '/admin/attendance/export'`, `'admin.attendance.notes.store': () => '/admin/attendance/notes'`.
  - Filtr `filterRules()`: `period` `nullable|in:week,2weeks,month`, `date` `nullable|date_format:Y-m-d`, `department_id`/`group_id` `nullable|integer`, `search` `nullable|string|max:100`, `tab` `nullable|in:attendance,notes`, `day_employee_id` `nullable|integer`, `day_date` `nullable|date_format:Y-m-d`.
  - Xodimlar: `Employee::query()->with(['schedule', 'department:id,name'])`, filtrlar (`search` — `name` yoki `card` LIKE), tartib bo‘lim nomi, so‘ng `employees.name` (join yoki `orderBy` subquery).
  - Inertia `Admin/Attendance/Index` proplari: `period` (`toArray`), `days` (`columns`), `filters`, `stats` (closure), `rows` (closure — `LengthAwarePaginator` 50 tadan, `build()` dan chiqqan qatorlar `forPage` bilan), `department_counts` (closure, `{department_id: soni}` filtr bo‘yicha), `day` (closure; `day_employee_id`+`day_date` bo‘lsa va xodim scope ichida topilsa `day()`, aks holda `null`), `notes` (closure; faqat `tab=notes` bo‘lsa `NoteService::paginate(12, ['date_from' => start, 'date_to' => end, 'search' => ...])`, notes elementida `dt` `Y-m-d` satr), `departments` (`Department::visibleTo`), `groups`, `employees` (`id,name`), `types` (`NoteType` `value/label`). `build()` bir so‘rovda bir marta hisoblanadi (memo), `stats`/`rows`/`department_counts` undan oladi.

- [ ] **Step 1: Write the failing tests**
  - `test_page_requires_attendance_permission` — huquqsiz 403; `Permission::where('name', 'Davomat')->exists()` (migratsiya).
  - `test_page_renders_grid_and_stats` — 1 xodim, 22.10 08:00/18:00: `component('Admin/Attendance/Index')`, `period.label` `'12.10 – 23.10.2026'`, `days` 10, `rows.data.0.cells` 10, `stats.on_time` 1.
  - `test_filters_by_department_and_search` — ikki bo‘lim; `department_id` → faqat biri; `search` ism qismi bo‘yicha.
  - `test_rows_are_paginated_by_fifty` — 51 xodim: `rows.data` 50, `rows.total` 51, `page=2` da 1; `stats` 51 xodim bo‘yicha (masalan `absent` = 51 × 9).
  - `test_department_user_sees_only_own_department` — `createDepartmentUserWithPermissions`.
  - `test_day_detail_is_returned_for_requested_cell` — `day_employee_id`, `day_date=2026-10-22` → `day.in` `'08:00'`.
  - `test_day_detail_respects_department_scope` — boshqa bo‘lim xodimi → `day` `null`.
  - `test_notes_tab_lists_period_notes` — `tab=notes`, davr ichidagi va tashqarisidagi izoh → faqat bittasi, `notes.data.0.dt` `'2026-10-16'`.
  - `test_store_note_redirects_back_with_success` — `from(route('admin.attendance.index', ['date' => '2026-10-23']))->post(...)` → `assertRedirect(<shu url>)`, `session('success')`, `notes` jadvalida yozuv; `Izohlar` huquqisiz → 403.
- [ ] **Step 2: Run** `php artisan test tests/Feature/Admin/AttendancePageTest.php` — FAIL.
- [ ] **Step 3: Implement** controller, migratsiya, config, marshrutlar, `can`, `routes.js`, `adminNav.js`. Sahifa komponenti hali yo‘q — testlar `assertInertia` bilan komponent nomini tekshiradi (Inertia test `ensure_pages_exist` yoqilgan bo‘lsa, `resources/js/Pages/Admin/Attendance/Index.jsx` ga vaqtinchalik minimal komponent qo‘ying; Task 6 uni to‘ldiradi).
- [ ] **Step 4: Run** — PASS; so‘ng `composer test` — faqat ma’lum 4 ta eski xato (EmployeeAdminTest filters.department_id, EmployeeExportTest, SanctumUserApiTest ×2).
- [ ] **Step 5: Commit** `git commit -m "davomat: sahifa marshruti, huquq va izoh qo‘shish"`

---

### Task 5: Excel eksport

**Files:**
- Create: `app/Exports/AttendanceExport.php` (`ReportsExport` uslubi: `FromCollection, ShouldAutoSize, WithHeadings, WithMapping`)
- Modify: `app/Http/Controllers/AttendanceController.php` (`export`)
- Test: `tests/Feature/Admin/AttendanceExportTest.php`

**Interfaces:**
- Consumes: `build()` (sahifalashsiz, barcha filtrlangan xodimlar), `AttendancePeriod::columns()`.
- Produces: sarlavhalar `['№', 'Bo‘lim', 'Xodim', 'Lavozim', …har ish kuni "12.10 Du"…, 'Ishladi']`; katak matni: `on_time`/`late` → `"08:14–18:46"` (chiqish yo‘q: `"08:14–ishda"` bugun, aks holda `"08:14–—"`), `excused` → `"Sababli"` (+ `" 08:13–17:11"` agar vaqt bo‘lsa), `absent` → `"Kelmadi"`, `future`/`pending` → `""`, `inactive` → `"—"`. Fayl: `davomat_{birinchi ish kuni}_{oxirgi ish kuni}.xlsx`.

- [ ] **Step 1: Write the failing test** `test_export_downloads_grid` — `Excel::fake()`, GET `admin.attendance.export` (`date=2026-10-23`), `Excel::assertDownloaded('davomat_2026-10-12_2026-10-23.xlsx', fn (AttendanceExport $e) => $e->headings()[4] === '12.10 Du' && $e->map($e->collection()->first())[4] === '08:00–18:00')`; huquqsiz 403.
- [ ] **Step 2: Run** `php artisan test tests/Feature/Admin/AttendanceExportTest.php` — FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** — PASS.
- [ ] **Step 5: Commit** `git commit -m "davomat: Excel eksport"`

---

### Task 6: Frontend — sahifa, jadval, kun oynasi, izoh oynasi

**Files:**
- Create: `resources/js/Pages/Admin/Attendance/Index.jsx` (sarlavha, davr boshqaruvi, filtrlar, tablar, holat)
- Create: `resources/js/Components/Attendance/AttendanceStats.jsx`, `AttendanceGrid.jsx`, `DayDrawer.jsx`, `NoteModal.jsx`, `NotesTab.jsx`
- Create: `resources/js/utils/uzDate.js` — `WEEKDAYS`, `MONTHS` (`LiveClock.jsx` dan ko‘chiriladi, `LiveClock` shuni import qiladi) va `formatDayTitle('2026-10-13') === 'Seshanba, 13-oktabr 2026'`
- Modify: `resources/js/Components/Live/LiveClock.jsx`

**Interfaces:**
- Consumes: Task 4 proplari va marshrutlari.
- Xatti-harakat:
  - Davr: ‹ / › `router.get(route('admin.attendance.index'), {...filters, date: period.prev|next}, { preserveState: true, preserveScroll: true })`; AntD `Segmented` `Hafta | 2 hafta | Oy` → `period`.
  - Filtr: qidiruv (debounce 400 ms), bo‘lim, guruh (tanlangan bo‘lim guruhlari) — o‘zgarsa `page` tashlanadi.
  - Tablar: AntD `Tabs` `Davomat` / `Arizalar` → `tab` parametri (`only: ['notes', 'filters']` bilan qisman yuklash).
  - Jadval: chap ustun (ism qalin, ostida lavozim) `position: sticky; left: 0`; gorizontal aylantirish; bo‘lim sarlavha qatori (nom + `department_counts[id]`, ochish/yopish — holat komponent ichida); katak 72×48 px, yuqorida `in`, pastda `out` / `inside` bo‘lsa "ishda" / yo‘q bo‘lsa "—"; ranglar: `on_time` — `positive` och fon, `late` — `caution` och fon, `excused` — oq fon + `positive` ramka, `absent` — `negative` och fon, `inactive` — kulrang "—", `future`/`pending` — bo‘sh; `is_today` ustun sarlavhasi va katagi `brand`/ko‘k ramka. Oxirgi ustun "Ishladi" (`row.worked`). Ostida rang izohi, "Ishladi — tushlik (1 soat) chegirib", "Kunni bosing — o‘sha kungi kirish-chiqishlar va arizalar ochiladi." `Pagination` komponenti.
  - Katak bosilganda (`future`/`inactive` dan tashqari): `router.reload({ data: { day_employee_id, day_date }, only: ['day'], preserveState: true, preserveScroll: true })`; `props.day` bo‘lsa `DayDrawer` ochiq (o‘ngdan, 480 px): sarlavha `formatDayTitle(day.date)` + holat belgisi, 4 kartochka (Kelgan, Ketgan, Ishladi + "tushlik (1 soat) chegirib", Ish vaqti + "Dushanba–juma"), "Kirish-chiqishlar" vaqt chizig‘i (kirish — yashil, chiqish — kulrang nuqta; `label`, ostida `place`), "Shu kunga arizalar" (`type_label` — `note`) yoki "Bu kunga ariza berilmagan.", pastda "Yopish" (parametrlarni olib tashlab `only: ['day']` reload) va "Shu kun uchun ariza berish" (`can.notes`).
  - `NoteModal`: `useForm({ employee_id, type, dt, note })` → `post(route('admin.attendance.notes.store'), { preserveScroll: true, onSuccess: yopish })`; xodim — qidiruvli `Select` (`props.employees`), tur — `props.types`, sana — `<Input type="date">`, xato matnlari `FormField` bilan. Sarlavhadagi "Ariza berish" bo‘sh oynani, drawerdagi tugma xodim va sanani to‘ldirib ochadi.
  - `NotesTab`: jadval (Xodim, Tur, Sana, Izoh, amallar → `admin.notes.edit`), `Pagination`.
  - Matnlar o‘zbekcha; `PageHeader`, `FlashMessages` (layout orqali), admin temasi tokenlari (yorug‘/qorong‘i).

- [ ] **Step 1: Implement** komponentlar.
- [ ] **Step 2: Run** `npm run build` — Expected: xatosiz.
- [ ] **Step 3: Brauzerda tekshirish** (`php artisan serve` + `npm run dev` ishlayotgan bo‘lsa): `/admin/attendance` ochiladi, ‹ › va Hafta/2 hafta/Oy ishlaydi, katak bosilganda drawer, izoh qo‘shilgach katak "Sababli" bo‘ladi va flash chiqadi, Arizalar tabi, qorong‘i tema. Konsolda xato yo‘q.
- [ ] **Step 4: Run** `composer test` — Task 4 dagi kabi.
- [ ] **Step 5: Commit** `git commit -m "davomat: sahifa interfeysi"`

---

### Task 7: Hujjatlar va Figma

**Files:**
- Modify: `CLAUDE.md` ("Reports" bo‘limiga bir xatboshi: `/admin/attendance`, `AttendanceGridService`, dushanba–juma, 1 soat tushlik, izoh = "Sababli").

- [ ] **Step 1:** CLAUDE.md ga xatboshi; commit `git commit -m "davomat: CLAUDE.md"`.
- [ ] **Step 2:** Figma (`E-idora`, `Ekranlar` sahifasi): "Davomat" bo‘limi — jadval ekrani (2 hafta), kun oynasi ochiq holati, izoh oynasi; mavjud DOM→Figma jarayoni bilan (sidebar `Shell/Sidebar`, nav `Davomat`). Xodim ismlari namunaga almashtiriladi.
