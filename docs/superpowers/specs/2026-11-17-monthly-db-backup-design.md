# Bazaning oylik zaxira nusxasi

Foydalanuvchi so‘zlari: "tizimga har bir oyda auto backup qo'shish kerak bazadan".

## Hozirgi holat

Tizimda Spatie `laravel-backup` bor (commit `9e9b59b`): `routes/console.php` da **har kuni** 01:30 `backup:run`
butun loyiha papkasi va bazani bitta zip qilib `storage/app/private/EIdora/` ga yozadi, 01:00 `backup:clean`,
03:00 `backup:monitor`. Xabarlari `your@example.com` ga ketadi, ya’ni hech kimga yetmaydi; serverda ishlayotgani
tekshirilmagan.

## Nima qilinadi

- Kunlik Spatie zaxirasi **o‘z holicha qoladi** (foydalanuvchi qarori). Oylik baza zaxirasi undan alohida.
- Yangi buyruq **`db:backup`**: joriy MySQL/MariaDB bazasining `mysqldump` nusxasi, `.sql.gz` ga siqilgan.
- Har oy **1-sanasi soat 04:00** da scheduler ishga tushiradi (02:00–03:00 dan qochiladi, 01:00–01:30 dagi
  solishtirish va Spatie bilan to‘qnashmaydi).
- Faqat **serverda** saqlanadi: `storage/app/private/db-backups/`. Boshqa joyga yuborilmaydi.
- **12 ta** eng yangi nusxa saqlanadi; eskilari yangi nusxa muvaffaqiyatli olingandan keyingina o‘chiriladi.
- **Telegram**: super-admin'larga (Telegram ulagan, xabar filtri `all`) muvaffaqiyatda fayl nomi, hajmi va
  nusxalar soni; xatoda qisqa sabab. Fayl Telegram'ga yuborilmaydi.
- Panelda ko‘rinmaydi.

## Yondashuvlar

1. **Alohida `db:backup` (tanlandi).** Laravel `Process` + `mysqldump`, siqish PHP'ning zlib'i bilan. Kunlik
   Spatie zaxirasi va uning tozalash qoidalaridan to‘liq mustaqil, Windows'da ham ishlaydi, `Process::fake()`
   bilan testlanadi.
2. Spatie'ni oylik `--only-db` bilan ham ishlatish — rad etildi: kunlik `backup:clean` bir papkadagi zip'larni
   bitta to‘plam deb ko‘radi (oyliklarni 4 oy saqlaydi, 5 GB chegarasi), 12 nusxa uchun alohida nom/disk va o‘z
   tozalash strategiyasi kerak bo‘lardi; uning `GzipCompressor` i `gzip` buyrug‘ini chaqiradi, Windows'da yo‘q.
3. Buyruq ichida `spatie/db-dumper` — rad etildi: siqish yana `gzip` buyrug‘i orqali, Symfony Process'ni
   to‘g‘ridan-to‘g‘ri chaqiradi, `Process::fake()` ushlamaydi.

## Qarorlar

- **Parol buyruq satrida ko‘rinmaydi.** Zaxira papkasida vaqtinchalik `.cnf` fayl (`[client]` user, password,
  host, port, socket; ruxsati 0600) yoziladi va `--defaults-extra-file` birinchi argument bo‘lib beriladi; ish
  tugagach (xatoda ham) o‘chiriladi. Vaqtinchalik fayl umumiy `/tmp` ga emas, yopiq zaxira papkasiga yoziladi.
- **`mysqldump` parametrlari:** `--single-transaction --quick --no-tablespaces --default-character-set=utf8mb4
  --result-file=…`. `--no-tablespaces` — MySQL 8 da PROCESS huquqi bo‘lmagan foydalanuvchi (`eidora`) uchun.
  Natija xotiraga emas, faylga yoziladi (bazada `faces` va eventlar katta).
- **Butunlik tekshiruvi:** chiqish kodi 0, fayl bo‘sh emas va oxirida `-- Dump completed` qatori bor. Aks holda xato.
- **Siqish:** xom `.sql` PHP'da 1 MB bo‘laklab `gzopen` bilan `.sql.gz.part` ga yoziladi, keyin
  `.sql.gz` ga qayta nomlanadi (yarim fayl hech qachon nusxa deb hisoblanmaydi). Xom `.sql` darhol o‘chiriladi.
- **Fayl nomi:** `{baza}-YYYY-MM-DD-HHMMSS.sql.gz`. Aylantirish faqat shu shakldagi fayllarga tegadi, nomdagi
  vaqt bo‘yicha saralanadi.
- **Ruxsatlar:** papka 0700, fayl 0600. Serverda cron root nomidan ishlaydi, ya’ni fayllar root'niki bo‘ladi;
  veb-server ularni o‘qiy olmaydi va o‘qishi shart emas.
- **Sozlamalar** `config/db_backup.php`: `enabled` (`DB_BACKUP_ENABLED`, faqat scheduler'ni o‘chiradi — qo‘lda
  ishga tushirish doim ishlaydi), `path` (`DB_BACKUP_PATH`, bo‘sh — `storage/app/private/db-backups`), `keep`
  (`DB_BACKUP_KEEP`, 12; 1 dan kam bo‘lmaydi), `connection` (`DB_CONNECTION`), `dump_binary_path`
  (`MYSQL_DUMP_BINARY_PATH` — Spatie bilan umumiy; bo‘sh — `PATH` dagi `mysqldump`), `timeout` (`DB_BACKUP_TIMEOUT`,
  3600 s).
- **Qatlam:** `db:backup` buyrug‘i → `App\Services\Backup\DatabaseBackupService` (dump, siqish, aylantirish) →
  natija. Telegram uchun `TelegramNotifier::notifySuperAdmins()` (super-admin + filtr `all`). Telegram xatosi
  buyruq natijasini o‘zgartirmaydi. Log: muvaffaqiyat `info`, xato `error`.
- **Scheduler:** `monthlyOn(1, '04:00')`, `when(enabled)`, `withoutOverlapping()`, `runInBackground()`.
  `onOneServer` kerak emas (bitta server).

## Builder'ga qoldirilgan

Xabar matnlarining aniq ko‘rinishi, xato sababini qisqartirish uzunligi, hujjat (`docs/database-backup.md`)
tuzilishi, tiklash buyrug‘i misoli.

## Testlar

- Muvaffaqiyat: `.sql.gz` paydo bo‘ladi, ochilganda dump matni; buyruqda parol yo‘q, `--defaults-extra-file`
  birinchi; vaqtinchalik `.cnf` va xom `.sql` qolmaydi; super-admin'ga Telegram xabari, oddiy foydalanuvchiga va
  filtri `device`/`none` bo‘lganga yo‘q.
- Aylantirish: 13-nusxadan keyin eng eskisi o‘chadi, nomi boshqa fayllarga tegilmaydi.
- Xato: `mysqldump` xato kodi yoki to‘liq bo‘lmagan dump — `FAILURE`, yangi `.sql.gz` yo‘q, eski nusxalar joyida,
  Telegram'ga xato xabari.
- SQLite ulanishida aniq xato.
- Scheduler: `db:backup` `0 4 1 * *` ifodasi bilan ro‘yxatda.
