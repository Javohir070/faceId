# Tarkibiy bo‘linmalar (kim hozir binoda)

Namuna: foydalanuvchi bergan ikki skrinshot (boshqa tizimdan; shaxsiy ma’lumot borligi uchun repoga qo‘shilmadi):

1. **Sahifa.** Sarlavha "Tarkibiy Bo'linmalar", yonida uchta hisoblagich — "Binoda: 57 Nafar", "Binoda Emas: 22 Nafar",
   "Sababsiz kelmaganlar: 25 Nafar" — va o‘ngda filtr tugmalari: Hammasi, Binoda, Binoda Emas, Sabablilar, Sababsiz.
   Ostida 4 ustunli kartochkalar: nomi (uzun bo‘lsa "…" bilan qisqartirilgan), kichik yorliq (BOSHQARMA, BO'LIM…),
   "Jami - 12 Nafar Binoda - 11 Nafar" va xodimlarning dumaloq rasmlari bir qatorda, binodagilar yashil ramkada,
   binoda yo‘qlar qizil ramkada.
2. **Xodim bosilganda** ochiladigan kichik oyna: katta dumaloq rasm, burchagida yashil nuqta, F.I.Sh., lavozim va
   "Kirgan vaqti: 08:52". Skrinshotdagi xona, telefon va mobil telefon qatorlarini foydalanuvchi chizib tashlagan —
   ular kerak emas.

## Maqsad va nima uchun

Rahbar yoki admin sahifani ochganda bir qarashda ko‘rishi kerak: har bir bo‘linmada hozir kim binoda, kim yo‘q, kim
sababli, kim sababsiz kelmagan. Davomat sahifasi kunlar kesimida (xodim × kun) ko‘rsatadi, Hisobotlar — ro‘yxat;
"hozir, shu daqiqada" holatini bo‘linmalar bo‘yicha ko‘rsatadigan sahifa yo‘q.

**Muvaffaqiyat:** skrinshotdagi ko‘rinish bizning ma’lumotlarimizda chiqadi; xodim terminaldan o‘tganidan keyin
ko‘pi bilan ~30 soniyada uning ramkasi rangi o‘zgaradi; bo‘lim bilan cheklangan foydalanuvchi boshqa bo‘lim xodimini
hech qachon ko‘rmaydi.

## Foydalanuvchi bilan kelishilgan qarorlar

Quyidagilarni foydalanuvchi chatda tasdiqlagan ("Hammasi to‘g‘ri"):

- Sidebar’da yangi **"Tarkibiy bo'linmalar"** menyusi, alohida ruxsat bilan; ruxsat `user` roliga ham beriladi.
- **`user` rolidagilar faqat o‘z bo‘limini ko‘radi** (foydalanuvchi alohida ta’kidlagan): faqat o‘z bo‘limining
  guruhlari, xodimlari va sanoqlari. `department_id` bo‘sh foydalanuvchi hech narsa ko‘rmaydi (bo‘sh holat).
  Bu `super-admin` dan boshqa har qanday rolga tegishli (`User::isDepartmentScoped()`); super-admin hamma bo‘limni
  ko‘radi. Cheklov `Group` / `Employee` / `Report` / `Note` global scope’lari orqali ishlaydi —
  `withoutGlobalScopes()` bu sahifada ishlatilmaydi.
- **Kartochka = Guruh** (`groups`). Guruhga tegishli bo‘lmagan xodimlar oxirgi **"Guruhsiz"** kartochkasida.
- Kartochkada: nomi, "Jami — N nafar · Binoda — M nafar", xodimlar rasmi — **yashil ramka = binoda, qizil = binoda
  emas**. Rasmi yo‘q xodimda bosh harflar.
- Kartochka yorlig‘ida **bo‘lim nomi** (Agentlik, Fond…) — bizda BOSHQARMA/BO'LIM kabi tur maydoni yo‘q.
- Holatlar **faqat bugun uchun** (qoidalar pastda). Muddati tugagan xodimlar umuman chiqmaydi.
- Tepada filtr tugmalari, har birining yonida soni (Hammasi / Binoda / Binoda emas / Sabablilar / Sababsiz).
  Alohida uchta hisoblagich kartochkasi avval qilingan edi, foydalanuvchi ko‘rib olib tashlattirdi — sonlar
  filtr tugmalarida yetarli. Filtr tanlansa kartochkalarda faqat shu xodimlar qoladi, bo‘sh kartochkalar
  yashiriladi.
- Xodim bosilganda kichik oyna: rasm, holat nuqtasi, F.I.Sh., lavozim, guruh, **Kirgan vaqti** (bugungi birinchi
  kirish), chiqib ketgan bo‘lsa **Chiqqan vaqti**, izoh bo‘lsa uning turi (Kechikdi / Erta ketdi / Kelmadi),
  **"Profilni ochish"** havolasi. Xona, telefon, email yo‘q (bizda bunday ma’lumot ham yo‘q).
- Sahifa **har 30 soniyada** o‘zi yangilanadi (Bosh sahifadagidek).
- **Kartochka bosilsa o‘ngdan guruh paneli** ochiladi (foydalanuvchi keyin so‘radi: "ma’lumotlar ko‘proq va
  chiroyliroq"): Binoda / Binoda emas / Sababli / Sababsiz sonlari, "Hozir binodada N / M · %" chizig‘i, qidiruv,
  panel ichidagi filtr va xodimlar ro‘yxati (rasm, ism, lavozim, Kirdi/Chiqdi vaqti, holat, sabab). Ma’lumot
  sahifadagi `board` dan olinadi — alohida so‘rov yo‘q, 30 soniyalik yangilanish bilan birga yangilanadi.
  Rasm bosilsa panel emas, xodim oynasi ochiladi. Har rasmda rang bilan birga ✓/✕ belgisi bor (DESIGN.md: rang
  yolg‘iz qolmaydi).
- **Sabab ko‘zga tashlanadi** (foydalanuvchi so‘radi): izohning turi va matni (`notes: [{type, text}]`, matn izohda
  majburiy) to‘q sariq blokda — panelda, xodim oynasida; panelda "Sababli" yorlig‘i, rasmdagi belgi to‘q sariq
  hujjat belgisi. To‘q sariq — yashil (binoda) va qizil (sababsiz) bilan adashmasligi uchun. Panel qidiruvi sabab
  matnini ham qidiradi.

## Maqsad emas / qilinmaydi

- Guruhlarga tur (Boshqarma / Bo‘lim / Maslahatchi…) maydoni qo‘shish.
- Xona, telefon, email kabi yangi xodim maydonlari.
- O‘tgan kunlarni ko‘rish (sana tanlash). Sahifa faqat "hozir"ni ko‘rsatadi; o‘tgan kunlar uchun Davomat bor.
- Shu sahifadan izoh qo‘shish yoki xodimni tahrirlash.
- Telegram xabarlari, Excel eksport.

## Anti-maqsadlar (texnik ishlasa ham muvaffaqiyatsizlik)

- Bo‘lim bilan cheklangan foydalanuvchi boshqa bo‘lim guruhi yoki xodimini ko‘rishi (sanoqlarda ham).
- Sahifaning qurilmalarga so‘rov yuborishi. Faqat bazadan o‘qiladi (terminallar boshqa tizimlar bilan umumiy).
- Tungi smenadagi xodim yarim tundan keyin "binoda emas" bo‘lib qolishi, holbuki u ichkarida.
- Shanba/yakshanba kuni hamma "sababsiz kelmagan" bo‘lib chiqishi.
- Bu sahifadagi "binoda" Davomat sahifasidagi bugungi "ichkarida" belgisidan farq qilishi.
- Sahifadagi JSHSHIR (`employees.card`) — u shaxsiy ma’lumot, sahifaga umuman yuborilmaydi.

## Qoidalar

**Kim hisoblanadi.** Joriy foydalanuvchi ko‘ra oladigan (global scope’lar ishlagan holda), amal qilish muddati hozir
ichida bo‘lgan xodimlar: `valid_from_dt <= now` va `valid_to_dt >= now` (`Employee::validity()` dagi `active` va
`expiring` — ikkalasi ham kiradi). Muddati tugagan va hali boshlanmagan xodimlar chiqmaydi.

**Bugungi ish kuni** har xodim uchun Davomat jadvalidagidek: `AttendanceCalculator::workDate(now, $night)` — tungi
smenali jadvalda vaqt 12 soat siljitiladi. Xodimning bugungi o‘tishlari — `reports` dagi shu ish kuniga tushadigan
yozuvlar (`workDate(occurred_at, $night)` bo‘yicha). Jadvali yo‘q xodim kunduzgi deb olinadi.

**Holatlar** (har xodim bitta "binoda / binoda emas" holatiga va ixtiyoriy "sababli / sababsiz" belgisiga ega):

| Holat | Qoida |
|-------|-------|
| **Binoda** (yashil) | bugungi ish kunidagi eng oxirgi o‘tishi — kirish (`TYPE_ENTRY`) |
| **Binoda emas** (qizil) | qolgan hamma: bugun o‘tishi yo‘q yoki oxirgisi chiqish |
| **Sababli** | bugungi ish kuni sanasiga izoh (`notes.dt`) bor, turi qanday bo‘lishidan qat’i nazar |
| **Sababsiz kelmagan** | bugun birorta ham kirishi yo‘q **va** izohi yo‘q **va** bugun dushanba–juma |

- Binoda + Binoda emas = jami ko‘rinadigan xodimlar. **"Binoda emas" ga bugun umuman kelmaganlar ham kiradi** —
  foydalanuvchi tekshiruvdan keyin shuni tanladi (namunadagi tizimda kelmaganlar alohida sanalardi, bizda yo‘q).
  Shuning uchun "Sababsiz" har doim "Binoda emas" ning bir qismi.
- Sababli xodim binoda ham bo‘lishi mumkin (masalan "Kechikdi" izohi bilan kelgan) — filtrda ikkalasida ham chiqadi.
- Shanba va yakshanba "Sababsiz" hisoblagichi 0, filtr bo‘sh ro‘yxat beradi. Binoda / Binoda emas odatdagidek ishlaydi.
- "Sababsiz kelmagan" ish boshlanish vaqtiga qaramaydi: kun boshida hali kelmaganlar ham shu yerda (foydalanuvchiga
  aytilgan ta’rif — "bugun umuman kelmagan va izohi yo‘q").

**Popover vaqtlari:** Kirgan vaqti = bugungi ish kunidagi eng erta kirish (`H:i`). Chiqqan vaqti = eng kech chiqish,
faqat xodim hozir binoda emas va bugun kirgan bo‘lsa ko‘rsatiladi. Izoh bo‘lsa uning `NoteType::label()`.

## Kartochkalar va tartib

- Har bir guruh — alohida kartochka, faqat **to‘g‘ridan-to‘g‘ri** shu guruhdagi xodimlar bilan (pastki guruh
  xodimlari o‘z kartochkasida). Pastki guruh kartochkasi nomida ota yo‘li: "Ishlar bo‘limi › Texnik xodimlar"
  (`MembersPanelService::groupOrder()` dagi yo‘l bilan bir xil).
- Tartib: bo‘lim nomi bo‘yicha, bo‘lim ichida guruhlar daraxti tartibida (ota, keyin uning bolalari, nomi bo‘yicha) —
  `MembersPanelService::groupOrder()` mantig‘i. Har bo‘limning "Guruhsiz" kartochkasi shu bo‘lim oxirida.
- Kartochka ichida xodimlar: avval binodagilar, keyin qolganlar; har birida ism bo‘yicha.
- Ko‘rinadigan xodimi yo‘q guruh kartochkasi ko‘rsatilmaydi.
- Yorliq — bo‘lim nomi (kichik, katta harflarda, skrinshotdagi yorliq uslubida).
- Kartochka nomi bir qatorda, sig‘masa "…" bilan qisqaradi; to‘liq nomi tooltip’da.
- Rasmlar qatorga sig‘masa keyingi qatorga o‘tadi (yashirilmaydi).

## Texnik joylashuv

Loyihaning odatiy zanjiri (CLAUDE.md, "Request layering" va "Permissions — the wiring ritual"):

- `config/menu_permissions.php`: `resources.units = "Tarkibiy bo'linmalar"`; `user_role.full` ga `units`.
  Amal ruxsatlari (`actions`) yo‘q — sahifa faqat o‘qiydi.
- Migratsiya: ruxsatni yaratadi va `super-admin` hamda `user` rollariga beradi
  (`2026_11_04_100000_grant_event_pull_to_all_roles.php` namunasi; nom config’dan emas, satr sifatida).
- Route: `GET /admin/units` → `admin.units.index`, `permission:` middleware bilan. `resources/js/routes.js` ga qo‘shiladi.
- `HandleInertiaRequests` dagi `can` xaritasiga `units`.
- `resources/js/config/adminNav.js`: "Davomat"dan keyin, `PartitionOutlined` ikonkasi.
- Controller: `UnitController@index` → `Inertia::render('Admin/Units/Index', [...])`. Hisob servisda
  (`App\Services\Units\UnitBoardService` yoki shunga o‘xshash) — repozitoriy kerak emas, chunki yozuv yo‘q
  (Davomat ham `AttendanceGridService` bilan shunday).
- Rasm: `EmployeeResource::faceThumbnailUrl($id, $face_max_updated_at)` — `MembersPanelService` dagidek.
- Profil havolasi faqat foydalanuvchida `can.employees` bo‘lsa (`admin.employees.show`).
- Yangilanish: `useInertiaPoll(['…'], 30000)`.
- UI: Tailwind bilan joylashuv, Ant Design `Popover`, `Tooltip`, `Segmented` yoki tugmalar; ikonkalar faqat
  `@ant-design/icons`; avatar uchun mavjud `InitialsAvatar`. Ranglar mavjud tema tokenlaridan (yorug‘ va qorong‘i
  rejimda ishlashi kerak). Barcha matn o‘zbekcha.

## Testlar

Feature testlar (SQLite, mavjud yordamchilar bilan):

- Ruxsatsiz foydalanuvchi 403 oladi; ruxsati bor — sahifa ochiladi.
- `user` rolidagi foydalanuvchi faqat o‘z bo‘limini ko‘radi: boshqa bo‘lim guruhi va xodimi kartochkalarda ham,
  sanoqlarda ham yo‘q; `department_id` bo‘sh foydalanuvchi bo‘sh sahifa oladi. Super-admin ikkala bo‘limni ko‘radi.
- Holatlar: oxirgi o‘tishi kirish → binoda; kirib-chiqqan → binoda emas, Kirgan va Chiqqan vaqti to‘g‘ri; o‘tishi
  yo‘q, izohi yo‘q, ish kuni → sababsiz; izohi bor → sababli; shanba → sababsiz 0.
- Tungi smena: kecha 20:00 kirgan, hozir 02:00 → binoda.
- Muddati tugagan xodim chiqmaydi; guruhsiz xodim "Guruhsiz" kartochkasida.
- JSHSHIR javobda yo‘q.

## Quruvchi qarorlari (tekshiruvdan keyin)

- **Rasm faqat `'Xodimlar'` ruxsati bilan.** Yuz rasmi `admin.employees.face` orqali keladi, u faqat `'Xodimlar'`
  ruxsatini qabul qiladi. Bu ruxsat yo‘q foydalanuvchiga `face_url = null` yuboriladi va bosh harflar chiqadi —
  rasmlarga kirish kengaytirilmaydi. `user` va `super-admin` da ikkala ruxsat ham bor, ular rasmni ko‘radi.
- **Ruxsat faqat `super-admin` va `user` ga** (foydalanuvchi bilan shunday kelishilgan). Boshqa rollarga admin rol
  matritsasidan beradi.
- **"Guruhsiz" kartochkasi har bo‘limda alohida**, shu bo‘lim oxirida. (`employees.department_id` NOT NULL —
  bo‘limsiz xodim bo‘lmaydi.)
- **Shanba/yakshanba ish kuni sanasi bo‘yicha** aniqlanadi (`workDate`), kalendar kuni bo‘yicha emas — Davomat bilan
  bir xil.
- **Bir kunda bir nechta izoh** bo‘lsa popover’da hammasining turi vergul bilan ko‘rsatiladi.
- `MembersPanelService::groupOrder()` private — umumiy joyga chiqariladi va ikkala servis undan foydalanadi.

## Quruvchiga qoldirilganlar

- Servis/komponent nomlari, props tuzilishi, so‘rovlarni optimallashtirish (bugungi `reports` bitta so‘rov bilan,
  tungi smena uchun kechagi kun ham olinadi).
- Kartochkalar ustunlari soni ekran kengligiga qarab (skrinshotda 4 ta), bo‘sh holat matni.
- Filtr tugmalari ko‘rinishi (rangli tugmalar yoki `Segmented`), DESIGN.md qoidalariga mos.
