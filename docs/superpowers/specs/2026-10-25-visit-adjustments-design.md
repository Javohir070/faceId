# Statistikaga qo‘lda kirish qo‘shish

## Maqsad

Admin tanlangan kun uchun "kirganlar" soniga qo‘lda son qo‘sha oladi (masalan, +1000, keyin yana +500) —
turniket/Face ID dan o‘tmagan oqim yoki boshlang‘ich son. Qo‘shilgan son **jonli sahifalarda**
(`/admin/live`, `/admin/live/stats`) hamma bog‘liq ko‘rsatkichlarga mos tushadi, statistika ichida
yig‘indilar buzilmaydi.

## Qoida

Har bir qo‘shish (yozuv) uchun:

| Ko‘rsatkich | Qo‘shiladi |
|---|---|
| Bugun kirganlar (`entered`) | N |
| Erkaklar / Ayollar | `round(N × 0.7)` / qolgani (70% / 30%) |
| Mahalliy (`local`) | N (xorijiyga qo‘shilmaydi) |
| Guruh | N — tanlangan guruhga (standart: "Ishtirokchi"); Homiy, OAV ga qo‘shilmaydi |
| So‘nggi 1 soatda kirganlar | N, agar yozuv so‘nggi 60 daqiqada qo‘shilgan bo‘lsa |

- Qo‘shishlar **faqat o‘z kuni** uchun amal qiladi (`date`); ertasi kuni hisob haqiqiy sondan boshlanadi.
- Bir kunga bir nechta qo‘shish mumkin — yig‘indisi olinadi; noto‘g‘ri yozuv o‘chiriladi.
- Erkak/ayol bo‘linishi yozuv yaratilganda saqlanadi (keyin nisbat o‘zgarsa ham tarix o‘zgarmaydi).
- O‘zgarmaydi: ro‘yxatdan o‘tganlar, hozir ichkarida, chiqqanlar, oxirgi kelgan, o‘tishlar ro‘yxati,
  hisobotlar/davomat (u yerlarda qo‘shish ko‘rinmaydi).

## Ma’lumot

`visit_adjustments`: `date` (indeks), `department_id` (guruh bo‘limidan; `BelongsToDepartmentScope`),
`group_id` (null on delete), `entered`, `male`, `female`, `note`, `user_id` (kim qo‘shdi), timestamps.

## Sahifa va huquq

- Yangi huquq **"Statistika qo‘shish"** (`menu_permissions.resources.live-adjust`) — wiring ritual bo‘yicha
  (config, routes, `can`, sidebar, `routes.js`, permission migratsiyasi).
- `GET /admin/live/adjustments?date=` → `Admin/Live/Adjustments`: forma (sana, son, guruh, izoh,
  70/30 oldindan ko‘rinishi) va shu kun qo‘shishlari ro‘yxati (jami bilan, o‘chirish).
- `POST /admin/live/adjustments`, `DELETE /admin/live/adjustments/{adjustment}`.
- Qatlamlar: `VisitAdjustmentController` → `VisitAdjustmentService` → `VisitAdjustmentRepositoryInterface`.

## Hisobga qo‘shish

`VisitAdjustmentService::totals($day)` → `{entered, male, female, local, groups: {group_id: n}}`,
`enteredBetween($from, $to)` → so‘nggi soat uchun. `LiveMonitorService` (`todayStats`, `enteredByOrigin`,
`enteredByGroup`) va `VisitStatsService::today()` shu qiymatlarni haqiqiy sonlarga qo‘shadi.
