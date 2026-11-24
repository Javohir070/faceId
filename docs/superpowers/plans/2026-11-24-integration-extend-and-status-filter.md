# Integratsiya API: muddatni uzaytirish va holat filtri — reja

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Integratsiya API ga `GET /employees?status=` filtri va `POST /employees/{jshshir}/extend` (muddatni uzaytirib terminallarga yozish) qo‘shish.

**Architecture:** Filtr mavjud `IntegrationEmployeeService::list()` ga `Employee::scopeValidity()` orqali qo‘shiladi. Uzaytirish — yangi `ExtendIntegrationEmployeeRequest` (`FindsIntegrationEmployee` bilan 404) → controller `extend()` → service `extend()`, u mavjud `update()` ga faqat muddat maydonlarini beradi (baza + `transferOne()`).

**Tech Stack:** Laravel 12, PHPUnit (SQLite), Scramble (`/docs/api`).

**Spec:** `docs/superpowers/specs/2026-11-24-integration-extend-and-status-filter-design.md`

## Global Constraints

- Hamma foydalanuvchiga ko‘rinadigan matn (xabar, hujjat) o‘zbekcha.
- Testlarda terminal doim soxta (`terminalNatijasi()` / `terminalYozilmasin()` / `Http::preventStrayRequests()`): haqiqiy terminalga hech narsa yozilmaydi.
- Testlar faqat SQLite: `composer test` (`COMPOSER_PROCESS_TIMEOUT=0`), format `vendor/bin/pint`.
- Bo‘lim izolyatsiyasi: boshqa bo‘lim xodimi 404 `NOT_FOUND`, ro‘yxatda ko‘rinmaydi.
- Commit va push faqat foydalanuvchi so‘rasa — rejadagi “commit” qadamlari o‘rniga `git status` bilan tekshirish.

## Review Focus

- Zona bilan kelgan `valid_to` (`…Z`) — kelajakdami, to‘g‘ri solishtirilishi va tizim vaqtiga o‘girib saqlanishi kerak.
- Faqat `valid_to` yuborilib, u bazadagi (kelajakdagi, `upcoming`) `valid_from` dan oldin bo‘lsa — 422, baza o‘zgarmaydi.
- `extend` ga ism/rasm ham yuborilsa — e’tiborga olinmaydi, ism va rasm o‘zgarmaydi.
- `status` + `search` + `limit/page` birga — `total` faqat filtrlangan to‘plamni sanaydi.
- Yuz rasmi yo‘q xodimni uzaytirish — baza yangilanadi, terminal natijasi `skipped` “Yuz rasmi yo‘q”, javob 200.

---

### Task 1: Ro‘yxatda `status` filtri

**Files:**
- Modify: `app/Http/Controllers/Api/IntegrationEmployeeController.php` (`index()`)
- Modify: `app/Services/IntegrationEmployeeService.php` (`list()`)
- Modify: `docs/integration-api.md` (ro‘yxat bo‘limi)
- Test: `tests/Feature/Api/IntegrationEmployeeApiTest.php`, `tests/Feature/Api/ApiAuthDocumentationTest.php`

**Interfaces:**
- Produces: `IntegrationEmployeeService::list(?string $search = null, int $limit = self::LIMIT_DEFAULT, int $page = 1, ?string $status = null): array`

- [ ] **Step 1: Failing tests** (`travelTo('2027-01-10 12:00:00')`, xodimlar `valid_to` bilan: tugagan `2027-01-01`, tugashiga yaqin `2027-01-12`, faol `2027-12-01`, kelgusi `valid_from 2027-03-01`; boshqa bo‘limda yana bitta tugagan)
  - `test_royxat_holat_boyicha_filtrlanadi`: `?status=expired` → faqat o‘z bo‘limidagi tugagan, `total` 1; `?status=expiring` → yaqin; `?status=upcoming` → kelgusi; `?status=active` → yaqin + faol (2 ta).
  - `test_holat_qidiruv_va_sahifalash_bilan`: 3 ta tugagan (biri `search` ga mos emas) → `?status=expired&search=…&limit=1` → `total` 2, `has_more` true.
  - `test_notogri_holat_422`: `?status=deleted` → 422 `status`.
  - `ApiAuthDocumentationTest::test_royxatda_holat_parametri`: `/integration/employees` `get` `parameters` da `status`, `schema.enum` = `['active','expiring','expired','upcoming']`.
- [ ] **Step 2: Run** `php artisan test --filter="holat|Holat"` → FAIL (filtr yo‘q, hammasi qaytadi).
- [ ] **Step 3: Implement** `index()` qoidasi `'status' => ['nullable', 'string', Rule::in(Employee::VALIDITIES)]`, `list()` da `->when($status !== null, fn ($q) => $q->validity($status))`.
- [ ] **Step 4: Run** filtrlangan testlar → PASS; hujjatga `status` qatori va holatlar izohi; “Muddati tugagan xodimlar ham ro‘yxatda” jumlasi `?status=expired` ga yo‘naltiriladi.

### Task 2: `POST /employees/{jshshir}/extend`

**Files:**
- Create: `app/Http/Requests/ExtendIntegrationEmployeeRequest.php`
- Modify: `routes/api.php`, `IntegrationEmployeeController.php` (`extend()`), `IntegrationEmployeeService.php` (`extend()`)
- Modify: `docs/integration-api.md`, `CLAUDE.md` (integratsiya API qatori)
- Test: `IntegrationEmployeeApiTest.php`, `ApiAuthDocumentationTest.php`

**Interfaces:**
- Consumes: `FindsIntegrationEmployee` (`employee(): ?Employee`, 404), `IntegrationEmployeeService::update(Employee, array): array{employee, terminals}`, `employeePayload()`.
- Produces: route `api.integration.employees.extend` (`POST /api/integration/employees/{jshshir}/extend`, `where('jshshir', '[0-9]+')`); `IntegrationEmployeeService::extend(Employee $employee, array $data): array{employee: Employee, terminals: list<array>}` — `$data` dan faqat `valid_from`, `valid_to` olinadi.

- [ ] **Step 1: Failing tests** (`travelTo('2027-12-10 12:00:00')`, `mavjudXodim()` — `valid_to 2027-12-01 23:59:59`, ya’ni tugagan; helper `uzaytir(User, string $jshshir, array $payload)`)
  - `test_muddat_uzaytiriladi_va_terminallarga_yoziladi`: `{valid_to: '2028-12-31 23:59:59'}` → 200, `employee.valid_to` `2028-12-31 23:59:59`, `employee.status` `active`, `terminals.0` = `{id, name, status: success, message: OK}`, faqat foydalanuvchi terminaliga yozilgan, `valid_from` o‘zgarmagan, `expiry_notified_at` `null` bo‘lgan.
  - `test_uzaytirishda_valid_from_ham_ozgaradi`: `{valid_from: '2027-12-11', valid_to: '2028-12-31'}` → `valid_from` `2027-12-11 00:00:00`, `status` `upcoming`.
  - `test_otgan_sanaga_uzaytirib_bolmaydi_422`: `valid_to` `2027-12-09 00:00:00` → 422 `valid_to`, xabar “hozirgi vaqtdan keyin”; `valid_to` yo‘q → 422; baza o‘zgarmaydi, terminal chaqirilmaydi.
  - `test_valid_to_bazadagi_valid_fromdan_oldin_422`: xodim `valid_from 2028-06-01`, `valid_to 2028-03-01` → 422 `valid_to`.
  - `test_uzaytirishda_boshqa_maydonlar_ozgarmaydi`: `{valid_to, last_name: 'Buzildi', face: FACE}` → ism `Aminov …`, `Face` yozuvi yo‘q.
  - `test_zona_bilan_kelgan_muddat`: `valid_to` `2028-01-01T19:00:00Z` → `2028-01-02 00:00:00` (Toshkent).
  - `test_rasmsiz_xodim_uzaytiriladi_terminal_skipped`: haqiqiy `transferOne`, `Http::preventStrayRequests()`, terminal `is_online` + login/parol bor → 200, `terminals.0.status` `skipped`, `message` `Yuz rasmi yo‘q`, bazada yangi muddat.
  - `test_boshqa_bolim_xodimini_uzaytirib_bolmaydi_404` (noto‘g‘ri body bilan ham 404), `test_uzaytirishni_bolimsiz_super_admin_va_ruxsatsiz_qila_olmaydi` (403 kodlari), `test_uzaytirish_jadval_sozlanmasa_ham_ishlaydi`, `test_uzaytirish_audit_logga_yoziladi` (`Integratsiya API: xodim muddati uzaytirildi`, `user_id`, `employee_id`, JSHSHIRsiz).
  - `ApiAuthDocumentationTest`: `/integration/employees/{jshshir}/extend` `post` `security` = Bearer + Basic.
- [ ] **Step 2: Run** `php artisan test --filter="uzaytir|Uzaytir|extend"` → FAIL (404 — yo‘l yo‘q).
- [ ] **Step 3: Implement** request: `valid_to` `['required', 'date', 'after:now']`, `valid_from` `['sometimes', 'required', 'date']`, `messages()` da `valid_to.after` → `Amal qilish tugashi hozirgi vaqtdan keyin bo‘lishi kerak.`; `after()` — `UpdateIntegrationEmployeeRequest::after()` dagi solishtirish (yuborilmagan chegara bazadan). Controller `extend()` javobi `['ok' => true, 'employee' => [...employeePayload, 'status' => validityStatus()], 'terminals' => …]`, log `info`.
- [ ] **Step 4: Run** filtrlangan testlar → PASS; `docs/integration-api.md` ga `extend` bo‘limi (jadval, javob, “muddati tugaganlarni topib uzaytirish” misoli, rasmsiz xodim `skipped`, xatolar jadvali), `CLAUDE.md` integratsiya qatoriga `extend` va `?status=`.

### Task 3: Yakuniy tekshiruv

- [ ] `COMPOSER_PROCESS_TIMEOUT=0 composer test` → hammasi yashil.
- [ ] `vendor/bin/pint` (o‘zgargan fayllar), keyin `vendor/bin/pint --test` → toza.
- [ ] `git status` — faqat shu reja fayllari o‘zgargan; commit qilinmaydi.
