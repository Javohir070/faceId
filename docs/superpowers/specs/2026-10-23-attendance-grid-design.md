# Davomat jadvali (barcha xodimlar)

Namuna: foydalanuvchi bergan ikki skrinshot — "Mening davomatim" sahifasi (kunlik katakli jadval, yuqorida statistika
kartochkalari, kun bosilganda o‘ngdan ochiladigan tafsilot oynasi):
`assets/2026-10-23-attendance-grid.png`, `assets/2026-10-23-attendance-day-drawer.png`. Figma: `E-idora` → `Ekranlar`
→ "Davomat" bo‘limi. Bizda bu bitta xodim emas, **barcha xodimlar**
uchun admin sahifasi bo‘ladi.

## Maqsad va nima uchun

Admin bir qarashda kim qaysi kuni o‘z vaqtida keldi, kim kechikdi, kim kelmadi, kim sababli ekanini ko‘rishi kerak.
Hozirgi "Hisobotlar" sahifasi har bir turni (kechikkanlar, kelmaganlar, ishlagan vaqt…) alohida ro‘yxat qilib beradi.
Yangi sahifa esa hammasini bitta jadvalda — xodim × kun — rang bilan ko‘rsatadi va kunni bosib, o‘sha kungi
kirish-chiqishlar hamda izohlarni ko‘rish va izoh qo‘shish imkonini beradi.

**Muvaffaqiyat:** skrinshotdagi ko‘rinish va hisob natijalari bizning ma’lumotlarimizda chiqadi (pastdagi
"Tekshiruv misoli"ga qarang), 750 ga yaqin xodim bilan sahifa sekinlashmaydi.

## Foydalanuvchi bilan kelishilgan qarorlar

- Sahifa **barcha xodimlarni** ko‘rsatadi (bo‘lim bo‘yicha guruhlab). Bo‘lim bilan cheklangan foydalanuvchi faqat o‘z
  bo‘limini ko‘radi (mavjud `BelongsToDepartmentScope` orqali).
- **"Ariza" = mavjud izoh (`notes`, "Ish vaqtida ruxsatlar")**. Uni admin kiritadi; xodim o‘zi yubormaydi,
  tasdiqlash jarayoni yo‘q.
- Izoh bor kun — **"Sababli"**, izoh turi qanday bo‘lishidan qat’i nazar.
- **Ish kunlari — dushanba–juma, hamma uchun.** Shanba va yakshanba jadvalda ko‘rsatilmaydi va hech qayerda
  hisoblanmaydi.
- **Tushlik — har kuni 1 soat**, ishlagan vaqtdan ayiriladi (rejadan ham).
- Holatlar va hisoblar quyidagi "Qoidalar" bo‘limida — foydalanuvchi ularni tasdiqlagan.

## Maqsad emas / qilinmaydi

- Xodimning o‘zi tizimga kirib o‘z davomatini ko‘rishi (foydalanuvchi ↔ xodim bog‘lanishi yo‘q, kerak emas).
- Ariza yuborish/tasdiqlash oqimi, ariza holatlari (kutilmoqda/rad etildi).
- Bayram kunlari kalendari, shanba ishlaydigan jadvallar, xodimga xos ish kunlari.
- Kechikishga daqiqa chegirmasi (grace) — hozirgi hisobotlardagidek kirish vaqtidan 1 soniya keyin ham kech.
- Mavjud "Hisobotlar" sahifasini o‘zgartirish yoki almashtirish.

## Anti-maqsadlar (texnik ishlasa ham muvaffaqiyatsizlik)

- Shanba/yakshanba yoki xodim hali ishga kirmagan kunlar "Kelmagan" bo‘lib chiqishi.
- Bugungi kun (hali tugamagan) "Kelmagan" yoki ishlagan vaqtni noto‘g‘ri ko‘paytirishi.
- Sahifaning qurilmalarga biror so‘rov yuborishi. Faqat bazadan o‘qiladi (qurilmalar boshqa tizimlar bilan umumiy).
- Izoh sanasi bir kun siljib ko‘rinishi (qarang: `Note::dt` UTC serializatsiyasi — sanani serverda `Y-m-d` qilib
  bering, frontendda `slice` ga tayanmang).

## Qoidalar

Atamalar: **ish kuni** — tanlangan davrdagi dushanba–juma sanasi. **Xodim-kun** — bitta xodim va bitta ish kuni,
agar sana xodimning amal qilish muddati (`valid_from_dt`..`valid_to_dt`, sana bo‘yicha) ichida bo‘lsa. Muddatdan
tashqari kun katakda kulrang "—" bo‘lib turadi va hech qayerda hisoblanmaydi. Davrda birorta ham amal qilish kuni
bo‘lmagan xodim jadvalga ham, statistikaga ham kirmaydi (quruvchi qarori).

Kunlik o‘tishlar mavjud mantiq bilan guruhlanadi: `AttendanceCalculator` (tungi smena — `shiftMoment()` / `workDate()`),
xodim jadvali (`schedules.enter_time` / `exit_time`), `reports` (`TYPE_ENTRY` / `TYPE_EXIT`). Birinchi kirish = kunning
eng erta kirishi, oxirgi chiqish = eng kech chiqishi (`ReportQueryService::groupPunchesByEmployeeWorkDate` bilan bir xil).

**Katak holati** (ustunlik tartibida):

1. **Kelajak** — sana bugundan keyin: bo‘sh katak.
2. **Sababli** — o‘sha xodim-kunga kamida bitta izoh bor (har qanday tur). Katak oq fon, yashil ramka; vaqtlar
   bo‘lsa ko‘rsatiladi.
3. **Kech kelgan** — birinchi kirish bor va `lateSeconds > 0`. Och sariq fon.
4. **O‘z vaqtida** — birinchi kirish bor va kechikish yo‘q. Och yashil fon.
5. **Kelmagan** — kirish yo‘q va sana bugundan oldin. Och qizil fon, "—".
6. **Bugun, hali kelmagan** — kirish yo‘q va sana = bugun: bo‘sh katak, hech qayerda hisoblanmaydi.

Bugungi katak qo‘shimcha ko‘k ramka oladi; ustun sarlavhasi ham belgilanadi. Katakda yuqorida birinchi kirish (`H:i`),
pastda oxirgi chiqish (`H:i`). Kun oxirgi o‘tishi kirish bo‘lsa (hali ichkarida) — pastda "ishda" (faqat bugun uchun;
o‘tgan kunda chiqish yo‘q bo‘lsa "—").

**Erta ketish** katak rangini o‘zgartirmaydi. U "N marta erta ketgan" soniga qo‘shiladi: oxirgi chiqishi
`earlySeconds > 0` bo‘lgan har bir **yakunlangan** xodim-kun (sababli kunlar ham). Bugun hali ichkarida bo‘lgan
(tushlikka chiqib qaytgan) xodim sanalmaydi — son kun davomida o‘sib-tushmasligi uchun (quruvchi qarori).

**Yakunlangan kun:** sana bugundan oldin, yoki sana = bugun va kunning oxirgi o‘tishi chiqish.

**Ishlagan vaqt** (xodim-kun): yakunlangan kun uchun `max(0, workedSeconds(birinchi kirish, oxirgi chiqish) − 3600)`;
aks holda 0. Xodim qatoridagi "Ishladi" — davrdagi yig‘indisi.

**Reja** (xodim-kun): yakunlangan kun uchun `max(0, jadval davomiyligi − 3600)` (tungi smenada davomiylik yarim tundan
o‘tib hisoblanadi), kelmagan kunlar ham kiradi. Bugun hali tugamagan bo‘lsa, ishlagan ham, reja ham qo‘shilmaydi.

**Statistika kartochkalari** — joriy filtrga mos **barcha** xodimlar bo‘yicha (faqat joriy sahifadagilar emas):

| Kartochka | Qiymat |
|-----------|--------|
| Ish kunlari | `o‘tgan ish kunlari / davrdagi ish kunlari` (sanalar soni, xodim-kun emas; o‘tgan = bugungacha, bugun ham) va "o‘rtacha kelish HH:MM" — birinchi kirishi bor barcha xodim-kunlarning kirish vaqti o‘rtachasi (tungi smena uchun `shiftMoment` qilingan vaqt bilan hisoblanadi) |
| O‘z vaqtida | son, foiz, "N marta erta ketgan" |
| Kech kelgan | son, foiz |
| Sababli | son, foiz |
| Kelmagan | son, foiz |
| Ishladi | jami ishlagan vaqt, ostida "reja: jami reja" |

Foiz = son / (o‘z vaqtida + kech + sababli + kelmagan) × 100, butun songa yaxlitlanadi; maxraj 0 bo‘lsa 0 %.
Vaqt formati: `81 s 24 d` (soat, daqiqa).

## Sahifa

**Marshrut va huquq** (CLAUDE.md dagi "wiring ritual" to‘liq bajariladi):

- Yangi huquq: `config/menu_permissions.php` → `'attendance' => 'Davomat'`; mavjud o‘rnatmalar uchun migratsiya
  (`2026_08_03_161000_add_groups_permission.php` namunasi — `super-admin` ga beriladi).
- `GET /admin/attendance` → `AttendanceController@index`, nomi `admin.attendance.index`.
- `GET /admin/attendance/export` → Excel, nomi `admin.attendance.export`.
- `POST /admin/attendance/notes` → izoh qo‘shish (`StoreNoteRequest`, `NoteService::create`), muvaffaqiyatdan keyin
  **shu sahifaga qaytadi** (`back()` + `success` flash). Bu marshrut qo‘shimcha `permission:Izohlar` talab qiladi.
- `HandleInertiaRequests` dagi `can` xaritasi, `adminNav.js` (Hisobotlar yonida, `ScheduleOutlined`), `routes.js`.
- Qatlamlar: Controller → `AttendanceGridService` (hisob) → mavjud modellar/repozitoriylar. Filtr validatsiyasi
  controllerda `filterRules()` (mavjud uslub).

**Parametrlar** (query): `period` = `week` | `2weeks` | `month` (standart `2weeks`), `date` (langar sana, standart bugun),
`department_id`, `group_id`, `search` (ism yoki karta), `page`, `tab` = `attendance` | `notes`.

- `week` — langar sana tushgan hafta (dushanba–yakshanba). `2weeks` — oldingi hafta + joriy hafta. `month` —
  kalendar oyi. Oldinga/orqaga tugmalari langarni davr uzunligiga suradi.
- Sarlavhadagi oraliq birinchi va oxirgi **ish kuni** bilan yoziladi (masalan `12.10 – 23.10.2026`).

**Ko‘rinish** (skrinshotdagi tuzilma, admin temasi va `PageHeader` / `FlashMessages` kabi umumiy komponentlar bilan):

- Sarlavha "Davomat", ostida "Har katakda: yuqorida kelgan, pastda ketgan vaqt". O‘ngda: ‹ oraliq ›, Hafta / 2 hafta / Oy
  (segmentli tanlov), "Hisobot" (Excel), "Ariza berish" (izoh oynasi; `can.notes` bo‘lmasa yashirin).
- Filtr qatori: qidiruv, bo‘lim, guruh.
- Tablar: **Davomat** | **Arizalar**.
- Statistika kartochkalari (yuqoridagi jadval).
- Jadval: chapda "Xodim" (ism, ostida lavozim), har ish kuni ustuni (sana `dd.mm` va hafta kuni qisqa nomi: Du, Se, Cho,
  Pa, Ju), oxirida "Ishladi". Bo‘lim sarlavha qatori (nom + xodimlar soni, yig‘ib-yoyiladi). Ko‘p ustunda gorizontal
  aylantirish, xodim ustuni qotib turadi. Pastda rang izohi va "Ishladi — tushlik (1 soat) chegirib",
  "Kunni bosing — o‘sha kungi kirish-chiqishlar va arizalar ochiladi."
- Xodimlar sahifalab: 50 tadan, tartib — bo‘lim nomi, so‘ng xodim ismi; sahifa ichida bo‘lim bo‘yicha guruhlanadi
  (`Pagination` komponenti).

**Kun tafsiloti** (katak bosilganda o‘ngdan Drawer; ma’lumot Inertia qisman qayta yuklash bilan, masalan ixtiyoriy
`day` prop + `day_employee_id` / `day_date` parametrlari — aniq usul quruvchiga):

- Sarlavha: "Seshanba, 13-oktabr 2026" va holat belgisi.
- Kelgan, Ketgan, Ishladi ("tushlik (1 soat) chegirib"), Ish vaqti (`09:00–18:00`, "Dushanba–juma").
- "Kirish-chiqishlar": o‘sha ish kunidagi barcha `reports` vaqt bo‘yicha — vaqt, "Kirdi"/"Chiqdi", ostida
  `kategoriya · qurilma nomi` (qurilma yo‘q bo‘lsa "Noma’lum qurilma"). Kirish yashil, chiqish kulrang nuqta.
- "Shu kunga arizalar": izohlar (tur nomi + matn) yoki "Bu kunga ariza berilmagan."
- Pastda "Yopish" va "Shu kun uchun ariza berish" (izoh oynasi, xodim va sana oldindan to‘ldirilgan).

**Izoh oynasi** (Modal): xodim, tur (`NoteType`), sana, matn — mavjud izoh formasi maydonlari bilan bir xil;
`POST /admin/attendance/notes`; saqlangach jadval va ochiq tafsilot yangilanadi.

**Arizalar tabi:** tanlangan davr va filtrlarga mos izohlar ro‘yxati (xodim, tur, sana, matn), tahrirlash/o‘chirish
mavjud izoh marshrutlariga olib boradi. Mavjud `NoteService::paginate` filtrlaridan foydalaniladi.

**Excel** (`maatwebsite/excel`, mavjud `ReportsExport` uslubi): joriy filtr va davrdagi barcha xodimlar (sahifalashsiz):
Bo‘lim, Xodim, Lavozim, har ish kuni ustuni (`08:14–18:46`, sababli/kelmagan uchun matn), Ishladi. Fayl nomi
`davomat_YYYY-MM-DD_YYYY-MM-DD.xlsx`.

## Cheklovlar

- Laravel 12 + Inertia + React (JSX), AntD 6 + Tailwind; ikonalar faqat `@ant-design/icons`; barcha matnlar o‘zbekcha.
- Navigatsiya Inertia `router`/`useForm`; Ziggy yo‘q — `routes.js` ga yozing.
- Ishlash: oy × 750 xodim. Davr uchun `reports` va `notes` bittadan so‘rov bilan olinib, PHP da guruhlanadi
  (`toBase()->cursor()` kabi xotirani tejaydigan usul). Statistika barcha filtrlangan xodimlar bo‘yicha, jadval
  esa faqat joriy sahifa xodimlari uchun yig‘iladi.
- Testlar SQLite da (`tests/TestCase.php`), `Tests\Concerns\InteractsWithPermissions`.

## Tekshiruv misoli (skrinshotdan)

Xodim jadvali 09:00–18:00, davr 12.10–23.10.2026 (ikki hafta, 10 ish kuni), bugun 23.10 (juma). Kunlar (kirish/chiqish):
12.10 08:14/18:46, 13.10 08:51/18:13, 14.10 06:59/18:30, 15.10 07:36/18:47, 16.10 08:13/17:11 (izoh bor),
19.10 10:05/18:15, 20.10 08:12/18:32, 21.10 08:49/18:49, 22.10 06:57/17:17, 23.10 06:34/— (ichkarida).

Kutilgan natija: Ish kunlari **10 / 10**, o‘rtacha kelish **08:03**; O‘z vaqtida **8 (80 %)**, **2 marta erta ketgan**
(16.10 va 22.10); Kech kelgan **1 (10 %)** (19.10); Sababli **1 (10 %)** (16.10); Kelmagan **0 (0 %)**;
Ishladi **81 s 24 d**, reja **72 s 00 d** (9 yakunlangan kun × 8 soat); 23.10 katagida "06:34 / ishda".
Bu misol `AttendanceGridService` testiga aynan kiritiladi.

## Quruvchiga qoldirilganlar

- Servis/DTO tuzilishi, kun tafsilotini qanday yuklash (ixtiyoriy prop yoki alohida GET), React komponentlarga bo‘lish.
- Aniq ranglar — admin temasi tokenlaridan (yorug‘/qorong‘i), skrinshotdagi ma’noga mos.
- Excel bezagi.
