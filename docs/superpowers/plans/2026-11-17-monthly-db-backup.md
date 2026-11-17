# Bazaning oylik zaxira nusxasi — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** `db:backup` buyrug‘i har oy 1-sanasi 04:00 da MySQL bazasining `.sql.gz` nusxasini yopiq papkaga yozadi, 12 tasini saqlaydi va super-admin'larga Telegram orqali xabar beradi.

**Architecture:** `DbBackupCommand` → `DatabaseBackupService::run(): DatabaseBackupResult` (Laravel `Process` bilan `mysqldump --result-file`, PHP zlib bilan siqish, aylantirish). Buyruq xatoni ushlaydi, loglaydi va `TelegramNotifier::notifySuperAdmins()` ni chaqiradi. Kunlik Spatie zaxirasiga tegilmaydi.

**Tech Stack:** Laravel 12, `Illuminate\Support\Facades\Process`, PHP `zlib`, PHPUnit (SQLite, `Process::fake()`, `Http::fake()`).

**Spec:** [docs/superpowers/specs/2026-11-17-monthly-db-backup-design.md](../specs/2026-11-17-monthly-db-backup-design.md)

## Global Constraints

- Barcha xabarlar, izohlar, buyruq tavsiflari o‘zbekcha (`o‘`, `g‘` — `‘` belgisi bilan, loyiha odati).
- Parol hech qachon buyruq satrida bo‘lmaydi; faqat 0600 ruxsatli vaqtinchalik `.cnf` orqali, `--defaults-extra-file` birinchi argument.
- Papka 0700, zaxira fayli 0600; standart yo‘l `storage_path('app/private/db-backups')` (public emas).
- Fayl nomi `{database}-YYYY-MM-DD-HHMMSS.sql.gz`; `keep` standart 12, hech qachon 1 dan kam emas.
- Telegram matnida parol, JSHSHIR, xodim ismi bo‘lmaydi.
- `tests/TestCase.php` ga tegilmaydi; testlar real `mysqldump` ni chaqirmaydi.

## Review Focus

- Parolda `"`, `\`, `#` yoki `;` bo‘lsa `.cnf` buzilmasligi kerak — qiymat qo‘shtirnoq ichida, `\` va `"` ekranlanadi (Task 1 testi).
- `mysqldump` 0 bilan chiqib, lekin dump yarim qolsa (`-- Dump completed` yo‘q) — xato, eski nusxalar o‘chirilmaydi (Task 1 testi).
- Zaxira papkasida begona fayllar (`readme.txt`, boshqa nomli `.gz`) aylantirishda o‘chirilmasligi kerak (Task 1 testi).
- Telegram API xato qaytarsa ham buyruq natijasi o‘zgarmaydi (Task 2 testi).
- Ulanish MySQL/MariaDB bo‘lmasa (masalan SQLite) — tushunarli xato, `mysqldump` chaqirilmaydi (Task 1 testi).

---

### Task 1: Sozlama, servis va `db:backup` buyrug‘i (dump, siqish, aylantirish)

**Files:**
- Create: `config/db_backup.php`
- Create: `app/Services/Backup/DatabaseBackupService.php`
- Create: `app/Services/Backup/DatabaseBackupResult.php`
- Create: `app/Console/Commands/DbBackupCommand.php`
- Test: `tests/Feature/Console/DbBackupCommandTest.php`

**Interfaces:**
- Produces:
  - `config('db_backup.enabled'|'path'|'keep'|'connection'|'dump_binary_path'|'timeout')` — env: `DB_BACKUP_ENABLED` (true), `DB_BACKUP_PATH` (bo‘sh → storage yo‘li), `DB_BACKUP_KEEP` (12), `DB_CONNECTION` (`mysql`), `MYSQL_DUMP_BINARY_PATH` (`''`), `DB_BACKUP_TIMEOUT` (3600).
  - `final readonly class DatabaseBackupResult { string $path; string $filename; int $bytes; int $kept; int $deleted; }`
  - `DatabaseBackupService::run(): DatabaseBackupResult` — xatoda `RuntimeException` (o‘zbekcha qisqa sabab) tashlaydi.
  - Buyruq `db:backup`: muvaffaqiyatda `SUCCESS` va `Zaxira olindi: {filename} ({hajm})`; xatoda `FAILURE` va `Zaxira olinmadi: {sabab}`.

- [ ] **Step 1: Failing testlar** — `setUp` da `config(['db_backup.path' => <test uchun vaqtinchalik papka>, 'db_backup.connection' => 'mysql', 'database.connections.mysql' => [driver mysql, host 127.0.0.1, port 3306, database eidora_db, username eidora, password 's3cr"et\\pa#ss']])`, `tearDown` da papkani o‘chirish. `Process::fake` yopilishi `--result-file=` argumentidagi yo‘lga `"-- MySQL dump\nINSERT ...;\n-- Dump completed on 2026-12-01  4:00:00\n"` yozadi va `.cnf` mazmunini testga saqlaydi.
  - `test_creates_gzipped_dump_without_password_on_command_line` — exit 0; papkada bitta `eidora_db-2026-12-01-040000.sql.gz` (`travelTo('2026-12-01 04:00:00')`); `gzdecode` → `INSERT` bor; `Process::assertRan` buyruq massivining 1-elementi `--defaults-extra-file=` bilan boshlanadi, hech bir argumentda `s3cr` yo‘q, `--single-transaction`, `--no-tablespaces`, oxirgi argument `eidora_db`; saqlangan `.cnf` da `password="s3cr\"et\\\\pa#ss"`; ishdan keyin papkada `.cnf`, `.sql`, `.part` yo‘q.
  - `test_keeps_only_newest_copies` — `keep=3`; oldindan 4 ta `eidora_db-2026-0X-01-040000.sql.gz` va `notes.txt`, `other.gz`; run → 3 ta eng yangi (yangisi bilan) qoladi, `notes.txt` va `other.gz` joyida.
  - `test_failed_dump_keeps_old_backups` — fake `Process::result('', 'mysqldump: Got error: 1045: Access denied', 2)`; exit 1, output `Zaxira olinmadi` va `Access denied`; eski nusxa joyida, yangi `.sql.gz` va vaqtinchalik fayllar yo‘q.
  - `test_incomplete_dump_is_rejected` — fake faylga `-- Dump completed` siz yozadi, exit 0 → buyruq exit 1, yangi `.sql.gz` yo‘q.
  - `test_non_mysql_connection_fails_without_running_dump` — `db_backup.connection => 'sqlite'`; exit 1, `Process::assertNothingRan()`.

- [ ] **Step 2: Run** `php artisan test tests/Feature/Console/DbBackupCommandTest.php` — FAIL (buyruq yo‘q).

- [ ] **Step 3: Implement.** Servis: driver `mysql`/`mariadb` bo‘lmasa xato; papka `0700`; `.cnf` (`[client]` user/password/host/port, `socket` bo‘sh bo‘lmasa) papka ichida `0600`; `Process::timeout($timeout)->run([$binary, "--defaults-extra-file=$cnf", '--single-transaction', '--quick', '--no-tablespaces', '--default-character-set=utf8mb4', "--result-file=$raw", $database])`; `$binary` = `rtrim(dump_binary_path,'/\\').'/mysqldump'` yoki bo‘sh bo‘lsa `mysqldump`; xato chiqish → `errorOutput` ning birinchi 300 belgisi; butunlik: fayl bor, bo‘sh emas, oxirgi ~1 KB da `-- Dump completed`; 1 MB bo‘laklab `gzopen(..., 'wb6')` → `.sql.gz.part` → `rename`, `chmod 0600`; `finally` da `.cnf`, xom `.sql`, `.part` o‘chiriladi. Aylantirish: `/^.+-\d{4}-\d{2}-\d{2}-\d{6}\.sql\.gz$/` ga mos fayllar, nomdagi vaqt bo‘yicha kamayish tartibida, `max(1, keep)` dan keyingilari o‘chiriladi. Buyruq servisni chaqiradi, `RuntimeException` ni ushlaydi, `Log::info`/`Log::error`.

- [ ] **Step 4: Run** o‘sha test fayli — PASS.

- [ ] **Step 5: Commit** `git add config/db_backup.php app/Services/Backup app/Console/Commands/DbBackupCommand.php tests/Feature/Console/DbBackupCommandTest.php` — "Baza zaxirasi: db:backup buyrug‘i".

### Task 2: Telegram xabari super-admin'larga

**Files:**
- Modify: `app/Services/Telegram/TelegramNotifier.php` (yangi metod)
- Modify: `app/Console/Commands/DbBackupCommand.php`
- Test: `tests/Feature/Console/DbBackupCommandTest.php`

**Interfaces:**
- Consumes: `DatabaseBackupResult` (Task 1).
- Produces: `TelegramNotifier::notifySuperAdmins(string $text): int` — `linkedUsers('backup')` (ya’ni faqat filtr `all`) ∩ `whereSuperAdmin`.

- [ ] **Step 1: Failing testlar** (`CreatesTelegramAdmin`, `RefreshDatabase`, `config(['telegram.token' => 'test-token'])`, `Http::fake(['https://api.telegram.org/*' => ...])`):
  - `test_success_is_reported_to_super_admins_only` — super-admin (chat 111, `all`), super-admin (chat 222, `device`), oddiy user (chat 333, `all`); 1 ta so‘rov chat 111 ga, matnda `Baza zaxirasi olindi`, fayl nomi va `12` bor.
  - `test_failure_is_reported` — mysqldump xatosi; chat 111 ga `Baza zaxirasi olinmadi` va `Access denied`; parol matnda yo‘q.
  - `test_telegram_error_does_not_change_exit_code` — Telegram 500 qaytaradi; muvaffaqiyatli zaxira exit 0.

- [ ] **Step 2: Run** — FAIL.

- [ ] **Step 3: Implement** metod va buyruqdan chaqiruv. Matn: muvaffaqiyat — `"✅ Baza zaxirasi olindi\nFayl: {filename}\nHajmi: {MB, 1 xona}\nSaqlangan nusxalar: {kept} ta (eng ko‘pi {keep})"`; xato — `"❌ Baza zaxirasi olinmadi\nSabab: {sabab}"`. `notifySuperAdmins` dagi istisno ushlanadi va loglanadi.

- [ ] **Step 4: Run** — PASS; `php artisan test tests/Feature/Telegram` ham o‘tadi.

- [ ] **Step 5: Commit** — "Baza zaxirasi: Telegram xabari super-admin'larga".

### Task 3: Scheduler, sozlama namunasi, hujjat

**Files:**
- Modify: `routes/console.php`
- Modify: `.env.example`
- Create: `docs/database-backup.md`
- Modify: `CLAUDE.md` (Artisan buyruqlari ro‘yxati)
- Test: `tests/Feature/Console/DbBackupCommandTest.php`

- [ ] **Step 1: Failing test** `test_backup_is_scheduled_monthly` — `app(Schedule::class)->events()` ichida buyrug‘ida `db:backup` bo‘lgan hodisa bor, `expression === '0 4 1 * *'`, `withoutOverlapping === true`.
- [ ] **Step 2: Run** — FAIL.
- [ ] **Step 3: Implement** `Schedule::command('db:backup')->monthlyOn(1, '04:00')->when(fn (): bool => (bool) config('db_backup.enabled', true))->withoutOverlapping()->runInBackground();` o‘zbekcha izoh bilan. `.env.example`: `DB_BACKUP_ENABLED=true`, `DB_BACKUP_KEEP=12`, `DB_BACKUP_PATH=`, `MYSQL_DUMP_BINARY_PATH` izohiga "Linux serverda bo‘sh qoldiring". Hujjat: nima, qachon, qayerda, sozlamalar, qo‘lda ishga tushirish, tiklash (`gunzip -c fayl.sql.gz | mysql ... eidora_db`), serverga chiqarish talablari, kunlik Spatie zaxirasidan farqi. CLAUDE.md ga bir qator.
- [ ] **Step 4: Run** — PASS; `php artisan schedule:list` da `0 4 1 * *  php artisan db:backup`.
- [ ] **Step 5: Commit** — "Baza zaxirasi: oylik jadval va hujjat".

### Task 4: Lokal tekshiruv va yakun

- [ ] `php artisan db:backup` (XAMPP MySQL, `MYSQL_DUMP_BINARY_PATH=C:/xampp/mysql/bin`) — exit 0; `storage/app/private/db-backups/*.sql.gz` paydo bo‘ladi; `gzip -t` / `zcat | tail -1` → `-- Dump completed`; papkada `.cnf` qolmagan.
- [ ] `composer test` — hammasi PASS; `vendor/bin/pint --test` — toza.
- [ ] Lokal zaxira faylini o‘chirish (git'ga tushmaydi, lekin shaxsiy ma’lumot).
