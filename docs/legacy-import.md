# Eski Biocontrol tizimidan ko‘chirish

Bir martalik ish: eski tizimning phpMyAdmin dump'idan **bo‘limlar, guruhlar, xodimlar va yuz rasmlari**
ko‘chiriladi. Kelgan-ketganlar (`reports_*` jadvallari), barmoq izlari va eski qurilma bog‘lanishlari
ko‘chirilmaydi — yangi tizim kelgan-ketganni terminallardan o‘zi oladi.

Buyruq: `php artisan legacy:import-employees` (`app/Services/Legacy/LegacyEmployeeImportService.php`).

## Qoidalar

- Faqat `--departments` da berilgan eski bo‘limlar ko‘chadi. Bo‘lim yangi tizimda nomi bo‘yicha topiladi,
  bo‘lmasa yaratiladi.
- Eski daraxtda har bo‘lim tepasida shu nomli guruh bor (masalan «Agentlik» bo‘limida «Agentlik» guruhi) —
  u tashlab yuboriladi, ichidagi guruhlar bo‘lim ostiga chiqadi. Tepa guruhda turgan xodim guruhsiz qoladi.
- Guruhi boshqa bo‘lim daraxtida yoki eski bazada yo‘q xodim o‘z bo‘limida, guruhsiz qoladi
  (`-v` bilan ro‘yxati chiqadi — keyin qo‘lda guruhga qo‘yiladi).
- Xodim JSHSHIR bo‘yicha topiladi. Bor bo‘lsa (masalan, terminaldan «Qurilmadan» bo‘limiga tortilgan bo‘lsa)
  bo‘limi, guruhi, ismi va muddati eski tizimdagidek qilinadi; lavozim eski tizimda to‘ldirilgan bo‘lsagina
  yoziladi; jadvali o‘zgarmaydi.
- Yuz rasmi faqat xodimda rasm bo‘lmasa qo‘shiladi; buzilgan rasm o‘tkazib yuboriladi.
- Eski jadval yangi tizimdagi umumiy jadvalga vaqti bo‘yicha (09:00–18:00 → «Standart») bog‘lanadi.
- Muddati allaqachon tugagan xodimlar «xabar berilgan» deb belgilanadi — Telegram'ga eski xabar ketmaydi.
- Terminallarga hech narsa yozilmaydi.
- Hammasi bitta tranzaksiyada: xato bo‘lsa hech narsa yozilmaydi. Qayta ishga tushirilsa takror yaratmaydi.

## Serverda bajarish

1. **Zaxira nusxa** (majburiy):

   ```bash
   cd /var/www/biocontrol && mysqldump -u root -p "$(grep ^DB_DATABASE= .env | cut -d= -f2)" > ~/backup-before-legacy-$(date +%F).sql
   ```

2. Dump'ni serverga yuklang (masalan WinSCP bilan) `/tmp/biocontrol_legacy.sql` nomi bilan.

3. Vaqtinchalik bazaga yuklang va ilova foydalanuvchisiga o‘qish huquqini bering
   (`<db_user>` — `.env` dagi `DB_USERNAME`):

   ```bash
   mysql -u root -p -e "CREATE DATABASE biocontrol_legacy CHARACTER SET utf8mb4; GRANT SELECT ON biocontrol_legacy.* TO '<db_user>'@'localhost';"
   mysql -u root -p biocontrol_legacy < /tmp/biocontrol_legacy.sql
   ```

4. Kodni yangilang: `git pull` (migratsiya yo‘q).

5. Sinov — hech narsa yozilmaydi, nima bo‘lishi va ogohlantirishlar chiqadi:

   ```bash
   php artisan legacy:import-employees --departments=5,7,8,14 --dry-run -v
   ```

6. Natija to‘g‘ri bo‘lsa — haqiqatda:

   ```bash
   php artisan legacy:import-employees --departments=5,7,8,14
   ```

7. Eski ma’lumotni serverdan o‘chiring (shaxsiy ma’lumot — JSHSHIR va rasmlar):

   ```bash
   mysql -u root -p -e "DROP DATABASE biocontrol_legacy;" && rm /tmp/biocontrol_legacy.sql
   ```

8. Xodimlarni terminallarga **Sinxronizatsiya** sahifasidan yuboring.

## 2026-11-11 dump'i

`biocontrol (1).sql` (eski tizim, MariaDB 10.4) — saqlanadigan bo‘limlar: 5 Agentlik, 7 Fond,
8 Ilmiy-texnik axborot markazi, 14 Kasbiy ta’lim agentligi. Olib tashlanganlar: 6 Vazirlik, 9 Milliy ofis,
10 OTFIV, 11 Milliy Gvardiya, 12 GRP, 13 Ta’lim sifatini nazorat qilish agentligi.

Lokal sinov (`--dry-run`): 4 bo‘lim, 84 guruh (+5 tepa guruh tashlandi), 289 xodim, 289 yuz rasmi,
15 tasining muddati tugagan, 23 ogohlantirish (11 tasining guruhi eski bazada yo‘q, 12 tasining guruhi
boshqa bo‘limda).
