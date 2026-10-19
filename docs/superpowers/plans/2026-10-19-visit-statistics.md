# Tashrif statistikasi Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Zal ekrani uchun alohida "Tashrif statistikasi" sahifasi (`/admin/live/stats`) va uning mahalliy/xorijiy hisobi uchun xodimga `country` maydoni.

**Architecture:** `employees.country` (ISO-2, default `UZ`) + forma/ko‘rish sahifasi. Hisoblar yangi `VisitStatsService::today()` da, guruh hisobi `LiveMonitorService::enteredByGroup()` dan qayta ishlatiladi. Sahifa `LiveMonitorController@stats` → `Admin/Live/Stats.jsx`, jonli monitor CSS tokenlari va masshtabi ustiga `visit-stats.css`.

**Tech Stack:** Laravel 12, Inertia + React 19 (JSX), Ant Design 6 (faqat forma Select), oddiy CSS, PHPUnit (SQLite :memory:).

**Spec:** `docs/superpowers/specs/2026-10-19-visit-statistics-design.md`

## Global Constraints

- Barcha UI matnlari, flash xabarlar va izohlar o‘zbekcha (sahifadagi ikkinchi qator inglizcha izohlar Figma bo‘yicha qoladi).
- Yangi huquq yo‘q: sahifa `permission:` + `config('menu_permissions.resources')['live']` ("Jonli kuzatuv").
- Hamma hisob bo‘lim cheklovi (`BelongsToDepartmentScope`) orqali o‘tadi; `withoutGlobalScopes()` ishlatilmaydi.
- "Odam" = takrorlanmas `employee_id`; "bugun" = `now()->startOfDay()`..`now()->endOfDay()`; "so‘nggi soat" = `now()->subHour()`..`now()`.
- Mahalliy = `country === 'UZ'`, xorijiy = boshqa har qanday kod.
- Ikonlar faqat `@ant-design/icons`; yangi npm/composer paket qo‘shilmaydi.
- Testlar faqat `composer test` / `php artisan test` (SQLite); `tests/TestCase.php` ga tegilmaydi.
- Format: `vendor/bin/pint` o‘zgargan PHP fayllarga.

## Review Focus

- Bugun faqat **chiqish** qilgan odam (kirishsiz) → `entered`/`inside` ga qo‘shilmaydi, `exited` ga qo‘shiladi. Test: Task 2 `test_exit_only_person_counts_as_exited_but_not_entered`.
- Xodimga bog‘lanmagan o‘tish (`employee_id = null`, noma’lum karta) → hech bir hisobga va `last_arrival` ga kirmaydi. Test: Task 2 `test_passes_without_employee_are_ignored`.
- `department_id` si `null` bo‘lgan bo‘limli foydalanuvchi → hamma son 0, `last_arrival` `null`, xato yo‘q. Test: Task 2 `test_department_user_without_department_sees_zeroes`.
- Bugun hech kim kirmagan (`entered = 0`) → foizlar `0%`, `NaN`/`Infinity` yo‘q, halqa bo‘sh. Tekshiruv: Task 3 Step 4.
- Guruhlar panelga sig‘maydigan darajada ko‘p → ortiqcha qatorlar yarim kesilmasdan yashiriladi. Tekshiruv: Task 3 Step 4.

---

### Task 0: Oldingi tekshirilgan o‘zgarishlarni alohida commitlash

Ishchi daraxtda shu ishga aloqasiz, tekshirilgan o‘zgarishlar bor; ular yangi ish bilan aralashmasligi uchun avval commitlanadi (push yo‘q).

- [ ] **Step 1:** `composer test` → kutilgan: 4 failed (EmployeeAdminTest, EmployeeExportTest, SanctumUserApiTest ×2 — oldindan), qolgani passed.
- [ ] **Step 2: Commitlar**

```bash
git add app/Services/Hikvision/HikvisionDeviceService.php tests/Feature/Admin/DeviceActionTest.php tests/Unit/Hikvision/HikvisionDeviceServiceTest.php
git commit -m "qurilmaga loopback (127.0.0.1) webhook manzili yozilmasin"
git add app/Services/LiveMonitorService.php tests/Feature/Admin/LiveMonitorTest.php resources/css/live-monitor.css resources/js/Pages/Admin/Live/Index.jsx
git commit -m "jonli monitor: guruhlar bo'yicha statistika (4 ustunli to'r)"
git add app/Http/Controllers/BiocontrolController.php routes/web.php tests/Feature/BiocontrolAccessTest.php phpunit.xml resources/js/Pages/Admin/Sync/Index.jsx
git commit -m "biocontrol: GET orqali qurilmaga yozish olib tashlandi, diagnostika huquq ostida; test loglari o'chirildi"
git add docs/superpowers/specs/2026-10-19-visit-statistics-design.md docs/superpowers/plans/2026-10-19-visit-statistics.md
git commit -m "docs: tashrif statistikasi spec va reja"
```

(Har bir commit oxiriga `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.)

---

### Task 1: Xodim davlati (`country`)

**Files:**
- Create: `database/migrations/2026_10_19_100000_add_country_to_employees_table.php`
- Create: `config/countries.php`
- Create: `resources/js/utils/countries.js`
- Modify: `app/Models/Employee.php` (`$fillable`, konstanta, metod)
- Modify: `database/factories/EmployeeFactory.php`
- Modify: `app/Http/Requests/StoreEmployeeRequest.php`, `app/Http/Requests/UpdateEmployeeRequest.php`
- Modify: `app/Http/Controllers/EmployeeController.php` (`create()` ~112, `edit()` ~176)
- Modify: `resources/js/Pages/Admin/Employees/Form.jsx` (Jins maydonidan keyin, ~131), `resources/js/Pages/Admin/Employees/Show.jsx` (~90)
- Test: `tests/Feature/Admin/EmployeeCountryTest.php`

**Interfaces:**
- Produces: ustun `employees.country` (`char(2)`, not null, default `'UZ'`, `gender` dan keyin); `Employee::LOCAL_COUNTRY = 'UZ'`; `Employee::isForeign(): bool`; `config('countries')` — `list<string>`; JS: `countryName(code: string): string`, `countryOptions(codes: string[]): {value: string, label: string}[]` (`UZ` birinchi, qolgani o‘zbekcha nom bo‘yicha alifbo).

- [ ] **Step 1: Failing testlar** — `EmployeeCountryTest` (`RefreshDatabase`, `InteractsWithPermissions`, `createUserWithPermissions([config('menu_permissions.resources')['employees']])`). Store payload — `EmployeeAdminTest::test_authorized_user_can_manage_employees` dagi bilan bir xil (`face` = `UploadedFile::fake()->image('face.jpg')`), card har testda boshqa.
  - `test_employee_saved_without_country_defaults_to_uzbekistan` — payloadda `country` yo‘q → `assertDatabaseHas('employees', ['card' => ..., 'country' => 'UZ'])`.
  - `test_employee_can_be_saved_with_foreign_country` — `'country' => 'KZ'` → bazada `KZ`.
  - `test_unknown_country_code_is_rejected` — `'country' => 'XX'` → `assertSessionHasErrors('country')`, xodim yaratilmaydi.
  - `test_update_without_country_keeps_existing_value` — `Employee::factory()->create(['country' => 'JP'])`, `put(route('admin.employees.update', ...))` `country` siz (face siz) → hali `JP`.
  - `test_is_foreign` — `(new Employee(['country' => 'UZ']))->isForeign()` false, `'KZ'` true.
- [ ] **Step 2:** `php artisan test tests/Feature/Admin/EmployeeCountryTest.php` → FAIL (ustun yo‘q).
- [ ] **Step 3: Implementatsiya**
  - Migration: `$table->char('country', 2)->default('UZ')->after('gender');` / `down`: `dropColumn('country')`.
  - `config/countries.php`: `return [...]` — rasmiy 249 ta ISO 3166-1 alpha-2 kod, katta harf, alifbo tartibida (`'UZ'` bor).
  - `Employee`: `'country'` ni `$fillable` ga; `public const LOCAL_COUNTRY = 'UZ';`; `isForeign()` → `$this->country !== self::LOCAL_COUNTRY`.
  - `EmployeeFactory`: `'country' => 'UZ'`.
  - Ikkala FormRequest: `'country' => ['sometimes', 'required', 'string', Rule::in(config('countries'))]`; `messages()`: `'country.required' => 'Davlatni tanlang.'`, `'country.in' => 'Davlat noto‘g‘ri.'`.
  - `EmployeeController@create/edit`: `'countries' => config('countries')`.
  - `countries.js`: `Intl.DisplayNames(['uz'], { type: 'region' })` bir marta yaratiladi; `countryName` xato yoki `undefined` bo‘lsa kodni qaytaradi.
  - `Form.jsx`: `useForm` ga `country: employee?.country ?? 'UZ'`; "Jins" dan keyin `<FormField label="Davlat" error={errors.country} required>` + `Select showSearch optionFilterProp="label" options={countryOptions(countries)}`; `countries = []` prop.
  - `Show.jsx`: `<DetailRow label="Davlat">{countryName(employee.country)}</DetailRow>` Jins qatoridan keyin (`employee.country` show propida bo‘lishini tekshiring; bo‘lmasa controllerda qo‘shing).
- [ ] **Step 4:** `php artisan test tests/Feature/Admin/EmployeeCountryTest.php` → 5 passed; `composer test` → faqat oldingi 4 ta failed.
- [ ] **Step 5:** `php artisan migrate` (lokal MySQL), brauzerda xodim tahrirlash formasida "Davlat" tanlovi o‘zbekcha nomlar bilan ochilishini va saqlanishini tekshiring.
- [ ] **Step 6: Commit** — `git add` yuqoridagi fayllar; `git commit -m "xodimga davlat (country) maydoni"`.

---

### Task 2: `VisitStatsService` va `/admin/live/stats` marshruti

**Files:**
- Create: `app/Services/VisitStatsService.php`
- Create: `resources/js/Pages/Admin/Live/Stats.jsx` (vaqtincha `export default function Stats() { return null; }` — Inertia test sahifa fayli mavjudligini tekshiradi; Task 3 to‘ldiradi)
- Modify: `app/Services/LiveMonitorService.php` (`enteredByGroup` → `public`)
- Modify: `app/Http/Controllers/LiveMonitorController.php`
- Modify: `routes/web.php` (`live` marshrutidan keyin)
- Test: `tests/Feature/Admin/VisitStatsTest.php`

**Interfaces:**
- Consumes: `LiveMonitorService::enteredByGroup(array $range): list<array{id:int,name:string,entered:int}>`; `Employee::LOCAL_COUNTRY` (Task 1).
- Produces: route `admin.live.stats`; Inertia `Admin/Live/Stats` props `stats`:
  `{registered:int, registered_today:int, entered:int, entered_last_hour:int, exited:int, exited_last_hour:int, inside:int, inside_male:int, inside_female:int, local:int, foreign:int, male:int, female:int, groups:list<{id,name,entered}>, last_arrival:null|{employee_name:string, country:string|null, position:string|null, department_name:string|null, device_name:string|null, time:string}}` (`time` = `H:i:s`).

- [ ] **Step 1: Failing testlar** (`RefreshDatabase`, `InteractsWithPermissions`, har testda `Carbon::setTestNow('2026-10-19 12:00:00')`, `tearDown` da `Carbon::setTestNow()`):
  - `test_user_without_live_permission_is_forbidden` — faqat `home` → `get(route('admin.live.stats'))` 403.
  - `test_stats_cover_registration_entries_exits_inside_and_origin` — xodimlar (`created_at` kecha, faqat D bugun): A (UZ, erkak, guruh "Ishtirokchi") kirish 09:00, chiqish 10:00, kirish 11:30; B (KZ, ayol) kirish 11:10, chiqish 11:40; C (UZ, erkak) kirish 08:00; D o‘tishsiz; F kecha 15:00 kirish. Kutilgan: `registered` 5, `registered_today` 1, `entered` 3, `entered_last_hour` 2, `exited` 2, `exited_last_hour` 1, `inside` 2, `inside_male` 2, `inside_female` 0, `local` 2, `foreign` 1, `male` 2, `female` 1, `groups` = `[['id' => ..., 'name' => 'Ishtirokchi', 'entered' => 1]]`, `last_arrival.employee_name` = A nomi, `last_arrival.country` `UZ`, `last_arrival.time` `11:30:00`, `last_arrival.device_name` = qurilma nomi.
  - `test_inside_uses_insertion_order_when_two_passes_share_a_timestamp` — bir xodim, 11:00 da avval kirish, keyin chiqish (shu vaqt, katta `id`) → `inside` 0; teskari tartibda → 1.
  - `test_exit_only_person_counts_as_exited_but_not_entered` — faqat chiqish → `exited` 1, `entered` 0, `inside` 0, `last_arrival` null.
  - `test_passes_without_employee_are_ignored` — `Report::factory()->entry()->create(['employee_id' => null, 'occurred_at' => '2026-10-19 11:59:00'])` + bitta haqiqiy kirish 11:00 → `entered` 1, `last_arrival.time` `11:00:00`.
  - `test_department_user_sees_only_own_department` — `createDepartmentUserWithPermissions($own->id, [live])`; boshqa bo‘lim xodimi va kirishi → `registered`, `entered`, `inside`, `last_arrival` faqat o‘z bo‘limi bo‘yicha.
  - `test_department_user_without_department_sees_zeroes` — `department_id = null` bo‘limli foydalanuvchi → barcha sonlar 0, `groups` `[]`, `last_arrival` null, 200.
- [ ] **Step 2:** `php artisan test tests/Feature/Admin/VisitStatsTest.php` → FAIL (marshrut yo‘q).
- [ ] **Step 3: Implementatsiya**
  - `LiveMonitorService::enteredByGroup` → `public`, docblock o‘zgarmaydi.
  - `VisitStatsService` (`__construct(private readonly LiveMonitorService $liveMonitorService)`), `public function today(): array` — yuqoridagi kalitlar. Odam sonlari `Report::query()->where('type', ...)->whereBetween('occurred_at', $range)->whereNotNull('employee_id')->distinct()->count('employee_id')` uslubida (`LiveMonitorService::todayStats` dagi kabi); `registered*` — `Employee::query()`; `local/foreign/male/female` — `Employee::query()->whereHas('reports', entry bugun)` + `country`/`gender` sharti.
  - `inside` algoritmi (har bir xodimning bugungi oxirgi o‘tishi, teng vaqtda katta `id`), ikki bosqich —
    tuple `whereIn` SQLite/MySQL da bir xil ishlamagani uchun ishlatilmaydi:
    (1) `Report::query()->whereBetween(...)->whereNotNull('employee_id')->groupBy('employee_id')->selectRaw('employee_id, max(occurred_at) as last_at')` ni subquery qilib, `reports` ga `employee_id` va `occurred_at = last_at` bo‘yicha join;
    (2) shu qatorlar ichida `groupBy('reports.employee_id')->selectRaw('max(reports.id) as id')` → `Report::query()->whereIn('id', <bu idlar>)->where('type', Report::TYPE_ENTRY)->pluck('employee_id')`.
    `inside_male/female` — `Employee::query()->whereIn('id', $insideIds)` jins bo‘yicha. Join ichidagi ustunlarni `reports.` bilan aniq yozing (scope `reports.department_id` qo‘shadi).
  - `last_arrival`: `Report::query()->entry()->whereBetween(...)->whereNotNull('employee_id')->with(['employee:id,name,country,position,department_id', 'employee.department:id,name', 'device:id,name'])->latest('occurred_at')->latest('id')->first()`; ism `employee?->name ?? employee_name`.
  - `LiveMonitorController`: konstruktorga `private readonly VisitStatsService $visitStatsService`; `public function stats(): Response` → `Inertia::render('Admin/Live/Stats', ['stats' => fn () => $this->visitStatsService->today()])`.
  - `routes/web.php`: `Route::get('live/stats', [LiveMonitorController::class, 'stats'])->middleware('permission:'.$menuPermissions['live'])->name('live.stats');` (`live/faces/{employee}` dan oldin).
- [ ] **Step 4:** `php artisan test tests/Feature/Admin/VisitStatsTest.php tests/Feature/Admin/LiveMonitorTest.php` → hammasi passed; `vendor/bin/pint` o‘zgargan PHP fayllarga.
- [ ] **Step 5: Commit** — `git commit -m "tashrif statistikasi: VisitStatsService va /admin/live/stats"`.

---

### Task 3: "Tashrif statistikasi" sahifasi (frontend)

**Files:**
- Create: `resources/css/visit-stats.css`
- Create: `resources/js/Components/Live/LiveClock.jsx` (Live/Index.jsx dagi `useNow` + `Clock` + `WEEKDAYS`/`MONTHS` shu yerga ko‘chadi)
- Modify: `resources/js/Pages/Admin/Live/Stats.jsx` (to‘liq sahifa)
- Modify: `resources/js/Pages/Admin/Live/Index.jsx` (`LiveClock` importi; `lm-tools` ga statistika havolasi)
- Modify: `resources/js/routes.js` (`'admin.live.stats': () => '/admin/live/stats'`)
- Modify: `resources/js/config/adminNav.js` (`live` dan keyin `{ key: 'live-stats', permission: 'live', href: route('admin.live.stats'), label: 'Tashrif statistikasi', icon: BarChartOutlined }`)

**Interfaces:**
- Consumes: `stats` prop (Task 2); `countryName` (Task 1); `useInertiaPoll`.
- Produces: `LiveClock` (`export default function LiveClock()` — `lm-clock` markup, jonli monitordagi bilan bir xil).

- [ ] **Step 1:** `LiveClock` ga ko‘chirish va `Index.jsx` da ishlatish; jonli monitor o‘zgarmay ochilishini brauzerda tekshirish. `lm-tools` ga `Link href={route('admin.live.stats')} className="lm-tool" aria-label="Tashrif statistikasi"` + `BarChartOutlined`.
- [ ] **Step 2: `Stats.jsx`** — ildiz `<div className="live-monitor visit-stats">` (`live-monitor.css` va `visit-stats.css` import); `useInertiaPoll(['stats'], 5000)`; `<Head title="Tashrif statistikasi" />`. Bloklar (Figma `hh`, 1:1431):
  - Sarlavha: `lm-header`/`lm-brand` (brend belgisi = `appName` bosh harflari, sarlavha `"{appName} · Tashrif statistikasi"`, izoh "Real vaqt rejimida yangilanadi · Live attendance statistics"), asboblar (jonli monitorga qaytish `ArrowLeftOutlined`, to‘liq ekran), `lm-live`, `LiveClock`.
  - KPI 4 ustun (`vs-kpis`): Ro‘yxatdan o‘tganlar (oq, `TeamOutlined`, "Registered", izoh "Bugun +{registered_today} ta yangi"), Bugun kirganlar (yashil, `LoginOutlined`, "Entered today", "So‘nggi 1 soatda +{entered_last_hour}"), Hozir ichkarida (ko‘k, `UserOutlined`, "Inside now", izoh "Erkak {inside_male} · Ayol {inside_female}"), Bugun chiqqanlar (qizil, `LogoutOutlined`, "Exited today", "So‘nggi 1 soatda +{exited_last_hour}").
  - Panellar (`vs-panels`, ustunlar `470fr 786fr 520fr` — Figma nisbati): "Mahalliy va xorijiy" (`conic-gradient` halqa: ko‘k `local`, oltin `foreign`; markazda `entered` va "bugun kirgan"; 2 qator "Mahalliy · Local" / "Xorijiy · International" son + foiz), "Erkaklar va ayollar" (2 karta son + foiz pill, ulush chizig‘i ko‘k/pushti, ostida "Hozir ichkarida · Inside now — Erkak {inside_male} · Ayol {inside_female}"), "Toifalar bo‘yicha" ("Guests by category · bugun kirganlar", ulush chizig‘i + har guruh qatori: nom, "Bugun kirgan", son, foiz; rang navbati `['blue','yellow','pink','green']` jonli monitordagi kabi).
  - Lenta "SO‘NGGI KIRGAN · LAST ARRIVAL": ism, `[countryName(country), position || department_name].filter(Boolean).join(' · ')`, `"{device_name} · {time}"`; `null` bo‘lsa "Bugun hali kirish qayd etilmagan."
  - Foiz yordamchisi: `percent(part, total)` → `total > 0 ? (part / total * 100).toFixed(1) : '0.0'`; sonlar `formatNumber` (Live/Index dagi kabi bo‘shliq bilan).
- [ ] **Step 3: `visit-stats.css`** — faqat `.visit-stats` ichida; `live-monitor.css` tokenlari (`--lm-*`), qo‘shimcha `--vs-red: #ff6b6b`, `--vs-gold: #e6c35c`; 1em=16px @1920×1080 da Figma o‘lchamlari (KPI raqami 6.25em, panel radius 1.75em, va h.k.); guruh ro‘yxati `flex-wrap` usuli bilan (sig‘magan qator yashirinadi); `@media (max-width: 1023px)` da bitta ustun.
- [ ] **Step 4: Brauzer tekshiruvi (1920×1080)** — `npm run dev` + `php artisan serve`; `/admin/live/stats`:
  - nol holat (lokal bazada bugun o‘tish yo‘q): foizlar `0.0%`, halqa bo‘sh, lenta bo‘sh-holat matni, konsolda xato yo‘q;
  - DevTools orqali DOM ga 6 ta guruh qatori qo‘shib, ortiqchalari yarim kesilmasdan yashirinishini ko‘ring;
  - Figma `hh` skrinshoti bilan joylashuv, ranglar va o‘lchamlarni solishtiring; sahifa 1080 px dan oshmaydi, scroll yo‘q;
  - yon menyuda "Tashrif statistikasi" va jonli monitordagi havola ishlaydi.
- [ ] **Step 5:** `composer test` → faqat oldingi 4 ta failed; `npm run build` xatosiz.
- [ ] **Step 6: Commit** — `git commit -m "tashrif statistikasi sahifasi (zal ekrani)"`.
