# Tadbir mehmonlari (Innoweek ishtirokchilari)

## Maqsad

Innoweek sayti ishtirokchilari (`users` + `numbers`) qurilmalardan kelgan o‘tishlarni odamga bog‘lash uchun
bazada bo‘lishi kerak, lekin ular **faqat** `/admin/live` (Jonli kuzatuv) va `/admin/live/stats`
(Tashrif statistikasi) sahifalarida ko‘rinadi. Xodimlar, davomat, hisobotlar, sinxronizatsiya, izohlar,
bosh sahifa va Telegram hisoblariga aralashmaydi.

## Ma’lumot

- `employees.is_visitor` — `boolean`, default `false`, indeks. Mehmon — oddiy `Employee` yozuvi + shu belgi
  (karta, ism, jins, davlat, guruh qurilma eventlarini bog‘lash va statistika uchun kerak).
- `php artisan innoweek:import {path}` mehmonlarni `is_visitor = true` bilan yozadi. Shu kartali **oddiy xodim**
  bo‘lsa — o‘zgartirilmaydi, mehmon qilib yuklanmaydi (buyruq sonini ogohlantiradi).

## Ko‘rinish qoidasi (global scope)

- `Employee` — `ExcludesVisitorsScope`: `is_visitor = false`. `Employee::query()->withVisitors()` uni olib tashlaydi.
- `Report` — `ExcludesVisitorReportsScope`: `employee_id IS NULL OR employee_id NOT IN (mehmonlar)`.
  `Report::query()->withVisitors()` uni olib tashlaydi.
- Bo‘lim cheklovi (`BelongsToDepartmentScope`) ikkala holatda ham qoladi.
- Standart holat — yashirish: yangi sahifa yoki hisob avtomatik ravishda mehmonlarsiz bo‘ladi.

`withVisitors()` faqat quyidagilarda:

| Joy | Nima uchun |
|-----|-----------|
| `LiveMonitorService`, `VisitStatsService` | ikki jonli sahifa: o‘tishlar, kirgan/ichkarida, jins, davlat, guruh, oxirgi kelgan |
| `LiveMonitorController@face` | mehmon yuz rasmi (route model binding mehmonni topmaydi — id bo‘yicha qidiriladi) |
| `DepartmentRepository` / `ScheduleRepository::hasEmployees()` | faqat mehmonli bo‘lim/jadval o‘chirilib, FK xatosi bermasin |

Event qabul qilish (`ReportService`) va import/tortish servislari `withoutGlobalScopes()` bilan ishlaydi —
mehmon o‘tishi to‘g‘ri `employee_id` bilan yoziladi. `EmployeePullService` mehmon kartasini topsa — o‘tkazib
yuboradi (mehmon oddiy xodimga aylanmaydi).

Bosh sahifadagi "So‘nggi eventlar" (`access_controller_events`, xom) mehmon kartalarini `whereNotExists` bilan chiqarib tashlaydi.

## Qilinmaydi

- Mehmon bo‘limi ("Innoweek 2026") va guruhlari (Ishtirokchi, Kompaniya, Tashkilotchi) Bo‘limlar/Guruhlar
  sahifalari va filtrlarida ko‘rinib qoladi — ular tashkiliy yozuvlar, ichida xodim ko‘rinmaydi.
- Mehmonni admin paneldan tahrirlash/o‘chirish (sahifalar 404) — ma’lumot manbai Innoweek dump.
- `employee_id` siz (xodim aniqlanmagan) o‘tishlar avvalgidek hisobotlarda qoladi.
