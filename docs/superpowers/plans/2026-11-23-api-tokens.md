# Tashqi API tokeni Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** `POST|DELETE /api/auth/token` — tashqi tizim email+parol bilan 24 soatlik Sanctum token oladi va bekor qiladi; parking va integratsiya API lari Basic bilan birga `Bearer` ni ham qabul qiladi.

**Architecture:** Basic cheklovi va parol tekshiruvi `AuthenticateApiUser` dan `App\Services\ApiUserLogin` ga chiqadi (middleware ham, token controller ham ishlatadi). `AuthenticateApiUser` `bearerToken()` bo‘lsa `PersonalAccessToken::findToken()` bilan foydalanuvchini guard’ga qo‘yadi, so‘ng ruxsat tekshiruvi o‘zgarmaydi. `User` model hodisalari parol o‘zgarsa yoki foydalanuvchi o‘chsa tokenlarni o‘chiradi.

**Tech Stack:** Laravel 12, Sanctum 4.4 (o‘rnatilgan, `personal_access_tokens` jadvali bor), PHPUnit.

**Spec:** `docs/superpowers/specs/2026-11-23-api-tokens-design.md`

## Global Constraints

- Manzillar: `POST /api/auth/token` (201), `DELETE /api/auth/token` (200); nomlar `api.auth.token.store|destroy`.
- Javob: `{"ok": true, "token", "token_type": "Bearer", "expires_at": "Y-m-d H:i:s"}`; xato shakli `{"ok": false, "error": {"code", "message"}}`.
- Muddat: `config('api_tokens.ttl_minutes')` ← `API_TOKEN_TTL_MINUTES`, sukut va noto‘g‘ri qiymatda 1440.
- Token faqat `menu_permissions.api` dagi ruxsatlardan kamida bittasi bor foydalanuvchiga; aks holda 403 `FORBIDDEN`.
- Xato urinish cheklovi Basic bilan umumiy (`api-user|<email>|<ip>` 5/daq, `api-user-ip|<ip>` 20/daq). Bearer yo‘lida cheklov yo‘q.
- Token qiymati, parol logga yozilmaydi. Mavjud parking/integratsiya testlari o‘zgarishsiz yashil qoladi.

## Review Focus

1. Muddati o‘tgan, o‘chirilgan yoki egasi o‘chgan token — 401, ruxsat olingan foydalanuvchi tokeni — 403.
2. Parol o‘zgarganda (admin, profil, tiklash) va foydalanuvchi o‘chganda tokenlar o‘chadi.
3. Bearer bilan integratsiya ro‘yxati bo‘lim cheklovi bilan ishlaydi; super-admin tokeni integratsiyada 403.
4. Token endpoint’i orqali parol terish Basic cheklovidan chetlab o‘tolmaydi.
5. Bearer so‘rovi sessiya ochmaydi.

---

### Task 1: Token berish (`POST /api/auth/token`) va umumiy kirish servisi

**Files:**
- Create: `app/Services/ApiUserLogin.php` — `attempt(Request $request, string $email, string $password): User|JsonResponse` (cheklov, `Auth::guard('web')->once`, log), `static error(string $kod, string $xabar, int $status, array $headers = []): JsonResponse`
- Modify: `app/Http/Middleware/AuthenticateApiUser.php` — Basic yo‘li `ApiUserLogin` orqali (xatti-harakat o‘zgarmaydi)
- Create: `app/Http/Middleware/ForceJsonAccept.php`; `config/api_tokens.php`; `.env.example`
- Create: `app/Http/Requests/IssueApiTokenRequest.php` (`email` required email, `password` required string, `name` nullable string max:100)
- Create: `app/Http/Controllers/Api/ApiTokenController.php` — `store()`
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/ApiTokenTest.php`

- [ ] **Step 1: Failing testlar** — `test_token_beriladi` (201, `token` `id|...`, `Bearer`, `expires_at` = hozir+24 soat, jadvalda 1 qator, nom `name` yoki sukut); `test_notogri_parol_401`; `test_cheklov_basic_bilan_umumiy` (token endpoint’ida 5 xato → parking Basic to‘g‘ri parol 429); `test_api_ruxsatisiz_foydalanuvchiga_berilmaydi` (403, qator yo‘q); `test_majburiy_maydonlar_422_accept_siz_ham_json`; `test_ttl_sozlanadi`; `test_token_logga_yozilmaydi`
- [ ] **Step 2:** `php artisan test tests/Feature/Api/ApiTokenTest.php` — Expected: FAIL. **Step 3:** kod. **Step 4:** `php artisan test tests/Feature/Api` — Expected: PASS (parking/integratsiya ham).
- [ ] **Step 5: Commit** `Tashqi API: token berish`

### Task 2: Bearer qabul qilish va `DELETE /api/auth/token`

**Files:**
- Modify: `AuthenticateApiUser` — `bearerToken()` → `PersonalAccessToken::findToken()`, muddat/egasi tekshiruvi, `Auth::guard('web')->setUser()`, `withAccessToken()`, `last_used_at`; 401 `WWW-Authenticate: Bearer`
- Modify: `ApiTokenController::destroy()`; `routes/api.php`

- [ ] **Step 1: Failing testlar** — `test_token_bilan_parking_royxati`; `test_token_bilan_integratsiya_royxati_bolim_cheklovi_bilan`; `test_super_admin_tokeni_integratsiyada_403`; `test_notogri_token_401`; `test_muddati_otgan_token_401`; `test_ruxsat_olinsa_token_403`; `test_bearer_sessiyaga_yozilmaydi`; `test_last_used_at_yangilanadi`; `test_token_bekor_qilinadi` (DELETE → 200, so‘ng 401); `test_tokensiz_bekor_qilish_401`
- [ ] **Step 2–4** — Expected FAIL → PASS (`php artisan test tests/Feature/Api`).
- [ ] **Step 5: Commit** `Tashqi API: Bearer token bilan kirish`

### Task 3: Tokenlarni bekor qilish va tozalash

**Files:**
- Modify: `app/Models/User.php` — `booted()`: `updated` da `wasChanged('password')` → `tokens()->delete()`; `deleting` → `tokens()->delete()`
- Modify: `routes/console.php` — `Schedule::command('sanctum:prune-expired --hours=24')->daily()`

- [ ] **Step 1: Failing testlar** — `test_parol_ozgarsa_tokenlar_ochadi` (model va admin paneli `admin.users.update` orqali); `test_boshqa_maydon_ozgarsa_tokenlar_qoladi`; `test_foydalanuvchi_ochsa_tokenlar_ochadi`; `test_eskirgan_tokenlar_har_kuni_tozalanadi` (schedule)
- [ ] **Step 2–4** — Expected FAIL → PASS. **Step 5: Commit** `Tashqi API: tokenlarni bekor qilish`

### Task 4: Hujjat

- Create: `docs/api-auth.md`; Modify: `docs/parking-api.md`, `docs/integration-api.md` (kirish bo‘limi havola), `CLAUDE.md`
- [ ] `COMPOSER_PROCESS_TIMEOUT=0 composer test` — PASS; `vendor/bin/pint --test`; commit `Tashqi API: token hujjati`
