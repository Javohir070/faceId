# Employee edit — ulangan qurilmalar + karta qulfi

## Maqsad

Xodimni tahrirlash sahifasida ulangan qurilmalar ko‘rinsin va ulardan o‘chirish mumkin bo‘lsin. Karta raqami faqat barcha qurilmalardan uzilgandan keyin o‘zgartiriladi — aks holda Sinxronizatsiya qurilmada eski `employeeNo` saqlanib, yangi karta bilan yangi user yaratiladi.

## Qamrov

- Edit form (`Admin/Employees/Form`): ulangan qurilmalar paneli + o‘chirish
- Karta input: edit rejimida `devices.length > 0` bo‘lsa readonly / disabled (+ generate tugmasi o‘chiq)
- Backend: bog‘langan qurilma borida `card` o‘zgarishini validation bilan taqiqlash
- Show sahifadagi qurilmalar paneli qayta ishlatiladi (umumiy komponent)
- Create form o‘zgarmaydi (karta ochiq)

## Qamrovdan tashqari

- Sync / remove job mantiqini o‘zgartirish (detach vaqti, queue)
- Kartani qisman o‘chirilganda (ba’zi qurilmalar qolsa) ochish — yo‘q, faqat **nol** bog‘lanishda
- Index/Home UI o‘zgarishlari

## Arxitektura

### Backend

1. `EmployeeService::loadForEdit` — `devices` relation ham yuklanadi.
2. `EmployeeResource::formFields()` — `devices` massivi (Show dagi format bilan bir xil: `id`, `name`, `ip_address`, `is_synced`, `synced_at`).
3. `UpdateEmployeeRequest`:
   - agar request dagi `card` joriy `employee.card` dan farq qilsa **va** `$employee->devices()->exists()` bo‘lsa → `card` maydoniga validation error (o‘zbekcha: avval qurilmalardan o‘chiring).
4. `EmployeeController::removeDevices` — `redirect()->back()` (Show ham, Edit ham ishlaydi), flash success saqlanadi.

### Frontend

1. `EmployeeDevicesPanel` ni `Show.jsx` dan `Components/EmployeeDevicesPanel.jsx` ga chiqarish.
2. `Form.jsx` (faqat `isEdit`):
   - pastda yoki form ostida panel: `employee.devices`
   - `hasLinkedDevices = (employee.devices ?? []).length > 0`
   - Karta `Input` + generate: `disabled={hasLinkedDevices}` (yoki `readOnly`)
   - Qisqa izoh: qurilmalar borida kartani o‘zgartirish mumkin emas; avval o‘chiring.
3. O‘chirish muvaffaqiyatida Inertia `back` orqali edit yangilanadi; queue `sync` bo‘lsa pivot darhol yo‘qoladi va karta ochiladi. Async queue da foydalanuvchi sahifani yangilab kutadi (mavjud Show xulqi bilan bir xil).

### Testlar

- Edit sahifa `employee.devices` qaytaradi.
- Qurilmaga bog‘langan xodimda `card` o‘zgartirish → 422 / validation error.
- Qurilmasiz (yoki o‘chirilgandan keyin) `card` o‘zgartirish → OK.
- `remove-devices` Edit refererdan chaqirilsa editga qaytadi; Showdan — showga (yoki `back`).

## UX oqimi

1. Edit ochiladi → ulangan qurilmalar ro‘yxati.
2. Karta qulflangan.
3. Qurilmalarni tanlab “O‘chirish” → job navbatga.
4. Pivot bo‘shagach → karta ochiladi → yangi raqam → saqlash.
5. Keyin Sinxronizatsiya — yangi `employeeNo` sifatida qo‘shiladi (eski allaqachon o‘chirilgan).

## Xatoliklar

- Bog‘langan holda karta o‘zgartirish: validation error.
- Ruxsatsiz remove/update: mavjud permission middleware (`Xodimlar`).
