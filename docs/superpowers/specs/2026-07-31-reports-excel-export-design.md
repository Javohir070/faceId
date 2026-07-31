# Reports — Excel export

## Maqsad

Admin → Hisobotlar (`/admin/reports`) sahifasida, ekranda ko'rinayotgan filtrlangan natijalarni `.xlsx` fayl sifatida yuklab olish imkoniyati.

## Qamrov

- Export joriy qo'yilgan filtrlar (xodim, bo'lim, qurilma, kategoriya, jadval, tur, sana oralig'i) bo'yicha — sahifalashsiz, filtrga mos **barcha** yozuvlar.
- Ustunlar: №, Ism, Bo'lim, Qurilma, Tur, Sana va vaqt — `Index.jsx` jadvalidagi ustunlar bilan bir xil.
- Ruxsat: mavjud `reports` permission (xuddi `admin.reports.index` kabi).

## Arxitektura

**Kutubxona:** `maatwebsite/excel` (Laravel Excel) — composer orqali qo'shiladi.

**Backend**
- `ReportController` dagi filtr+query qurish logikasi (`index()` ichida) alohida private metodga (`filteredReportsQuery(array $filters): Builder`) chiqariladi — `index()` va yangi `export()` ikkalasi shundan foydalanadi, kod takrorlanmaydi.
- Yangi route: `GET /admin/reports/export` → `ReportController::export()`, `permission:reports` middleware bilan, nomi `admin.reports.export`.
- `app/Exports/ReportsExport.php` — Laravel Excel export class:
  - `FromQuery` — filtrlangan `Builder`ni qabul qiladi, chunk-chunk oqim bilan yozadi (xotira tejamkor).
  - `WithHeadings` — `['№', 'Ism', "Bo'lim", 'Qurilma', 'Tur', 'Sana va vaqt']`.
  - `WithMapping` — har bir `Report` qatorini massivga aylantiradi (`type_label`, `department->name`, `device->name` bilan).
  - `ShouldAutoSize` — ustun kengligi avtomatik.

**Frontend**
- `Admin/Reports/Index.jsx`: `PageHeader` actions ichida, "Qurilmadan tortish" tugmasi yonida yangi **"Excel yuklab olish"** tugmasi.
- Tugma joriy `form` state'idagi filtrlarni query-string sifatida `route('admin.reports.export')` ga qo'shib, oddiy `<a href>` orqali yuklaydi (browser navigatsiyasi, Inertia so'rovi emas — fayl yuklab olish uchun to'g'ri yondashuv).
- `resources/js/routes.js` ga `admin.reports.export` route builder qo'shiladi.

## Xatoliklarni boshqarish

- Natija bo'sh bo'lsa — sarlavha qatorlari bilan bo'sh `.xlsx` qaytadi (xato emas).
- Ruxsati yo'q foydalanuvchi — mavjud `permission` middleware avtomatik 403 qaytaradi.

## Test

- Feature test: filtr bilan `GET /admin/reports/export` chaqirilganda, `Excel::fake()` orqali export class to'g'ri query/qatorlar bilan chaqirilganini tekshiradi.
- Ruxsatsiz foydalanuvchi uchun 403 testi (mavjud permission test patterniga mos).

## Chegaralar (YAGNI)

- Boshqa formatlar (CSV, PDF) hozircha qo'shilmaydi.
- Fon rejimida (queue) generatsiya qilish hozircha kerak emas — hajm katta bo'lmagani uchun sinxron yuklab olish yetarli.
