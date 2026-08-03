# User ↔ Department scope

## Maqsad

`users.department_id` orqali non–`super-admin` foydalanuvchilar faqat o‘z bo‘limiga tegishli Department-bog‘langan ma’lumotlarni ko‘radi/yozadi. `users.domain` olib tashlanadi.

## Qoidalar

- `super-admin`: barcha bo‘limlar; `department_id` ixtiyoriy.
- Boshqa barcha rollar: `department_id` **majburiy**; faqat shu bo‘lim.
- Qamrov: Employees, Groups, Reports; Notes — `employee` orqali.
- Qamrovdan tashqari: Devices, Schedules, Categories, Users ro‘yxati, Departments CRUD (dropdownlarda esa faqat o‘z bo‘limi).

## Schema

- `users.department_id` nullable FK → `departments`, `nullOnDelete`
- `users.domain` drop (base migration + alter migration)

## Scoping (yondashuv A)

- Global scope `BelongsToDepartmentScope` → `Employee`, `Group`, `Report`
- Auth yo‘q (queue/console) → filter yo‘q
- `super-admin` → filter yo‘q
- Boshqa + `department_id` → `where department_id = …`
- Boshqa + `department_id` null → bo‘sh natija
- Notes list: `whereHas('employee')` (Employee scope ishlaydi)
- Kartochka unique va tizim joblari: kerak joyda `withoutGlobalScopes`

## User CRUD

- Form: Bo‘lim select; `super-admin` yo‘q rollarda required
- Show/Index: bo‘lim nomi

## Yozish himoyasi

- Employee/Group create-update: scoped user uchun `department_id` majburiy merge (o‘z bo‘limi)
- Form dropdownlar: Departments faqat o‘z bo‘limi
