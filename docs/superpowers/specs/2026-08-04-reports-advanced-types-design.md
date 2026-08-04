# Reports — advanced types, filters, Excel

## Maqsad

`/admin/reports` sahifasini eski tizimdagi (`docs/report.php`) hisobotlarga o‘xshatib kengaytirish: 8 tur, datetime filter, tipga mos jadval ustunlari, shu natijani Excel ga yuklash.

## Qamrov

**Kiradi**
- Bitta sahifa: `/admin/reports` (mavjud Inertia UI).
- 8 hisobot turi (pastda).
- Filterlar: xodim, bo‘lim/guruh, qurilma, kategoriya, jadval, tur, `date_from` / `date_to` (datetime).
- Excel: tanlangan turdagi jadval ustunlari bilan bir xil.
- Tungi smena: `schedule.enter_time > schedule.exit_time` (eski `shift_dt` mantiqiga mos).
- Sabab ustunlari: mavjud `notes` (`NoteType`: CameLate, LeftEarly, Absent).

**Qamrovdan tashqari (YAGNI)**
- Parking / raqam (plate) filtri.
- Bayramlar (`holidays`) moduli.
- `rate` ustuni (employees jadvalida yo‘q).
- Alohida URL lar (`/reports/late` va hokazo).
- `docs/excel.php` (SimpleXLSX reader) — ishlatilmaydi; export `maatwebsite/excel` orqali.

## Qarorlar

| Savol | Javob |
|-------|-------|
| Turlar | Barcha 8 tur |
| Bayramlar | Hisobga olinmaydi |
| Tungi smena | Qo‘llab-quvvatlanadi |
| Sana filtri | Datetime |
| Arxitektura | `ReportQueryService` |

## Arxitektura

```
Index.jsx  →  GET /admin/reports
           →  GET /admin/reports/export
                    ↓
             ReportController (yupqa)
                    ↓
             ReportQueryService
                    ↓
             reports + employees + schedules + notes + devices + groups
```

**Backend**
- `App\Enums\AttendanceReportType` — qiymatlar `1..8` + `label()`.
- `App\Services\ReportQueryService`:
  - `columns(?AttendanceReportType $type): array` — UI/Excel headings.
  - `paginate(array $filters, int $perPage)` — Inertia uchun.
  - `forExport(array $filters): iterable` — Excel uchun (paginatsiyasiz).
  - Ichki: tip bo‘yicha query/collection builder; umumiy filter helper.
- `ReportController::index` / `export` filtr validatsiyasini yangilaydi va service ga topshiradi.
- `ReportsExport` tipga qarab headings + map (service dan olingan ustunlar/qatorlar).
- Pull (`/admin/reports/pull`) o‘zgarmaydi.

**Frontend**
- `Admin/Reports/Index.jsx`:
  - Type options: Kirish, Chiqish, Kechikkan, Erta ketgan, Kelgan, Kelmagan, Ish vaqti, Individual.
  - `DatePicker` + `showTime`, format `YYYY-MM-DD HH:mm:ss`.
  - Backend dan kelgan `columns` bo‘yicha dinamik jadval.
  - Excel `<a href>` joriy filterlar bilan (mavjud pattern).

## Hisobot turlari

| type | Nom | Qoida | Ustunlar |
|------|-----|-------|----------|
| bo‘sh / 1 / 2 | Kirish / Chiqish | `reports` loglari; type bo‘lsa filter | №, Ism, Guruh, Lavozim, Qurilma, Tur, Sana-vaqt |
| 3 | Kechikkan | Kunlik birinchi kirish > `enter_time` (+ tungi smena); note type CameLate | №, Ism, Guruh, Lavozim, Sana, Vaqt, Kechikish, Sabab |
| 4 | Erta ketgan | Kunlik oxirgi chiqish < `exit_time`; note type LeftEarly | №, Ism, Guruh, Lavozim, Sana, Vaqt, Erta, Sabab |
| 5 | Kelgan | Oraliqdagi har kun: xodimda shu kun report bor | №, Ism, Guruh, Lavozim, Sana |
| 6 | Kelmagan | Oraliqdagi har kun: report yo‘q; note type Absent | №, Ism, Guruh, Lavozim, Sana, Sabab |
| 7 | Ish vaqti | Kunlik birinchi kirish − oxirgi chiqish; kunlar pivot + jami | №, Ism, Guruh, Lavozim, [kunlar…], Jami |
| 8 | Individual | Kunlik birinchi kirish / oxirgi chiqish | №, Ism, Guruh, Lavozim, Sana, Kirish, Chiqish |

**Umumiy filterlar:** xodim, bo‘lim/guruh, qurilma, kategoriya, jadval, datetime oralig‘i.

**Maxsus qoidalar**
- Type 3, 4, 7, 8: faqat `schedule_id` bor xodimlar.
- Type 5, 6, 7: `date_from` / `date_to` bo‘sh bo‘lsa default **bugungi kun** (`00:00:00` … `23:59:59`).
- Type 5–7: maksimal oraliq **93 kun**; undan oshsa validation xatosi.
- Type 1/2/bo‘sh: bo‘sh sana = cheklovsiz (mavjud behavior), lekin export hajmi uchun amaliy chegara o‘zgarmaydi.
- Bo‘lim scope: mavjud `BelongsToDepartmentScope` saqlanadi.

## API / validatsiya

- `type`: `nullable|integer|in:1,2,3,4,5,6,7,8`
- `date_from`, `date_to`: `nullable|date` (datetime string qabul qilinadi)
- `date_to` `after_or_equal:date_from`
- Response `index`: `reports`, `columns`, `filters`, filter option listlari (mavjud)
- Route nomlari o‘zgarmaydi: `admin.reports.index`, `admin.reports.export`

## Xatoliklar

- Noto‘g‘ri filter → 422.
- Ruxsatsiz → 403 (`permission:reports`).
- Bo‘sh natija → bo‘sh jadval / sarlavhali bo‘sh Excel (xato emas).
- Oraliq > 93 kun (type 5–7) → aniq validation xabari.

## Test

- Feature: har bir type uchun kamida 1 ta happy-path (`ReportPageTest` kengaytiriladi yoki yangi test class).
- Unit: tungi smena uchun kechikish / erta ketish hisobi.
- Export: type 1 va type 3 headings mosligi; `Excel::fake()` pattern.

## Mavjud kod bilan bog‘liqlik

- Hozirgi Excel export va asosiy Kirish/Chiqish filterlari saqlanadi, lekin query logikasi controllerdan `ReportQueryService` ga ko‘chiriladi.
- `Report` modelidagi `TYPE_ENTRY` / `TYPE_EXIT` (1/2) o‘zgarmaydi; advanced turlar filter/report mode, DB `reports.type` emas.
- `Schedule.enter_time` / `exit_time`, `Employee.schedule_id` / `position` / `group_id`, `Note` — tayyor.
)