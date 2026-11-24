# Jadval: kechikishga ruxsat (dizayn)

## Maqsad

Har ish jadvaliga **kechikishga ruxsat (daqiqa)** qo‘shiladi. Kirish 09:00 va ruxsat 15 bo‘lsa, 09:15:00 gacha
(shu daqiqa ham) kelgan xodim kechikkan hisoblanmaydi: na Hisobotlarda, na Davomatda, na Telegram kunlik
hisobotida.

- **Maqsad emas:** erta ketish uchun ruxsat, xodim bo‘yicha alohida ruxsat, eski API (`/api/legacy`) dagi
  qat’iy chegaralar.
- **Anti-maqsad:** mavjud jadvallarning natijasi o‘zgarib qolishi (standart 0), tungi smena hisobining buzilishi.

## Foydalanuvchi qarorlari (2026-11-24)

- Ruxsat faqat **chegara**. Ruxsatdan keyin kelgan xodimning kechikishi **kirish vaqtidan** hisoblanadi:
  09:00 + 15 daqiqa ruxsat, 09:20 da kelgan → kechikish **20 daqiqa** (5 emas).

## Dizayn

**Ma’lumot:** `schedules.late_grace_minutes` — `unsignedSmallInteger`, default `0`. Migratsiya mavjud
qatorlarni 0 qiladi, shuning uchun hozirgi hisobotlar o‘zgarmaydi.

**Forma:** Admin → Jadvallar drawer’ida “Kechikishga ruxsat (daqiqa)” raqam maydoni, ixtiyoriy, 0–120.
Bo‘sh qoldirilsa 0 saqlanadi. Ro‘yxatda “Kirish vaqti” ustunida 0 dan katta bo‘lsa “+15 daq” belgisi.
`lang/uz/validation.php` `attributes` ga `late_grace_minutes`.

**Hisob:** `AttendanceCalculator::isLate(firstEntry, enterTime, nightShift, graceMinutes)` —
`lateSeconds() > graceMinutes * 60`. `lateSeconds()` o‘zgarmaydi (kirish vaqtidan hisoblaydi), tungi smena
siljitishi (`shiftMoment()`) shu yerda qoladi.

| Joy | O‘zgarish |
|---|---|
| `ReportQueryService::buildLateRows()` (Hisobotlar → Kechikkan) | `isLate()` bo‘lmasa qator chiqmaydi; “Kechikish” ustuni `lateSeconds()` (to‘liq) |
| `AttendanceGridService::cell()` (Davomat) | `late` holati `isLate()` bilan; `scheduleInfo()` ga `grace` |
| `TelegramReportService::digestForDate()` | chegara `kirish + ruxsat` |

Present, Individual, WorkTime, Absent turlari kechikishni hisoblamaydi — o‘zgarmaydi.

## Testlar

- Forma: ruxsat saqlanadi, bo‘sh → 0, manfiy/120 dan katta → o‘zbekcha xato; drawer yozuvida maydon bor.
- Hisobot: 15 daqiqa ruxsat bilan 09:15 kelgan chiqmaydi, 09:20 kelgan `00:20:00` bilan chiqadi;
  tungi smena (20:00, ruxsat 10) 20:08 → kechikmagan, 20:12 → kechikkan.
- Davomat: 09:10 → `on_time`, 09:20 → `late`.
- Telegram: 09:10 kechikkanlar ro‘yxatida yo‘q, 09:20 bor.
