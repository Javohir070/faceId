# Integratsiya API (xodim qo‘shish/tahrirlash/o‘chirish) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** `/api/integration/employees` — tashqi tizim panel foydalanuvchisi nomidan o‘z bo‘limiga xodim qo‘shadi, tahrirlaydi, o‘chiradi; xodim foydalanuvchining terminallariga darhol yoziladi.

**Architecture:** Parking middleware umumiy `api.user:<kalit>` ga aylanadi (`AuthenticateApiUser`). `routes/api.php` → `Api\IntegrationEmployeeController` → FormRequest’lar → `IntegrationEmployeeService` → `EmployeeService` (baza) + `EmployeeSyncService::transferOne()` / `removeFromDevices()` (terminal). Bo‘lim va terminal chegarasi mavjud global scope’lar orqali (foydalanuvchi `once` bilan kirgan).

**Tech Stack:** Laravel 12, Spatie permission, PHPUnit (SQLite), Mockery partialMock (terminal chaqiriqlari).

**Spec:** `docs/superpowers/specs/2026-11-23-integration-employees-api-design.md`

## Global Constraints

- Manzillar: `POST /api/integration/employees` (201), `PUT|PATCH /api/integration/employees/{jshshir}` (200), `DELETE /api/integration/employees/{jshshir}` (200); route nomlari `api.integration.employees.store|update|destroy`; `{jshshir}` — `[0-9]+`.
- Ruxsat aynan `'Integratsiya API'`, `config('menu_permissions.api.integration')`; migratsiya faqat super-admin ga.
- Middleware alias `api.user`, parametr — `menu_permissions.api` kaliti (`parking`, `integration`). Xatti-harakat parking’dagidek o‘zgarmaydi.
- Jadval: `config('integration_api.schedule_id')` ← `INTEGRATION_API_SCHEDULE_ID`; yo‘q yoki foydalanuvchi bo‘limiga mos emas → 503 `NOT_CONFIGURED`, hech narsa yaratilmaydi.
- Bo‘limsiz foydalanuvchi → 403 `NO_DEPARTMENT` (uchala amal).
- Xodim javobi: `id, jshshir, fish, last_name, first_name, middle_name, gender, position, department_id, group_id, valid_from, valid_to` (`Y-m-d H:i:s`); terminal: `id, name, status (success|failed|skipped), message`.
- Haqiqiy terminalga yozilmaydi: testlarda `EmployeeSyncService::transferOne` yoki `HikvisionUserService` mock qilinadi.
- JSHSHIR, rasm, parol logga yozilmaydi. UI/xabarlar o‘zbekcha. `/api/v1`, `/api/legacy` o‘zgarmaydi.

## Review Focus

1. Bo‘limsiz (super-admin) foydalanuvchi — 403, xodim yaratilmaydi.
2. Boshqa bo‘lim xodimini PUT/DELETE — 404, xodim va terminal o‘zgarmaydi.
3. Yaratish/tahrirda boshqa foydalanuvchining terminaliga yozilmaydi.
4. O‘chirishda xodim API foydalanuvchisiga ko‘rinmaydigan terminaldan ham o‘chiriladi; terminal xatosida xodim bazada qoladi.
5. Buzuq base64, rasm bo‘lmagan fayl, 1 MB dan katta rasm — 422, hech narsa yaratilmaydi.

---

### Task 1: Umumiy kirish middleware’i va `Integratsiya API` ruxsati

**Files:**
- Rename: `app/Http/Middleware/AuthenticateParkingApi.php` → `app/Http/Middleware/AuthenticateApiUser.php` — `handle(Request, Closure, string $ruxsatKaliti)`; 403 xabari ruxsat nomi bilan; log matnlari “Tashqi API”
- Modify: `bootstrap/app.php` (alias `api.user`, `parking.api` o‘chadi), `routes/api.php` (parking → `api.user:parking`)
- Modify: `config/menu_permissions.php` (`'integration' => 'Integratsiya API'`)
- Create: `database/migrations/2026_11_23_100000_add_integration_api_permission.php` (parking migratsiyasi naqshi)
- Modify: `resources/js/Pages/Admin/Roles/Form.jsx` — ogohlantirish matni ikkala API ga umumiy
- Test: `tests/Feature/Api/IntegrationEmployeeApiTest.php`, mavjud `tests/Feature/Api/ParkingApiTest.php` yashil qolishi shart

**Interfaces:**
- Produces: `api.user:integration` middleware; `'Integratsiya API'` ruxsat qatori.

- [ ] **Step 1: Failing testlar** — `test_integratsiya_ruxsati_migratsiyada_yaratiladi`; `test_integratsiya_ruxsati_tashqi_api_guruhida` (`RoleService::API_GROUP`, `api => true`); `test_parking_ruxsati_integratsiyaga_kirita_olmaydi` (faqat `Parking API` li foydalanuvchi `POST` → 403 `FORBIDDEN`) — route yo‘qligi uchun keyingi task’gacha 404/405 bilan yiqiladi, Task 2 da yashil bo‘ladi.
- [ ] **Step 2:** `php artisan test tests/Feature/Api/IntegrationEmployeeApiTest.php` — Expected: FAIL.
- [ ] **Step 3:** middleware nomini o‘zgartirish va parametrlash, config, migratsiya, JSX matni.
- [ ] **Step 4:** `php artisan test tests/Feature/Api` — Expected: ruxsat testlari va butun `ParkingApiTest` PASS.
- [ ] **Step 5: Commit** `Integratsiya API: umumiy kirish va ruxsat`

### Task 2: `POST` — xodim qo‘shish

**Files:**
- Create: `config/integration_api.php`; Modify: `.env.example`
- Create: `app/Rules/Base64Image.php` — `__construct(int $maxKilobytes = 1024)`; `data:image/...;base64,` prefiksini olib tashlaydi, `base64_decode(strict)`, hajm, `finfo` MIME ∈ {image/jpeg, image/png}
- Create: `app/Http/Requests/Api/StoreIntegrationEmployeeRequest.php` — spec jadvali; `jshshir` `digits:14` + `Rule::unique('employees','card')`; `group_id` foydalanuvchi bo‘limida; `face` `required|string|Base64Image`; xabarlar `StoreEmployeeRequest` uslubida
- Create: `app/Services/IntegrationEmployeeService.php`:
  - `scheduleId(User $user): ?int`
  - `create(User $user, array $data): array{employee: Employee, terminals: list<array{id:int,name:string,status:string,message:string}>}` — maydonlarni panel nomlariga o‘giradi (`card`, `valid_from_dt`, …), base64 → vaqtinchalik `UploadedFile`, `EmployeeService::create()`, so‘ng `pushToTerminals()`
  - `pushToTerminals(Employee $employee): list<...>` — `Device::query()` (foydalanuvchiga scope’langan) har biri uchun `transferOne()`; `'device disconnected'` → `'Terminal oflayn'`; `set_time_limit(0)`
  - `employeePayload(Employee): array`
- Create: `app/Http/Controllers/Api/IntegrationEmployeeController.php` — `store()`; bo‘lim tekshiruvi (403), jadval (503), audit `Log::info`
- Modify: `routes/api.php`; `lang/uz/validation.php` attributes: `jshshir`, `valid_from`, `valid_to`
- Test: `tests/Feature/Api/IntegrationEmployeeApiTest.php`

- [ ] **Step 1: Failing testlar**
  - `test_xodim_qoshiladi_va_foydalanuvchi_terminallariga_yoziladi` — 201; bazada bo‘lim = foydalanuvchiniki, jadval = config, yuz rasmi saqlangan; `transferOne` faqat foydalanuvchining 2 terminaliga (boshqa foydalanuvchi terminaliga emas); javob `employee` maydonlari va `terminals`
  - `test_terminal_xatosi_xodimni_bekor_qilmaydi` — `transferOne` `failed` → 201, xodim bazada, `terminals.0.status = failed`
  - `test_terminali_yoq_foydalanuvchi` — `terminals: []`
  - `test_bolimsiz_foydalanuvchi_403` — `NO_DEPARTMENT`, xodim yo‘q
  - `test_jadval_sozlanmagan_503` va `test_boshqa_bolim_jadvali_503`
  - `test_majburiy_maydonlar_422` (har biri), `test_mavjud_jshshir_422` (boshqa bo‘limda bo‘lsa ham), `test_boshqa_bolim_guruhi_422`
  - `test_buzuq_rasm_422` — buzuq base64, matn fayl, 1 MB+ — har biri 422, xodim yo‘q, `transferOne` chaqirilmaydi
  - `test_data_url_prefiksi_qabul_qilinadi`
  - `test_audit_logga_jshshirsiz_yoziladi`
- [ ] **Step 2:** testni ishga tushirish — Expected: FAIL (404).
- [ ] **Step 3:** yuqoridagi fayllar.
- [ ] **Step 4:** `php artisan test tests/Feature/Api` — Expected: PASS.
- [ ] **Step 5: Commit** `Integratsiya API: xodim qo‘shish`

### Task 3: `PUT|PATCH` — tahrirlash

**Files:**
- Create: `app/Http/Requests/Api/UpdateIntegrationEmployeeRequest.php` — Store qoidalari `sometimes` bilan, `jshshir` yo‘q, `face` ixtiyoriy, `valid_to ≥ valid_from` (bittasi berilsa — bazadagisi bilan solishtiriladi)
- Modify: `IntegrationEmployeeService::update(User $user, Employee $employee, array $data): array` — o‘zgarmagan ism qismlari saqlanadi, `EmployeeService::update()`, so‘ng `pushToTerminals()`
- Modify: controller `update(string $jshshir)` — `Employee::query()->where('card', …)` (scope’langan) yo‘q → 404 `NOT_FOUND`

- [ ] **Step 1: Failing testlar** — `test_xodim_tahrirlanadi_va_qayta_yoziladi` (faqat `valid_to` → boshqa maydonlar o‘zgarmaydi, `transferOne` chaqiriladi); `test_yuz_rasmi_almashtiriladi`; `test_boshqa_bolim_xodimi_404` (o‘zgarmaydi, `transferOne` yo‘q); `test_yoq_jshshir_404`; `test_valid_to_eski_valid_fromdan_oldin_422`; `test_patch_ham_ishlaydi`
- [ ] **Step 2:** Expected: FAIL. **Step 3:** kod. **Step 4:** Expected: PASS.
- [ ] **Step 5: Commit** `Integratsiya API: xodimni tahrirlash`

### Task 4: `DELETE` — o‘chirish

**Files:**
- Modify: `app/Services/Hikvision/EmployeeSyncService.php::removeFromDevices()` — `Device::withoutGlobalScope(AssignedDevicesScope::class)` (id’lar chaqiruvchida tekshirilgan; API foydalanuvchisi kirganda boshqa terminallar jim tushib qolmasin)
- Modify: `IntegrationEmployeeService::delete(Employee $employee): array{deleted: bool, terminals: list<...>}` — bog‘langan hamma terminal id’lari (`withoutGlobalScopes`), `removeFromDevices()`; hammasi muvaffaqiyatli bo‘lsa `EmployeeService::delete()` (navbatga hech narsa tushmaydi), aks holda xodim qoladi
- Modify: controller `destroy(string $jshshir)` — 200 `{"ok": true, "terminals": [...]}`; terminal xatosida **502** `TERMINAL_ERROR` + `terminals`, xodim bazada

- [ ] **Step 1: Failing testlar** (`HikvisionUserService::deleteUser` mock) — `test_xodim_hamma_terminallardan_va_bazadan_ochiriladi` (API foydalanuvchisiga ko‘rinmaydigan terminal ham; `Queue::fake()` — hech narsa navbatga tushmaydi); `test_terminal_xatosida_xodim_qoladi_502`; `test_boshqa_bolim_xodimini_ochirib_bolmaydi_404`; `test_terminalga_yozilmagan_xodim_ochiriladi`
- [ ] **Step 2:** Expected: FAIL. **Step 3:** kod. **Step 4:** `php artisan test tests/Feature/Api tests/Feature/Admin/EmployeeAdminTest.php tests/Feature/Hikvision` — Expected: PASS.
- [ ] **Step 5: Commit** `Integratsiya API: xodimni o‘chirish`

### Task 5: Hujjat va yakuniy tekshiruv

**Files:**
- Create: `docs/integration-api.md` — sozlash (ruxsat → rol → foydalanuvchi + bo‘lim + terminallarni biriktirish, `INTEGRATION_API_SCHEDULE_ID`), uchala amal, javob/xatolar jadvali, terminalga darhol yozilishi va umumiy terminallar ogohlantirishi, curl misollari, serverga chiqarish
- Modify: `CLAUDE.md` — tashqi kanallar ro‘yxati, “Employees never auto-sync” istisnolari, middleware nomi; `docs/parking-api.md` dagi middleware nomi

- [ ] **Step 1:** hujjatlar.
- [ ] **Step 2:** `COMPOSER_PROCESS_TIMEOUT=0 composer test` — Expected: PASS (Linux skip’lardan tashqari); `vendor/bin/pint --test` toza; `php artisan route:list --path=api/integration` — 3 ta route.
- [ ] **Step 3: Commit** `Integratsiya API: hujjat`
