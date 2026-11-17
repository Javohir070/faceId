# Zaxira nusxalari sahifasi — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Super-admin Admin → Sozlamalar → "Zaxira nusxalari" sahifasida `db:backup` nusxalarini ko‘radi va parolni qayta tasdiqlab yuklab oladi.

**Architecture:** `BackupController` (index, download) → `DatabaseBackupService::backups()` / `downloadPath()` (fayl naqshi aylantirish bilan umumiy). Serverda veb-foydalanuvchi o‘qishi uchun `DB_BACKUP_GROUP` — `db:backup` papkani 0750, fayllarni 0640 qilib shu guruhga beradi.

**Tech Stack:** Laravel 12, Inertia + React 19, Ant Design 6, Spatie permission (`role:` middleware), PHPUnit (SQLite).

**Spec:** [docs/superpowers/specs/2026-11-17-backups-page-design.md](../specs/2026-11-17-backups-page-design.md)

## Global Constraints

- UI matnlari, xabarlar, izohlar o‘zbekcha; ikonkalar faqat `@ant-design/icons`.
- Kirish faqat `super-admin` roli; ruxsat qatori/migratsiya yo‘q.
- Yuklab olish `password.confirm` ortida; har yuklab olish `Log::info` (user id, email, fayl, IP `App\Http\ClientIp`).
- Fayl nomi naqshi: `{baza}-YYYY-MM-DD-HHMMSS.sql.gz`, faqat `config('db_backup.path')` ichida.
- Navigatsiya Inertia orqali; yuklab olish — `Button href` (eksportlardagidek). Yangi marshrutlar `resources/js/routes.js` ga.

## Review Focus

- `../`, `%2e%2e`, absolyut yo‘l yoki boshqa baza nomi bilan fayl so‘ralsa — 404, papkadan tashqariga chiqilmaydi (Task 2 testi).
- Papka mavjud emas — bo‘sh ro‘yxat, xato emas (Task 2 testi).
- `DB_BACKUP_GROUP` mavjud bo‘lmagan guruh — zaxira baribir olinadi, faqat ogohlantirish (Task 1 testi).
- Hamma ruxsatlari bor, lekin super-admin bo‘lmagan foydalanuvchi — 403 va menyuda yo‘q (Task 2 testi).
- Fayl o‘chirilgan, lekin ro‘yxatdagi havola bosilsa — 404 (Task 2 testi).

---

### Task 1: `DB_BACKUP_GROUP` — veb-foydalanuvchi o‘qishi uchun guruh ruxsati

**Files:** Modify `config/db_backup.php`, `app/Services/Backup/DatabaseBackupService.php`, `.env.example`; Test `tests/Feature/Console/DbBackupCommandTest.php`

**Interfaces — Produces:** `config('db_backup.group')` (`DB_BACKUP_GROUP`, standart `''`).

- [ ] **Step 1: Failing testlar**
  - `test_unknown_group_does_not_break_backup` — `config(['db_backup.group' => 'biocontrol-no-such-group'])`, `Log::spy()`; `db:backup` — exit 0, yangi `.sql.gz` bor; `Log::shouldHaveReceived('warning')` xabarida `biocontrol-no-such-group`.
  - `test_group_gets_read_access` — Windows'da `markTestSkipped`; `group` = joriy jarayon guruhi (`posix_getgrgid(posix_getegid())['name']`); papka `0750`, fayl `0640`, oldindan bor `0600` eski nusxa ham `0640`.
- [ ] **Step 2: Run** `php artisan test --filter="test_unknown_group_does_not_break_backup|test_group_gets_read_access"` — birinchisi FAIL (warning yo‘q), ikkinchisi Windows'da SKIPPED.
- [ ] **Step 3: Implement** — guruh bo‘sh bo‘lmasa: aylantirishdan keyin papka `chmod 0750` + `@chgrp`, shu bazaning har zaxira fayli `chmod 0640` + `@chgrp`; birinchi muvaffaqiyatsiz `chgrp` da bitta `Log::warning('Baza zaxirasi: guruhga berib bo‘lmadi', ['group' => …])`. Bo‘sh bo‘lsa avvalgidek 0700/0600. Vaqtinchalik fayllarga tegilmaydi.
- [ ] **Step 4: Run** — PASS (ikkinchisi Windows'da SKIPPED); butun `DbBackupCommandTest` PASS.
- [ ] **Step 5: Commit** — "Zaxira nusxalari: DB_BACKUP_GROUP".

### Task 2: Sahifa va yuklab olish

**Files:** Create `app/Http/Controllers/BackupController.php`, `resources/js/Pages/Admin/Backups/Index.jsx`, `tests/Feature/Admin/BackupPageTest.php`; Modify `app/Services/Backup/DatabaseBackupService.php`, `routes/web.php`, `app/Http/Middleware/HandleInertiaRequests.php`, `resources/js/config/adminNav.js`, `resources/js/routes.js`

**Interfaces — Produces:**
- `DatabaseBackupService::backups(): array{unreadable: bool, items: list<array{filename: string, bytes: int, created_at: string}>}` — `created_at` `Y-m-d H:i:s`, nomdagi vaqtdan; yangisi birinchi.
- `DatabaseBackupService::downloadPath(string $filename): ?string` — naqshga mos va papkadagi fayl, aks holda `null`.
- Marshrutlar: `GET /admin/backups` → `admin.backups.index` (`role:super-admin`); `GET /admin/backups/{filename}/download` → `admin.backups.download` (`role:super-admin`, `password.confirm`).
- Inertia props: `backups` (items), `unreadable`, `keep`. `can.backups`.

- [ ] **Step 1: Failing testlar** (`RefreshDatabase`, `InteractsWithPermissions`, `CreatesTelegramAdmin`; `db_backup.path` vaqtinchalik papka, `database.connections.mysql.database = eidora_db`, `db_backup.connection = mysql`):
  - `test_super_admin_sees_backups_newest_first` — fayllar `eidora_db-2026-10-01-040000.sql.gz`, `eidora_db-2026-11-01-040000.sql.gz`, `other_db-2026-11-02-040000.sql.gz`, `notes.txt`; component `Admin/Backups/Index`, `backups` 2 ta, birinchisi `eidora_db-2026-11-01-040000.sql.gz`, `created_at` `2026-11-01 04:00:00`, `bytes` fayl hajmi; `keep` 12; `can.backups` true.
  - `test_missing_directory_shows_empty_list` — papka yo‘q; 200, `backups` bo‘sh, `unreadable` false.
  - `test_non_super_admin_is_forbidden` — `createUserWithPermissions()` dan super-admin rolini olib tashlab (yoki faqat ruxsatlar bilan foydalanuvchi): index 403, download 403; boshqa sahifada `can.backups` false.
  - `test_download_requires_password_confirmation` — super-admin, sessiyasiz: redirect `password.confirm`.
  - `test_download_returns_file_and_logs` — `withSession(['auth.password_confirmed_at' => time()])`, `Log::spy()`; 200, `Content-Disposition` da fayl nomi, `Log::shouldHaveReceived('info')` (`Baza zaxirasi yuklab olindi`, `file`).
  - `test_download_rejects_foreign_and_missing_files` — tasdiqlangan sessiya: `other_db-2026-11-02-040000.sql.gz`, `notes.txt`, `eidora_db-2026-12-01-040000.sql.gz` (yo‘q), `..%2F.env`, `eidora_db-2026-11-01-040000.sql` — hammasi 404.
- [ ] **Step 2: Run** `php artisan test tests/Feature/Admin/BackupPageTest.php` — FAIL (marshrut yo‘q).
- [ ] **Step 3: Implement.** Servis: naqsh `/^{preg_quote(prefix)}-(\d{4}-\d{2}-\d{2}-\d{6})\.sql\.gz$/` aylantirish bilan umumiy private metoddan; `unreadable` = papka bor va `! is_readable`. Controller: `index` → `Inertia::render('Admin/Backups/Index', [...])`; `download` → `downloadPath` null bo‘lsa `abort(404)`, log, `response()->download($path, $filename, ['Content-Type' => 'application/gzip'])`. Marshrut `{filename}` uchun `->where('filename', '[A-Za-z0-9_.-]+')`. `can.backups` = `$user->hasRole('super-admin')`. Menyu: Sozlamalar ichida `{ key: 'backups', href: route('admin.backups.index'), label: 'Zaxira nusxalari', icon: DatabaseOutlined }`. Sahifa: `PageHeader` (sarlavha "Zaxira nusxalari", izoh "Har oy 1-sanasi 04:00 da avtomatik olinadi, oxirgi {keep} tasi saqlanadi."), `unreadable` bo‘lsa Ant `Alert` (warning, "Zaxira papkasini o‘qib bo‘lmadi. Serverda .env ga DB_BACKUP_GROUP (masalan www-data) yozing va php artisan db:backup ni ishga tushiring."), `TableShell` + `data-table`: N#, Fayl, Sana va vaqt, Hajmi, `Button href` "Yuklab olish" (`DownloadOutlined`). Bo‘sh: "Hali zaxira nusxasi yo‘q".
- [ ] **Step 4: Run** — PASS; `npm run build` xatosiz.
- [ ] **Step 5: Commit** — "Zaxira nusxalari sahifasi".

### Task 3: Hujjat va brauzerda tekshiruv

- [ ] `docs/database-backup.md`: "Panelda" bo‘limi (kim ko‘radi, parol, log) va serverga chiqarishga `DB_BACKUP_GROUP` qadami (veb-foydalanuvchini aniqlash: `ps -o user= -C php-fpm` yoki `stat -c %G storage/logs`); CLAUDE.md dagi `db:backup` qatoriga sahifa.
- [ ] Lokal: `php artisan db:backup`, super-admin bilan `/admin/backups` — ro‘yxat, menyu bandi, yuklab olish parol so‘raydi va keyin fayl keladi; console xatosiz; skrinshot. Keyin lokal zaxira o‘chiriladi.
- [ ] `COMPOSER_PROCESS_TIMEOUT=0 composer test` PASS, `vendor/bin/pint --test` toza; commit.
