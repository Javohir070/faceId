# Zaxira nusxalari sahifasi

Foydalanuvchi so‘zlari: eski tizimdagi "Резервное копирование" sahifasini ko‘rsatib, "shu tizimda shuni qo‘shsa
bo‘ladimi, olgan backuplarni ko‘rinadigan qilish uchun".

Asos: [2026-11-17-monthly-db-backup-design.md](2026-11-17-monthly-db-backup-design.md) (`db:backup`).

## Nima qilinadi

- Admin → Sozlamalar ichida **"Zaxira nusxalari"** sahifasi (`/admin/backups`): `db:backup` olgan `.sql.gz`
  fayllar ro‘yxati — №, fayl nomi, sana va vaqt, hajmi — eng yangisi tepada, va **yuklab olish**.
- Tepada izoh: "Har oy 1-sanasi 04:00 da avtomatik olinadi, oxirgi {keep} tasi saqlanadi".
- Faqat oylik baza zaxiralari (kunlik Spatie zip'lari ko‘rsatilmaydi). Faqat shu bazaning fayllari.
- **Yo‘q** (foydalanuvchi tanlovi): zaxira olish tugmasi, o‘chirish, tiklash ("Применить").

## Yondashuvlar

1. **Papkani to‘g‘ridan-to‘g‘ri o‘qish + guruhga o‘qish ruxsati (tanlandi).** `DB_BACKUP_GROUP` (masalan
   `www-data`) berilsa `db:backup` papkani `0750`, zaxira fayllarini `0640` qilib shu guruhga beradi.
2. Har zaxirani bazadagi jadvalga yozish — rad etildi: yuklab olish uchun baribir 1-banddagi ruxsat kerak,
   migratsiya/model/repository qo‘shiladi, fayllar qo‘lda o‘chirilsa jadval bilan mos kelmay qoladi.
3. Cron'ni veb-foydalanuvchiga o‘tkazish — rad etildi: barcha rejali vazifalar o‘zgaradi, `storage/` dagi root
   fayllari bilan muammo.

## Qarorlar

- **Kirish faqat super-admin** (foydalanuvchi tanlovi): `role:super-admin` middleware; ruxsat qatori va
  migratsiya yo‘q, rollar orqali berib bo‘lmaydi. Umumiy `can` xaritasida `backups` kaliti
  (`hasRole('super-admin')`), menyuda Sozlamalar guruhida.
- **Yuklab olishdan oldin parol qayta so‘raladi** (`password.confirm`, Laravel standarti — 3 soat eslab
  qoladi). Mening qarorim: fayl — butun baza (JSHSHIR, yuz rasmlari); o‘g‘irlangan sessiya bilan yuklab
  bo‘lmasin.
- **Har yuklab olish logga yoziladi:** foydalanuvchi id/email, fayl, IP (`App\Http\ClientIp`).
- **Fayl nomi qat’iy tekshiriladi:** faqat `{baza}-YYYY-MM-DD-HHMMSS.sql.gz` naqshi va zaxira papkasidagi fayl;
  boshqasi — 404. Yo‘l orqali chiqish (`../`) imkonsiz.
- **Sana fayl nomidagi vaqtdan** olinadi (mtime emas).
- **Ruxsatlar:** `DB_BACKUP_GROUP` bo‘sh — hozirgidek `0700`/`0600`. Berilsa — har ishga tushishda papka `0750`
  va shu bazaning barcha zaxira fayllari `0640`, guruhi `DB_BACKUP_GROUP` (eski nusxalar ham). Vaqtinchalik
  `.cnf` va xom `.sql` baribir `0600`. `chgrp` bajarilmasa (guruh yo‘q, Windows) zaxira buzilmaydi — faqat
  ogohlantirish logi.
- **Papka o‘qilmasa** (mavjud, lekin veb-foydalanuvchi o‘qiy olmaydi) sahifada ogohlantirish: "Zaxira papkasini
  o‘qib bo‘lmadi — serverda DB_BACKUP_GROUP sozlang". Papka yo‘q bo‘lsa — oddiy bo‘sh holat.
- **Qatlam:** `BackupController` (index, download) → `DatabaseBackupService` (`backups()`, `downloadPath()`;
  fayl naqshi aylantirish bilan umumiy). Sahifa `Admin/Backups/Index`: `PageHeader` + Ant `Table`, yuklab olish
  — `Button href` (eksportlardagidek), ikonka `@ant-design/icons` dan. `routes.js` ga ikkala marshrut.

## Testlar

- Super-admin sahifani ko‘radi: faqat shu bazaning fayllari, yangisi birinchi, hajmi va sanasi bilan.
- Super-admin bo‘lmagan (hamma ruxsatlari bo‘lsa ham) — 403; `can.backups` false.
- Yuklab olish parol tasdiqlanmagan sessiyada `password.confirm` ga yo‘naltiradi; tasdiqlangan sessiyada fayl
  qaytadi va log yoziladi.
- Noto‘g‘ri nom, boshqa baza fayli, mavjud bo‘lmagan fayl — 404.
- Papka o‘qilmasa `unreadable` belgisi (Windows'da o‘tkazib yuboriladi).
- `DB_BACKUP_GROUP` berilsa fayl `0640`, papka `0750` (Windows'da o‘tkazib yuboriladi).
