# Bazaning oylik zaxira nusxasi (`db:backup`)

Har oy 1-sanasi soat **04:00** da scheduler `php artisan db:backup` ni ishga tushiradi. Buyruq MySQL/MariaDB
bazasining `mysqldump` nusxasini oladi, siqadi (`.sql.gz`) va serverning o‘zida, yopiq papkada saqlaydi.
Eng yangi **12 ta** nusxa qoladi, eskilari o‘chiriladi. Natija super-admin'larga Telegram orqali xabar qilinadi.

Spec: [superpowers/specs/2026-11-17-monthly-db-backup-design.md](superpowers/specs/2026-11-17-monthly-db-backup-design.md).

## Kunlik zaxiradan farqi

Tizimda ikkita mustaqil zaxira bor, bir-biriga tegmaydi:

| | Kunlik (Spatie `backup:run`) | Oylik (`db:backup`) |
|---|---|---|
| Qachon | har kuni 01:30 (`backup:clean` 01:00, `backup:monitor` 03:00) | har oy 1-sanasi 04:00 |
| Nima | butun loyiha papkasi (`.env` bilan) + baza, bitta zip | faqat baza, `.sql.gz` |
| Qayerda | `storage/app/private/{APP_NAME}/` | `storage/app/private/db-backups/` |
| Necha nusxa | `config/backup.php` → `cleanup` (kunlar bo‘yicha, 5 GB gacha) | `DB_BACKUP_KEEP` (12) |
| Xabar | `config/backup.php` → mail (`your@example.com` — sozlanmagan) | Telegram, super-admin'lar |

## Fayllar

```
storage/app/private/db-backups/
  eidora_db-2026-12-01-040000.sql.gz
  eidora_db-2027-01-01-040000.sql.gz
  …
```

- Papka `0700`, fayllar `0600`. Serverda cron root nomidan ishlaydi, shuning uchun fayllar root'niki bo‘ladi va
  veb-server ularni o‘qiy olmaydi (o‘qishi kerak ham emas).
- Dumpda **JSHSHIR va yuz rasmlari** (`faces` jadvali) bor: faylni public papkaga, chatga yoki pochtaga qo‘ymang.
- Aylantirish faqat `{baza}-YYYY-MM-DD-HHMMSS.sql.gz` shaklidagi fayllarga tegadi. Papkaga qo‘lda qo‘yilgan
  boshqa fayllar o‘chirilmaydi. Yangi nusxa olinmasa, eskilari ham o‘chirilmaydi.

## Qanday ishlaydi

1. Zaxira papkasida vaqtinchalik `.client-*.cnf` fayl (`0600`) yoziladi: user, password, host, port. Parol
   buyruq satriga chiqmaydi (`ps` da ko‘rinmaydi): `mysqldump --defaults-extra-file=…`.
2. `mysqldump --single-transaction --quick --no-tablespaces --default-character-set=utf8mb4 --result-file=…`.
   `--single-transaction` InnoDB jadvallarini qulflamasdan izchil nusxa oladi, ya’ni tizim ishlashda davom etadi.
   `--no-tablespaces` PROCESS huquqi bo‘lmagan foydalanuvchi uchun kerak (MySQL 8).
3. Butunlik tekshiruvi: chiqish kodi 0 va dump oxirida `-- Dump completed` qatori bor.
4. PHP dumpni `.sql.gz` ga siqadi (`gzip` buyrug‘i kerak emas, Windows'da ham ishlaydi). Yarim yozilgan fayl
   `.part` nomida qoladi va nusxa hisoblanmaydi.
5. Vaqtinchalik `.cnf` va siqilmagan `.sql` xato bo‘lsa ham o‘chiriladi.
6. Eski nusxalar o‘chiriladi, keyin Telegram xabari yuboriladi va `storage/logs` ga yoziladi.

## Sozlamalar (`config/db_backup.php`, `.env`)

| O‘zgaruvchi | Standart | Ma’nosi |
|---|---|---|
| `DB_BACKUP_ENABLED` | `true` | `false` — scheduler ishga tushirmaydi (qo‘lda `db:backup` baribir ishlaydi) |
| `DB_BACKUP_KEEP` | `12` | nechta eng yangi nusxa saqlanadi (kamida 1) |
| `DB_BACKUP_PATH` | bo‘sh → `storage/app/private/db-backups` | boshqa papka; public bo‘lmasin |
| `DB_BACKUP_TIMEOUT` | `3600` | `mysqldump` uchun soniya |
| `MYSQL_DUMP_BINARY_PATH` | bo‘sh → `PATH` dagi `mysqldump` | Windows/XAMPP: `C:/xampp/mysql/bin`. Linux serverda **bo‘sh** qoldiring |

Ulanish `DB_CONNECTION` / `DB_HOST` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` dan olinadi.

## Telegram

Xabar faqat Telegram ulagan, xabar filtri `all` bo‘lgan **super-admin'larga** boradi. Guruhlarga yuborilmaydi,
fayl esa umuman yuborilmaydi.

- Muvaffaqiyatda: fayl nomi, hajmi va saqlangan nusxalar soni.
- Xatoda: qisqa sabab, masalan `mysqldump xato bilan tugadi (kod 2): … Access denied …`.

Telegram ishlamasa ham zaxira natijasi o‘zgarmaydi, xato logga yoziladi.

## Qo‘lda ishga tushirish

```bash
php artisan db:backup
```

Muvaffaqiyatda `Zaxira olindi: eidora_db-….sql.gz (… MB). Saqlangan nusxalar: N ta, o‘chirilgan: M ta.` chiqadi
va kod 0 qaytadi. Xatoda `Zaxira olinmadi: …` chiqadi va kod 1 qaytadi. Qo‘lda olingan nusxa ham 12 talik
hisobga kiradi.

## Tiklash

Avval yangi, bo‘sh bazaga tiklab tekshiring, ishlayotgan bazaning ustiga birdaniga yozmang:

```bash
gzip -t eidora_db-2026-12-01-040000.sql.gz                    # fayl butunmi
mysql -u root -p -e "CREATE DATABASE eidora_restore CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
gunzip -c eidora_db-2026-12-01-040000.sql.gz | mysql -u root -p eidora_restore
```

Hammasi joyida bo‘lsa, `.env` dagi `DB_DATABASE` ni almashtiring yoki xuddi shu buyruq bilan asosiy bazaga
tiklang (oldin uning ham zaxirasini oling).

## Serverga chiqarish

1. **`mysqldump` bor bo‘lishi kerak.** `which mysqldump && mysqldump --version`. Yo‘q bo‘lsa
   `apt install mysql-client` (MariaDB serverida `mariadb-client`). Versiya server versiyasiga mos bo‘lsin:
   MySQL 8 ning `mysqldump` i MariaDB'ga ulansa `--column-statistics` xatosini berishi mumkin.
2. **`.env`:** `MYSQL_DUMP_BINARY_PATH=` (bo‘sh). Agar `.env` `.env.example` dan nusxalangan bo‘lsa, unda
   `C:/xampp/mysql/bin` turgan bo‘ladi va **kunlik Spatie zaxirasi ham** ishlamaydi. Kerak bo‘lsa
   `DB_BACKUP_KEEP` ni qo‘shing. `php artisan config:cache` ishlatilsa, `.env` o‘zgargach uni qayta ishga tushiring.
3. **Baza foydalanuvchisining huquqlari** (`eidora`): `SELECT`, `SHOW VIEW`, `TRIGGER`, `LOCK TABLES`.
   Tekshirish: `SHOW GRANTS FOR 'eidora'@'%';`.
4. **Papka:** `storage/app/private` ga cron foydalanuvchisi (root) yoza olishi kerak. `db-backups` papkasini
   buyruq o‘zi `0700` bilan yaratadi.
5. **Joy:** `du -sh storage/app/private/*` va `df -h`. Bitta nusxa siqilgan holda taxminan bazaning yarmidan
   kamroq (yuz rasmlari yomon siqiladi), 12 ta nusxa shuning 12 barobari.
6. **Cron** allaqachon `php artisan schedule:run` ni har daqiqada ishga tushiradi, qo‘shimcha cron qatori kerak
   emas. Tekshirish: `php artisan schedule:list | grep db:backup` → `0 4 1 * *`.
7. **Birinchi marta qo‘lda** ishga tushirib ko‘ring: `php artisan db:backup`, keyin
   `ls -la storage/app/private/db-backups` va `gzip -t <fayl>`.

## Muammolar

| Xabar | Sabab |
|---|---|
| `kod 127` yoki `Unable to launch a new process` | `mysqldump` topilmadi: 1- va 2-bandlarni tekshiring |
| `Access denied for user` | `.env` dagi `DB_USERNAME` / `DB_PASSWORD` noto‘g‘ri yoki huquq yetmaydi |
| `PROCESS privilege` | eski kod yoki `--no-tablespaces` ni bilmaydigan `mysqldump` |
| `«-- Dump completed» qatori yo‘q` | dump yarmida to‘xtagan (disk to‘lgan, ulanish uzilgan); `df -h` |
| `Zaxira papkasiga yozib bo‘lmaydi` | papka egasi yoki ruxsati; buyruqni cron foydalanuvchisi nomidan ishga tushiring |
