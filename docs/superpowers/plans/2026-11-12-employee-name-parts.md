# Xodim ismi uch maydonda — reja

Spec: `docs/superpowers/specs/2026-11-12-employee-name-parts-design.md`. Har qadam TDD: avval qizil test.

1. **Helper.** `tests/Unit/Employees/EmployeeNameTest.php` → `app/Services/Employees/EmployeeName.php`
   (`split()`, `join()`, `clean()`).
2. **Migratsiya.** `tests/Feature/Database/SplitEmployeeNamesMigrationTest.php` →
   `database/migrations/2026_11_12_120000_add_name_parts_to_employees_table.php` (ustunlar + `split()` bilan to‘ldirish,
   `down()` olib tashlaydi). `Employee::$fillable` ga uchta maydon.
3. **Factory.** Qismlar `name` dan olinadi (`EmployeeFactory`).
4. **Admin forma (server).** `EmployeeAdminTest` ga: yig‘ish, Otasining ismisiz, kelgan `name` e’tiborsiz,
   majburiy/uzunlik validatsiyasi, edit/show props. → `StoreEmployeeRequest`, `UpdateEmployeeRequest`,
   `EmployeeService::create()/update()`, `EmployeeResource` (form va show maydonlari).
   Mavjud testlardagi `name` yuborishlari `last_name`/`first_name` ga o‘tkaziladi.
5. **Legacy API.** `LegacyApiTest` ga: yangi xodim qismlari, ism o‘zgarganda qayta bo‘linadi, o‘zgarmaganda qismlar
   saqlanadi; javob formati eskidek. → `LegacyApiService::addEmployee()`.
6. **Qurilmadan import.** `EmployeePullServiceTest` ga: yangi va yangilangan xodim qismlari. → `EmployeePullService`.
7. **JSX.** `Employees/Form.jsx` — uchta input; `Employees/Show.jsx` — uchta qator.
8. **Tekshirish.** `composer test`, `vendor/bin/pint`, `npm run build`, brauzerda localhost forma (yaratish, tahrirlash,
   validatsiya).
