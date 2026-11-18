# Parking API Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** `GET /api/parking/employees` — parking tizimi uchun xodimlar ro‘yxati (id, fish, jshshir), qidiruv va sahifalash bilan, panel foydalanuvchisining email+paroli (HTTP Basic) va `Parking API` ruxsati bilan himoyalangan.

**Architecture:** `routes/api.php` → `parking.api` middleware (`AuthenticateParkingApi`, sessiyasiz `Auth::guard('web')->once`, ruxsat va xato urinish cheklovi) → `Api\ParkingController` → `ParkingApiService` (`withoutGlobalScopes`). Ruxsat `config/menu_permissions.php` ning yangi `api` bo‘limida, seeder va migratsiya bilan.

**Tech Stack:** Laravel 12, Spatie laravel-permission, PHPUnit (SQLite in-memory).

**Spec:** `docs/superpowers/specs/2026-11-18-parking-api-design.md`

## Global Constraints

- Manzil aynan `GET /api/parking/employees`, route nomi `api.parking.employees`.
- Javob kalitlari: `ok, count, total, page, limit, has_more, employees[]`; har xodim aynan `id, fish, jshshir`.
- `limit` sukut 200, 1..1000; `page` sukut 1, ≥1; noto‘g‘ri qiymat — 422.
- Ruxsat nomi aynan `'Parking API'`, `config('menu_permissions.api.parking')`.
- 401 `UNAUTHORIZED` + `WWW-Authenticate: Basic`; 403 `FORBIDDEN`; 429 `TOO_MANY_ATTEMPTS`; xato javob shakli `{"ok": false, "error": {"code", "message"}}`.
- Xato urinish cheklovi: bir IP + email uchun 60 soniyada 5 ta; IP `App\Http\ClientIp::from()` dan.
- Logga parol ham, `search` qiymati ham (JSHSHIR bo‘lishi mumkin) yozilmaydi.
- UI matnlari va xabarlar o‘zbekcha; `tests/TestCase.php` o‘zgarmaydi; `/api/v1` o‘zgarmaydi.

## Review Focus

1. Bo‘limi `null` bo‘lgan (super-admin bo‘lmagan) parking foydalanuvchisi hamma xodimni ko‘rishi kerak — global scope ishlab ketmasin.
2. Ruxsati yo‘q, lekin paroli to‘g‘ri foydalanuvchi 403 olishi, ro‘yxat bermasligi kerak.
3. 5 ta xato urinishdan keyin to‘g‘ri parol bilan ham 429 — parol tekshirilmaydi.
4. Javobda uchta maydondan boshqa hech narsa (bo‘lim, yuz, muddat) chiqmasligi kerak.
5. So‘rov sessiya cookie’sini qaytarmasligi kerak.

---

### Task 1: `Parking API` ruxsati

**Files:**
- Modify: `config/menu_permissions.php` — yangi `'api' => ['parking' => 'Parking API']` (izoh bilan: menyu emas, API kirish ruxsati)
- Modify: `database/seeders/DatabaseSeeder.php:25-30` — `...array_values(config('menu_permissions.api'))`
- Create: `database/migrations/2026_11_18_100000_add_parking_api_permission.php` — `2026_11_12_100000_add_units_permission.php` naqshi, `ROLES = ['super-admin']`
- Test: `tests/Feature/Api/ParkingApiTest.php`

**Interfaces:**
- Produces: `Permission` qatori `'Parking API'` (guard `web`); `config('menu_permissions.api.parking')`.

- [ ] **Step 1: Failing testlar**
  - `test_parking_api_ruxsati_migratsiyada_yaratiladi`: `Permission::where('name', 'Parking API')->where('guard_name', 'web')->exists()` true.
  - `test_seeder_parking_ruxsatini_super_adminga_beradi`: `$this->seed()` dan keyin `Role::findByName('super-admin')->hasPermissionTo('Parking API')` true, `user` roli esa yo‘q.
- [ ] **Step 2:** `php artisan test tests/Feature/Api/ParkingApiTest.php` — Expected: ikkalasi FAIL.
- [ ] **Step 3:** config, seeder, migratsiya.
- [ ] **Step 4:** Shu buyruq — Expected: PASS.
- [ ] **Step 5: Commit** `Parking API: ruxsat`

### Task 2: Middleware, route, controller, servis

**Files:**
- Create: `app/Http/Middleware/AuthenticateParkingApi.php`
- Modify: `bootstrap/app.php` — alias `'parking.api'`
- Modify: `routes/api.php` — `Route::middleware('parking.api')->prefix('parking')->name('api.parking.')->group(...)`
- Create: `app/Http/Controllers/Api/ParkingController.php` — `employees(Request): JsonResponse`, filtr validatsiyasi inline (`search` nullable string max:100, `limit`, `page`)
- Create: `app/Services/ParkingApiService.php` — `LIMIT_DEFAULT = 200`, `LIMIT_MAX = 1000`, `employees(?string $search, int $limit, int $page): array{total:int, page:int, limit:int, has_more:bool, items:list<array{id:int, fish:?string, jshshir:?string}>}`
- Test: `tests/Feature/Api/ParkingApiTest.php`

**Interfaces:**
- Consumes: `'Parking API'` ruxsati (Task 1).
- Middleware: Basic yo‘q/xato → 401; `RateLimiter` kaliti `'parking-api|'.strtolower(email).'|'.ip`, 5 xato/60 s → 429 (tekshiruvdan oldin); muvaffaqiyatda kalit tozalanadi; `$user->can('Parking API')` yo‘q → 403.
- Servis: `Employee::query()->withoutGlobalScopes()`, `search` bo‘lsa `name LIKE %q% OR card LIKE %q%`, `orderBy('id')`, faqat `id, name, card` select.

- [ ] **Step 1: Failing testlar** (helper: `parkingUser(?int $departmentId = null, bool $permission = true)` — `Permission::findOrCreate('Parking API')`, `User::factory()->create(['department_id' => …, 'password' => 'parking-parol'])`, `assignRole('user')`; `basic($user, $parol)` sarlavhasi)
  - `test_parolsiz_sorov_401` — `assertUnauthorized()`, `assertHeader('WWW-Authenticate')`, `error.code = UNAUTHORIZED`
  - `test_notogri_parol_401_va_logga_yoziladi` — `Log::spy()`, warning chaqirilgan, kontekstda `password` yo‘q
  - `test_ruxsatsiz_foydalanuvchi_403` — `error.code = FORBIDDEN`
  - `test_royxat_faqat_id_fish_jshshir_qaytaradi` — `employees.0` kalitlari aynan `['id','fish','jshshir']`, qiymatlar mos
  - `test_bolimi_yoq_foydalanuvchi_hamma_xodimni_koradi` — ikki bo‘limdagi 2 xodim, `department_id` null foydalanuvchi → `total = 2`
  - `test_fish_boyicha_qidiradi`, `test_jshshir_boyicha_qidiradi`
  - `test_sahifalaydi` — 3 xodim, `limit=2&page=2` → `count 1, total 3, has_more false`; `page=1` → `has_more true`
  - `test_notogri_limit_422` — `limit=1001` va `page=0` → 422
  - `test_besh_xato_urinishdan_keyin_429` — 5 ta xato paroldan keyin to‘g‘ri parol ham 429
  - `test_sessiya_cookie_qaytmaydi` — `assertCookieMissing(config('session.cookie'))`
  - `test_super_admin_ham_kira_oladi` — super-admin roli `'Parking API'` bilan → 200
- [ ] **Step 2:** `php artisan test tests/Feature/Api/ParkingApiTest.php` — Expected: yangi testlar FAIL (404).
- [ ] **Step 3:** Middleware, alias, route, controller, servis.
- [ ] **Step 4:** Shu buyruq — Expected: hammasi PASS.
- [ ] **Step 5: Commit** `Parking API: xodimlar ro‘yxati`

### Task 3: Hujjat va yakuniy tekshiruv

**Files:**
- Create: `docs/parking-api.md` — manzil, parametrlar, javob, xatolar, sozlash (ruxsat → rol → foydalanuvchi), curl misoli, HTTPS talabi, bo‘lim cheklovi qo‘llanmasligi, muddati tugaganlar ham chiqishi
- Modify: `CLAUDE.md` — directory API paragrafidan keyin parking API haqida qisqa paragraf

- [ ] **Step 1:** Hujjatlar.
- [ ] **Step 2:** `COMPOSER_PROCESS_TIMEOUT=0 composer test` — Expected: hammasi PASS (Linux-only skip’lar bundan mustasno); `vendor/bin/pint --test` toza.
- [ ] **Step 3:** `php artisan route:list --path=api/parking` — Expected: `GET|HEAD api/parking/employees … api.parking.employees`.
- [ ] **Step 4: Commit** `Parking API: hujjat`
