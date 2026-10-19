# Tashrif statistikasi (zal ekrani) + xodim davlati

Figma: `Innoweek` → freym `hh` (1:1431), "INNOWEEK 2026 · Tashrif statistikasi", 1920×1080.

## Maqsad

Zal ekranida ko‘rsatiladigan alohida, to‘liq ekranli jonli statistika sahifasi: bugun kim, qancha kirdi/chiqdi,
hozir ichkarida qancha, mahalliy/xorijiy, jins va guruh (OAV, Homiy, Ishtirokchi…) kesimida, hamda oxirgi kirgan odam.
Mahalliy/xorijiy hisobi uchun xodimga **davlat** maydoni qo‘shiladi.

## 1. Xodim davlati (`employees.country`)

- Migration: `country` `char(2)`, `NOT NULL`, default `'UZ'`, `gender` dan keyin. Mavjud xodimlar va qurilmadan
  import qilinadiganlar (`EmployeePullService`) avtomatik `UZ` bo‘ladi — import kodiga tegilmaydi.
- `config/countries.php` — `ISO 3166-1 alpha-2 => o‘zbekcha nom` (yagona manba), ICU/CLDR dan bir marta
  generatsiya qilingan; O‘zbekiston birinchi, qolgani o‘zbek alifbosi bo‘yicha. (Brauzer `Intl` dan voz kechildi:
  ilova ichidagi Chromium o‘zbek ICU ma’lumotisiz inglizcha nom qaytardi.)
- `Employee`: `fillable` ga `country`; `public const LOCAL_COUNTRY = 'UZ'`; `isForeign(): bool`.
- `EmployeeFactory`: `'country' => 'UZ'`.
- `StoreEmployeeRequest` / `UpdateEmployeeRequest`: `country` — `sometimes`, `required`, `string`,
  `Rule::in(config('countries'))`; xabarlar o‘zbekcha ("Davlatni tanlang.", "Davlat noto‘g‘ri."). Yuborilmasa
  saqlashda `UZ` (DB default), yangilashda o‘zgarmaydi — davlatsiz eski so‘rovlar buzilmaydi; forma har doim yuboradi.
- `EmployeeController@create/edit`: `countries` prop — `[{value, label}]` (`config('countries')` tartibida).
- `Employee::countryName()` va `EmployeeResource` `country_name` — nom serverdan keladi.
- `Admin/Employees/Form.jsx`: "Davlat" qidiruvli `Select`, standart `UZ`; qidiruv apostrof turlarini e’tiborsiz qoldiradi.
- `Admin/Employees/Show.jsx`: "Davlat" qatori (`country_name`).
- Qilinmaydi: bayroqlar (Windows emoji bayroq ko‘rsatmaydi), Excel eksportiga ustun, ro‘yxat filtri.

## 2. Sahifa va marshrut

- `GET /admin/live/stats` → `LiveMonitorController@stats`, nomi `admin.live.stats`,
  middleware `permission:` + `menu_permissions.resources.live` ("Jonli kuzatuv") — yangi huquq yo‘q.
- `resources/js/routes.js`: `'admin.live.stats': () => '/admin/live/stats'`.
- `resources/js/config/adminNav.js`: "Jonli kuzatuv" yonida "Tashrif statistikasi" (`can.live`).
- Jonli monitor sarlavhasidagi asboblar qatoriga statistika sahifasiga havola (`BarChartOutlined`).
- Inertia: `Admin/Live/Stats.jsx`, `useInertiaPoll(['stats'], 5000)`.
- Uslub: `resources/css/visit-stats.css` — jonli monitor tokenlari va masshtablash (`font-size: min(0.8333vw, 1.4815vh)`,
  1em = 16px @1920×1080) bilan bir xil; to‘q, admin temasidan mustaqil.

## 3. Ma’lumot (`App\Services\VisitStatsService::today()`)

Barcha so‘rovlar `Employee`/`Report`/`Group` global scope (bo‘lim cheklovi) orqali o‘tadi. "Bugun" =
`now()->startOfDay()`..`endOfDay()`. "Odam" = takrorlanmas `employee_id` (o‘tish emas).

| Kalit | Hisob |
|-------|-------|
| `registered` | `Employee` soni |
| `registered_today` | bugun yaratilgan xodimlar |
| `entered` / `entered_last_hour` | bugun / so‘nggi 60 daqiqada **kirish** qilgan odamlar |
| `exited` / `exited_last_hour` | bugun / so‘nggi 60 daqiqada **chiqish** qilgan odamlar |
| `inside` | bugungi **oxirgi** o‘tishi kirish bo‘lgan odamlar (`occurred_at` bo‘yicha, teng bo‘lsa katta `id`) |
| `inside_male` / `inside_female` | `inside` jins bo‘yicha |
| `local` / `foreign` | bugun kirganlar: `country = 'UZ'` / boshqa |
| `male` / `female` | bugun kirganlar jins bo‘yicha |
| `groups` | `LiveMonitorService` dagi bilan bir xil: `[{id, name, entered}]` (barcha guruhlar, 0 bilan) |
| `last_arrival` | bugungi oxirgi **kirish**: `employee_name`, `country`, `position`, `department_name`, `device_name`, `time`; yo‘q bo‘lsa `null` |

Guruh hisobi takrorlanmasligi uchun `LiveMonitorService::enteredByGroup()` `public` qilinadi;
`VisitStatsService` `LiveMonitorService` ni konstruktor orqali oladi va shu metodni chaqiradi.

Foizlar frontendda hisoblanadi (`entered` ga nisbatan, 0 ga bo‘linmaydi).

## 4. Sahifa tarkibi (Figma `hh`)

1. Sarlavha: brend belgisi (APP_NAME bosh harflari), "{APP_NAME} · Tashrif statistikasi",
   "Real vaqt rejimida yangilanadi · Live attendance statistics", "JONLI · LIVE", soat va sana (jonli monitordagi `Clock`).
2. KPI (4 ta): Ro‘yxatdan o‘tganlar (oq, "Bugun +N ta yangi"), Bugun kirganlar (yashil, "So‘nggi 1 soatda +N"),
   Hozir ichkarida (ko‘k), Bugun chiqqanlar (qizil, "So‘nggi 1 soatda +N").
3. Panellar: Mahalliy va xorijiy (CSS `conic-gradient` halqa + 2 qator), Erkaklar va ayollar (2 karta + ulush chizig‘i +
   "Hozir ichkarida: Erkak · Ayol"), Toifalar bo‘yicha (ulush chizig‘i + guruh qatorlari, rang navbati jonli monitordagidek).
4. "So‘nggi kirgan · Last arrival" lentasi: ism, davlat nomi · lavozim/bo‘lim, turniket · vaqt.
   Bo‘sh holat: "Bugun hali kirish qayd etilmagan."

Guruhlar 3 tadan ko‘p bo‘lsa panel ichida sig‘maganlari yashiriladi (jonli monitordagi wrap usuli).

## 5. Test

- `tests/Feature/Admin/VisitStatsTest.php`: huquqsiz 403; har bir kalit (ayniqsa `inside` — kirdi→chiqdi→kirdi,
  `local/foreign`, `*_last_hour`, `registered_today`, `last_arrival`); bo‘lim cheklovi; bo‘sh kun.
- `EmployeeCountryTest`: `country` saqlanadi, noto‘g‘ri kod rad etiladi, berilmasa `UZ` (migration default),
  yangilashda berilmasa o‘zgarmaydi — mavjud yiqilayotgan testlarga tegilmaydi.
- `LiveMonitorTest`: guruh hisobi umumiy joyga ko‘chgandan keyin ham o‘tadi.
- Brauzerda 1920×1080 da Figma bilan solishtirish.

## Qamrovdan tashqari

Kirish monitoriga Mahalliy/Xorijiy plitkalari va davlat chipi, bayroqlar, "+1 hozirgina" animatsiyasi, davlat bo‘yicha
filtr va eksport.
