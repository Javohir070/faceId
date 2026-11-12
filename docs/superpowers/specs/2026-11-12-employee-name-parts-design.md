# Xodim ismi uch maydonda: Familiya, Ism, Otasining ismi

Foydalanuvchi so‘zlari: "employee name, 3 ta ajratish kerak last_name first_name, middle_name name o'z ham qolish
kerak yani 3 ta input bo'lish kerak 3 yingin name yozish kerak".

## Maqsad

Xodim formasida bitta "F.I.Sh." o‘rniga uchta input: **Familiya**, **Ism**, **Otasining ismi**. Mavjud `employees.name`
ustuni qoladi va uchta maydondan **"Familiya Ism Otasining ismi"** ko‘rinishida avtomatik yig‘iladi. `name` ni
o‘qiydigan hamma joy (ro‘yxatlar, hisobotlar, Davomat, Tarkibiy bo‘linmalar, Telegram, qurilmaga sinxronizatsiya,
Excel) o‘zgarmaydi.

## Qarorlar

- **Ustunlar.** `employees` ga `last_name`, `first_name`, `middle_name` — `string`, nullable, `name` dan keyin.
  Nullable: qurilmadan import va legacy API faqat bitta ism beradi, eski qatorlarda bitta so‘z bo‘lishi mumkin.
- **Bo‘lish qoidasi** (bitta helper: `App\Services\Employees\EmployeeName::split()`): bo‘shliqlar bitta qilinadi,
  1-so‘z → `last_name`, 2-so‘z → `first_name`, qolgani → `middle_name` ("Akmal o‘g‘li" butunligicha). Yo‘q so‘z → `null`.
- **Yig‘ish qoidasi** (`EmployeeName::join()`): bo‘sh qismlar tashlanadi, qolganlari bitta bo‘shliq bilan.
  Otasining ismi bo‘lmasa oxirida bo‘shliq qolmaydi.
- **Migratsiya** mavjud qatorlarni `split()` bilan to‘ldiradi; `down()` uchta ustunni olib tashlaydi.
- **Forma (admin).** Familiya va Ism majburiy, Otasining ismi ixtiyoriy, har biri ko‘pi bilan 80 belgi
  (3 × 80 + 2 bo‘shliq < 255 — `name` ustuniga sig‘adi). Xabarlar o‘zbekcha. `name` ni server yig‘adi:
  `EmployeeService::create()/update()`; formadan kelgan `name` e’tiborga olinmaydi. JSX da yig‘ilmaydi.
- **Tahrirlash** uchta maydon to‘ldirilgan holda ochiladi. Eski qatorda bitta so‘z bo‘lsa, Ism bo‘sh chiqadi va saqlashda
  "Ismni kiriting" xatosi beriladi — operator to‘ldiradi.
- **Ko‘rish (Show) sahifasi** profil kartochkasida Familiya, Ism, Otasining ismi qatorlarini ko‘rsatadi.
- **Bitta ism keladigan yo‘llar** `name` ni avvalgidek yozadi va qo‘shimcha `split()` natijasini saqlaydi:
  - legacy API `add_employee` — so‘rov va javob formati o‘zgarmaydi;
  - qurilmadan import (`EmployeePullService`).
  Mavjud xodimda `name` o‘zgarmagan bo‘lsa, qismlar qayta bo‘linmaydi — operator formada ajratgan ikki so‘zli
  familiya yo‘qolmasin.
- **Seeder** xodim yaratmaydi — o‘zgarish yo‘q. **Factory** uchta qismni `name` dan oladi, shuning uchun
  `Employee::factory()->create(['name' => …])` ham mos qismlar beradi.

## Qilinmaydi

- `name` ustunini o‘chirish yoki uni o‘qiydigan joylarni qismlarga o‘tkazish.
- Qidiruvni qismlar bo‘yicha qilish (qidiruv `name` bo‘yicha qoladi va to‘liq ismni topadi).
- Qurilmaga ismni qismlarga bo‘lib yuborish (terminal bitta `name` oladi).
