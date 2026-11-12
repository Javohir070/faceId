# Tarkibiy bo‘linmalar Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** "Tarkibiy bo'linmalar" sahifasi: har guruh kartochkasida xodimlar rasmi, binodagilar yashil, binoda yo‘qlar qizil ramkada; tepada hisoblagichlar va filtrlar; xodim bosilganda popover.

**Architecture:** Faqat o‘qiydigan sahifa. `UnitBoardService` bugungi `reports` va `notes` dan har xodim holatini hisoblaydi (Davomat bilan bir xil "bugungi ish kuni" va "ichkarida" qoidasi), xodimlarni guruh daraxti tartibida kartochkalarga ajratadi. `UnitController@index` uni Inertia prop sifatida beradi, React sahifa 30 soniyada qisman qayta yuklaydi. Guruh daraxti tartibi `MembersPanelService` dan umumiy `GroupTree` klassiga chiqariladi.

**Tech Stack:** Laravel 12, Inertia.js, React 19 (JSX), Tailwind, Ant Design 6, spatie/laravel-permission, PHPUnit (SQLite).

**Spec:** `docs/superpowers/specs/2026-11-12-structural-units-design.md`

## Global Constraints

- Barcha UI matni, flash va izohlar o‘zbekcha. Ikonkalar faqat `@ant-design/icons`.
- Ruxsat nomi aynan: `Tarkibiy bo'linmalar` (to‘g‘ri apostrof `'`), config kaliti `units`.
- Route: `GET /admin/units`, nomi `admin.units.index`.
- Sahifa qurilmalarga so‘rov yubormaydi, faqat bazadan o‘qiydi.
- `withoutGlobalScopes()` bu funksiyada ishlatilmaydi — bo‘lim cheklovi global scope’lar orqali.
- `employees.card` (JSHSHIR) javobga hech qachon kirmaydi.
- Yuz rasmi URL faqat foydalanuvchida `Xodimlar` ruxsati bo‘lsa beriladi, aks holda `null`.
- Ichki navigatsiya faqat Inertia `Link`/`router`; yangi route `resources/js/routes.js` ga qo‘shiladi (Ziggy yo‘q).
- Testlar: `composer test` (SQLite). Format: `vendor/bin/pint`.

## Review Focus

1. **Tungi smena va yarim tun** — 20:00–08:00 jadvalidagi xodim soat 02:00 da ham "binoda" va Kirgan vaqti kechagi 20:05. Test: Task 2 `test_night_shift_employee_stays_inside_after_midnight`.
2. **Shanba/yakshanba** — hech kim "sababsiz" emas, lekin binoda/binoda emas ishlaydi. Test: Task 2 `test_weekend_has_no_unexcused`.
3. **Bo‘limi biriktirilmagan `user`** — bo‘sh sahifa, xato emas. Test: Task 3 `test_user_without_department_sees_empty_board`.
4. **Kecha kirib, chiqmay ketgan kunduzgi xodim** — bugun "binoda emas" (kechagi kirish bugunga o‘tmaydi). Test: Task 2 `test_yesterdays_unclosed_entry_does_not_count_today`.
5. **Kechagi izoh** bugungi "sababli"ga ta’sir qilmaydi. Test: Task 2 `test_yesterdays_note_does_not_excuse_today`.

---

### Task 1: Guruh daraxti tartibini umumiy klassga chiqarish

**Files:**
- Create: `app/Services/Groups/GroupTree.php`
- Modify: `app/Services/Employees/MembersPanelService.php` (private `groupOrder()` olib tashlanadi, chaqiruvlar `GroupTree` ga)
- Test: `tests/Feature/Admin/MembersPanelTest.php` (mavjud, o‘zgarmaydi)

**Interfaces:**
- Produces: `App\Services\Groups\GroupTree::order(int $departmentId, ?int $rootId = null): array<int, array{index: int, path: string}>` — kalit guruh id; tanasi hozirgi `MembersPanelService::groupOrder()` bilan aynan bir xil (global scope ishlaydi, sikldan himoya, yo‘l `' › '` bilan).
- `MembersPanelService` konstruktorga `private readonly GroupTree $groupTree` oladi (container orqali).

- [ ] **Step 1:** `GroupTree::order()` ni yarating — `MembersPanelService::groupOrder()` tanasini ko‘chiring; `MembersPanelService` dagi ikki chaqiruvni `$this->groupTree->order(...)` ga almashtiring. `forDepartment()` dagi `null` rootId va `forGroup()` dagi `$group->department_id` o‘zgarmaydi.
- [ ] **Step 2:** Run `php artisan test tests/Feature/Admin/MembersPanelTest.php` — Expected: PASS (xatti-harakat o‘zgarmagan).
- [ ] **Step 3:** Commit: `git commit -m "GroupTree: guruh daraxti tartibi umumiy klassga chiqarildi"`

---

### Task 2: `UnitBoardService` — holatlar va kartochkalar

**Files:**
- Create: `app/Services/Units/UnitBoardService.php`
- Test: `tests/Feature/Admin/UnitBoardServiceTest.php`

**Interfaces:**
- Consumes: `GroupTree::order()` (Task 1), `AttendanceCalculator::isNightShift()` / `workDate()`, `EmployeeResource::faceThumbnailUrl()`, `NoteType::label()`.
- Produces: `App\Services\Units\UnitBoardService::build(CarbonInterface $now, bool $withFaces): array` qaytaradi:

```php
[
    'counts' => ['total' => int, 'inside' => int, 'outside' => int, 'excused' => int, 'unexcused' => int],
    'units' => [ // ko‘rinadigan xodimi bor kartochkalar, tartiblangan
        [
            'key' => 'group:5' | 'ungrouped:3',      // 3 = department_id
            'title' => 'Ishlar bo‘limi › Texnik xodimlar' | 'Guruhsiz',
            'department' => 'Agentlik',
            'total' => int,
            'inside' => int,
            'employees' => [[
                'id' => int, 'name' => string, 'position' => ?string, 'group' => ?string, // group = title, guruhsizda null
                'inside' => bool, 'excused' => bool, 'unexcused' => bool,
                'in' => ?'H:i', 'out' => ?'H:i',     // out faqat !inside && in !== null bo‘lsa
                'notes' => list<string>,             // NoteType::label(), takrorsiz
                'face_url' => ?string,
            ]],
        ],
    ],
]
```

Qoidalar (spec "Qoidalar"):
- Xodimlar: `Employee::query()` (global scope) `valid_from_dt <= $now` va `valid_to_dt >= $now`; faqat kerakli ustunlar (`card` emas), `with(['schedule', 'department:id,name'])`, `$withFaces` bo‘lsa `withMax('face', 'updated_at')`.
- Har xodim uchun `$night` — `AttendanceGridService::scheduleInfo()` dagidek (jadval yo‘q → kunduzgi); `$today = workDate(Carbon::instance($now), $night)`.
- O‘tishlar: `Report` `whereIn('employee_id', ...)`, `occurred_at` `[$now - 1 kun 00:00, $now]`, `orderBy('occurred_at')->orderBy('id')`; xodimga faqat `workDate(occurred_at, $night) === $today` bo‘lganlari. `in` = birinchi kirish, `out` = oxirgi chiqish, `inside` = oxirgi o‘tish `TYPE_ENTRY`.
- Izohlar: `Note` `whereIn('employee_id', ...)`, `dt` kecha..bugun oralig‘ida; xodimga `dt === $today` bo‘lganlari. `excused = notes !== []`.
- `unexcused = in === null && ! excused && Carbon::parse($today)->isWeekday()`.
- Kartochkalar: bo‘limlar nomi bo‘yicha; bo‘lim ichida `GroupTree::order($departmentId)` tartibi, keyin `ungrouped:{id}`. Kartochka ichida avval `inside`, keyin ism bo‘yicha. Bo‘sh kartochka chiqmaydi. Daraxtda topilmagan `group_id` (masalan boshqa bo‘lim guruhi) — guruhsiz deb olinadi.

- [ ] **Step 1: Failing testlarni yozing** (`UnitBoardServiceTest`, `RefreshDatabase`, `$this->travelTo(...)`, `app(UnitBoardService::class)->build(now(), false)`; yordamchilar `AttendancePageTest` dagidek `employee()`, `punch()`, jadval 09:00–18:00; testlar `actingAs(createUserWithPermissions())` ostida):
  - `test_statuses_for_today` — travelTo `2026-11-11 12:00` (chorshanba). A: kirish 08:52 → `inside=true, in='08:52', out=null`. B: kirish 08:00, chiqish 11:00 → `inside=false, in='08:00', out='11:00', unexcused=false`. C: o‘tish/izoh yo‘q → `inside=false, unexcused=true`. D: `NoteType::Absent` izoh `2026-11-11` → `excused=true, unexcused=false, notes=['Kelmadi']`. `counts == ['total'=>4,'inside'=>1,'outside'=>3,'excused'=>1,'unexcused'=>1]`.
  - `test_weekend_has_no_unexcused` — travelTo `2026-11-14 12:00` (shanba), o‘tishsiz xodim → `unexcused=false`, `counts.unexcused=0`, `counts.outside=1`.
  - `test_night_shift_employee_stays_inside_after_midnight` — jadval 20:00–08:00, travelTo `2026-11-12 02:00`, kirish `2026-11-11 20:05` → `inside=true, in='20:05'`.
  - `test_yesterdays_unclosed_entry_does_not_count_today` — kunduzgi, travelTo `2026-11-11 12:00`, kirish `2026-11-10 09:00` → `inside=false, in=null, unexcused=true`.
  - `test_yesterdays_note_does_not_excuse_today` — izoh `2026-11-10`, travelTo `2026-11-11 12:00` → `excused=false, unexcused=true`.
  - `test_expired_and_upcoming_employees_are_hidden` — `valid_to_dt` 2026-11-01 va `valid_from_dt` 2026-12-01 bo‘lgan xodimlar → `counts.total=0`, `units=[]`.
  - `test_units_follow_group_tree_with_ungrouped_last` — bo‘lim "Agentlik": guruhlar "Ishlar bo‘limi" → bolasi "Texnik xodimlar", "Devonxona", bo‘sh guruh "Fond"; har birida (Fond’dan tashqari) bittadan xodim + guruhsiz xodim. `array_column($units, 'title') == ['Devonxona', 'Ishlar bo‘limi', 'Ishlar bo‘limi › Texnik xodimlar', 'Guruhsiz']`, har birida `department='Agentlik'`. Ikkinchi bo‘lim "Byuro" (nomi bo‘yicha oldin) kartochkalari ro‘yxat boshida.
  - `test_inside_employees_come_first_in_card` — bitta guruhda "Aziz" (binoda emas) va "Zafar" (binoda) → `employees[0].name='Zafar'`, kartochka `total=2, inside=1`.
  - `test_face_url_only_when_requested` — yuzi bor xodim (`$employee->face()->create(['face' => base64_encode('rasm')])`): `build(now(), true)` → `face_url` `'/face?v='` ni o‘z ichiga oladi; `build(now(), false)` → `null`.
- [ ] **Step 2:** Run `php artisan test tests/Feature/Admin/UnitBoardServiceTest.php` — Expected: FAIL ("Class ... UnitBoardService not found").
- [ ] **Step 3:** `UnitBoardService::build()` ni yuqoridagi qoidalar bilan yozing (konstruktor: `AttendanceCalculator $calculator`, `GroupTree $groupTree`).
- [ ] **Step 4:** Run o‘sha test — Expected: PASS.
- [ ] **Step 5:** Commit: `git commit -m "UnitBoardService: bugungi binoda/binoda emas/sababli/sababsiz holatlari"`

---

### Task 3: Ruxsat, route, controller, sidebar

**Files:**
- Modify: `config/menu_permissions.php` — `resources.units = "Tarkibiy bo'linmalar"` (`attendance` dan keyin); `user_role.full` ga `'units'`.
- Create: `database/migrations/2026_11_12_100000_add_units_permission.php` — ruxsatni yaratadi, `super-admin` va `user` rollariga beradi (bor bo‘lsa); `down()` ruxsatni o‘chiradi. Namuna: `2026_11_04_100000_grant_event_pull_to_all_roles.php`, nom satr sifatida (config’dan emas).
- Create: `app/Http/Controllers/UnitController.php`
- Modify: `routes/web.php` — `attendance` route’laridan keyin.
- Modify: `app/Http/Middleware/HandleInertiaRequests.php` — `can.units`.
- Modify: `resources/js/routes.js` — `'admin.units.index': () => '/admin/units'`.
- Modify: `resources/js/config/adminNav.js` — "Davomat"dan keyin `{ key: 'units', href: route('admin.units.index'), label: "Tarkibiy bo'linmalar", icon: PartitionOutlined }`.
- Create: `resources/js/Pages/Admin/Units/Index.jsx` — hozircha `AdminLayout` + `PageHeader title="Tarkibiy bo'linmalar"` (Task 4 to‘ldiradi).
- Test: `tests/Feature/Admin/UnitPageTest.php`; Modify: `tests/Feature/Admin/DefaultUserRoleTest.php`, `tests/Feature/Admin/DepartmentIsolationTest.php`.

**Interfaces:**
- Consumes: `UnitBoardService::build()` (Task 2).
- Produces: Inertia komponent `Admin/Units/Index`, prop `board` (Task 2 shakli), lazy closure — qisman reload `only: ['board']` ishlaydi. `can.units: bool`.
- `UnitController::index(Request $request): Response` → `Inertia::render('Admin/Units/Index', ['board' => fn () => $this->board->build(now(), $request->user()->can(config('menu_permissions.resources')['employees']))])`.

- [ ] **Step 1: Failing testlar** (`UnitPageTest`, travelTo `2026-11-11 12:00`):
  - `test_page_requires_units_permission` — `Permission` jadvalida `Tarkibiy bo'linmalar` bor; faqat `Hisobotlar` ruxsatli foydalanuvchi → 403.
  - `test_page_renders_board` — super-admin (`createUserWithPermissions([units])`) → `component('Admin/Units/Index')`, `board.counts.total=1`, `board.units.0.employees.0.name`.
  - `test_department_user_sees_only_own_department` — `createDepartmentUserWithPermissions($own->id, [units])`, boshqa bo‘limda xodim → `board.counts.total=1`, `board.units` faqat o‘z bo‘lim nomi, javobda boshqa xodim ismi yo‘q (`assertDontSee`).
  - `test_user_without_department_sees_empty_board` — `User::factory()->create(['department_id' => null])` + `user` roli + units ruxsati → 200, `board.units=[]`, `board.counts.total=0`.
  - `test_face_url_hidden_without_employees_permission` — yuzi bor xodim; faqat units ruxsati → `face_url=null`; units + Xodimlar → URL.
  - `test_card_is_never_sent` — xodim `card='12345678901234'` → `assertDontSee('12345678901234')`.
  - `DefaultUserRoleTest::allowedPages` ga `'tarkibiy bo‘linmalar' => ['admin.units.index']`.
  - `DepartmentIsolationTest` dagi sahifalar ro‘yxatiga `route('admin.units.index')`.
- [ ] **Step 2:** Run `php artisan test tests/Feature/Admin/UnitPageTest.php tests/Feature/Admin/DefaultUserRoleTest.php tests/Feature/Admin/DepartmentIsolationTest.php` — Expected: FAIL (route yo‘q).
- [ ] **Step 3:** Yuqoridagi fayllarni yozing/o‘zgartiring.
- [ ] **Step 4:** O‘sha testlar — Expected: PASS.
- [ ] **Step 5:** Commit: `git commit -m "Tarkibiy bo'linmalar: ruxsat, route, controller, sidebar"`

---

### Task 4: Sahifa UI — hisoblagichlar, filtr, kartochkalar, popover

**Files:**
- Modify: `resources/js/Pages/Admin/Units/Index.jsx`
- Create: `resources/js/Components/Units/UnitCard.jsx`
- Create: `resources/js/Components/Units/EmployeeBubble.jsx`

**Interfaces:**
- Consumes: prop `board` (Task 2 shakli), `can.employees` (`usePage().props.can`), `useInertiaPoll(['board'], 30000)`, `initials()` (`Components/InitialsAvatar`), `route('admin.employees.show', id)`.
- Produces: `UnitCard({ unit, employees })`, `EmployeeBubble({ employee, canOpenProfile })`.

Qarorlar:
- Tepada `PageHeader title="Tarkibiy bo'linmalar"`, ostida uchta hisoblagich: `Binoda: {inside} nafar`, `Binoda emas: {outside} nafar`, `Sababsiz kelmaganlar: {unexcused} nafar`.
- Filtr `Segmented` (lokal state, URL’ga yozilmaydi): `all` Hammasi, `inside` Binoda, `outside` Binoda emas, `excused` Sabablilar, `unexcused` Sababsiz. Predikat: `inside → e.inside`, `outside → !e.inside`, `excused → e.excused`, `unexcused → e.unexcused`. Filtrdan keyin bo‘sh kartochka yashiriladi.
- Kartochkalar to‘ri: `grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4`.
- `UnitCard`: nomi bir qatorda `truncate`, to‘liq nomi `Tooltip`; yorliq — `department` katta harflarda kichik ramkali; `Jami — {unit.total} nafar · Binoda — {unit.inside} nafar` (filtrdan qat’i nazar kartochkaning to‘liq sonlari); rasmlar `flex flex-wrap gap-1.5`.
- `EmployeeBubble`: 40px dumaloq rasm (`face_url` bo‘lmasa yoki yuklanmasa — `initials()`), `ring-2` — `ring-positive` binoda, `ring-negative` aks holda; `aria-label` = ism. Ant `Popover` `trigger="click"`: 96px rasm + burchakda holat nuqtasi, ism, lavozim, guruh, `Kirgan vaqti: {in}` yoki `Bugun kelmagan`, `out` bo‘lsa `Chiqqan vaqti: {out}`, `notes` bo‘lsa `Sabab: {notes.join(', ')}`, `canOpenProfile` bo‘lsa `Link` "Profilni ochish".
- Bo‘sh holat (`units` bo‘sh yoki filtrdan keyin hech narsa): Ant `Empty` "Ko‘rsatiladigan xodim yo‘q".
- Ranglar faqat tema tokenlari (`positive`, `negative`, `ink`, `surface`, `line`) — yorug‘ va qorong‘i rejimda ishlaydi.

- [ ] **Step 1:** Komponentlarni yozing.
- [ ] **Step 2:** Run `npm run build` — Expected: xatosiz build.
- [ ] **Step 3:** Commit: `git commit -m "Tarkibiy bo'linmalar sahifasi: kartochkalar, filtr, xodim popover"`

---

### Task 5: To‘liq tekshiruv

- [ ] **Step 1:** Run `vendor/bin/pint` — o‘zgargan PHP fayllar formatlanadi.
- [ ] **Step 2:** Run `composer test` — Expected: hammasi PASS.
- [ ] **Step 3:** `php artisan migrate` (lokal), dev serverni ishga tushirib `/admin/units` ni brauzerda oching: kartochkalar, ranglar, filtr, popover, qorong‘i rejim, telefon kengligi (375px) tekshiriladi; konsolda xato yo‘q.
- [ ] **Step 4:** Commit (pint o‘zgarishlari bo‘lsa).
