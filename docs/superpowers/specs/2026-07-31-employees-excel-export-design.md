# Employees — Excel export

## Maqsad

Admin → Xodimlar (`/admin/employees`) sahifasida, ekranda ko'rinayotgan filtrlangan natijalarni `.xlsx` fayl sifatida yuklab olish imkoniyati — Reports export bilan bir xil pattern (`docs/superpowers/specs/2026-07-31-reports-excel-export-design.md`).

## Qamrov

- Export joriy qo'yilgan filtrlar (qidiruv, bo'lim, jadval, qurilma, yuz bor/yo'q) bo'yicha — sahifalashsiz, filtrga mos **barcha** yozuvlar.
- Ustunlar: №, F.I.Sh., Karta, Jins, Lavozim, Bo'lim, Jadval, Yuz, Amal qilish (dan), Amal qilish (gacha) — `Index.jsx` jadvalidagi ustunlar bilan bir xil, "Amal qilish" ikkita alohida ustunga ajratiladi (Excel'da saralash/filtrlash uchun qulay).
- Ruxsat: mavjud `employees` permission (xuddi `admin.employees.index` kabi).

## Arxitektura

**Kutubxona:** `maatwebsite/excel` — allaqachon o'rnatilgan (Reports export orqali).

**Backend**
- `EmployeeRepositoryInterface` va `EmployeeRepository`ga yangi metod: `filteredQuery(array $filters): Builder` — hozirgi `paginate()` ichidagi filtr logikasi (`search`, `department_id`, `schedule_id`, `device_id`, `has_face`) shu metodga chiqariladi; `paginate()` o'zi shu metoddan foydalanadi (DRY).
- `EmployeeService`ga `getFilteredQuery(array $filters): Builder` — repository'ni chaqiradi.
- Yangi route: `GET /admin/employees/export` → `EmployeeController::export()`, `permission:employees` middleware bilan, nomi `admin.employees.export`.
- `app/Exports/EmployeesExport.php` — `FromQuery` + `WithHeadings` + `WithMapping` + `ShouldAutoSize`.

**Frontend**
- `Admin/Employees/Index.jsx`: `PageHeader` actions ichida, "Qurilmadan tortish" yonida "Excel yuklab olish" tugmasi — joriy `filters`ni query-string sifatida export URL'ga qo'shib, oddiy `<a href>` orqali yuklaydi.
- `resources/js/routes.js` ga `admin.employees.export` route builder qo'shiladi.

## Xatoliklarni boshqarish

- Natija bo'sh bo'lsa — sarlavha qatorlari bilan bo'sh `.xlsx` qaytadi.
- Ruxsati yo'q foydalanuvchi — mavjud `permission` middleware avtomatik 403.

## Test

- Feature test: filtr bilan `GET /admin/employees/export` chaqirilganda, `Excel::fake()` orqali export'ning to'g'ri query bilan chaqirilganini tekshiradi (Reports uchun yozilgan `ReportExportTest` patterniga mos).
- Ruxsatsiz foydalanuvchi uchun 403 testi.

## Chegaralar (YAGNI)

- Yuz rasmi (face) Excel'ga tasvir sifatida joylanmaydi — faqat "bor/yo'q" holati (jadvaldagidek).
- Boshqa formatlar yoki fon rejimida generatsiya qilish qo'shilmaydi.
