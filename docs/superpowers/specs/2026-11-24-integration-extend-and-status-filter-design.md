# Integratsiya API — muddatni uzaytirish va holat bo‘yicha ro‘yxat (dizayn)

## Maqsad

Foydalanuvchi so‘rovi (2026-11-24): “muddati tugasa uni uzaytirib qurilmalar ham yuborish api chiqarish kerak muddati
tugan ham list chiqarish api ham qilish kerak”.

Tashqi tizim (integratsiya API foydalanuvchisi) o‘z bo‘limining:

1. **muddati tugagan xodimlarini topadi**;
2. **muddatini uzaytiradi**: yangi muddat bazaga va darhol foydalanuvchining terminallariga yoziladi, xodim yana eshikdan
   o‘tadi.

- **Maqsad emas:** muddatni avtomatik uzaytirish, boshqa maydonlarni uzaytirish bilan birga o‘zgartirish (buning uchun
  `PUT`/`PATCH` bor), ro‘yxat maydonlarini o‘zgartirish.
- **Anti-maqsad:** boshqa bo‘lim xodimini ko‘rish yoki uzaytirish; foydalanuvchiga biriktirilmagan terminalga yozish;
  “uzaytirildi” deb javob berib, xodimni baribir muddati o‘tgan holda qoldirish; ro‘yxatda yuz rasmi.

## Hozirgi holat

- `PATCH /api/integration/employees/{jshshir}` `{"valid_to": "..."}` muddatni allaqachon o‘zgartiradi va
  `EmployeeSyncService::transferOne()` bilan foydalanuvchining terminallariga yozadi (terminalda `Valid.endTime`).
  Lekin o‘tgan sanani ham qabul qiladi, javobda xodim holati yo‘q.
- `GET /api/integration/employees` hamma xodimlarni beradi, holat bo‘yicha filtri yo‘q.
- Panelda `Employee::scopeValidity()` va `validityStatus()` bor: `active`, `expiring`, `expired`, `upcoming`.

## Yondashuvlar

**Uzaytirish:**

| Yondashuv | Afzalligi | Kamchiligi | Tanlandimi |
|---|---|---|---|
| Alohida `POST …/{jshshir}/extend` | Maqsadi aniq; faqat muddat; yangi `valid_to` kelajakda bo‘lishi shart; javobda yangi holat | Kichik yangi kod, `PATCH` bilan qisman takror | **Ha** (foydalanuvchi) |
| `PATCH` yetarli, faqat hujjat | Yangi kod yo‘q | O‘tgan sanaga “uzaytirish” ham o‘tadi; tashqi tizim uchun maqsadi yashirin | Yo‘q |

**Ro‘yxat:**

| Yondashuv | Afzalligi | Kamchiligi | Tanlandimi |
|---|---|---|---|
| Mavjud ro‘yxatga `?status=` filtri | To‘rtala holat ishlaydi (`expiring` bilan oldindan uzaytirish); `scopeValidity()` qayta ishlatiladi; bitta yo‘l | Tashqi tizim parametrni bilishi kerak | **Ha** (foydalanuvchi) |
| Alohida `GET …/expired` | Ko‘rinishi aniq | Boshqa holatlar uchun yana yo‘l kerak | Yo‘q |

## Dizayn

### POST /api/integration/employees/{jshshir}/extend

Kirish, 403 lar (`FORBIDDEN`, `NO_DEPARTMENT`, `SUPER_ADMIN`) va 404 `NOT_FOUND` (yo‘q yoki boshqa bo‘limniki,
`FindsIntegrationEmployee`, 422 dan oldin) boshqa amallardagidek. `INTEGRATION_API_SCHEDULE_ID` talab qilinmaydi.

| Maydon | Qoida |
|---|---|
| `valid_to` | majburiy sana, **hozirdan keyin** bo‘lishi shart (aks holda 422) |
| `valid_from` | ixtiyoriy sana |

- `valid_to` yuborilgan yoki bazadagi `valid_from` dan oldin bo‘lsa — 422 `valid_to`.
- Sanalar `POST`/`PUT` dagidek: zonasiz — tizim vaqti, zona bilan — tizim vaqtiga o‘giriladi.
- Boshqa maydonlar (ism, rasm, …) yuborilsa, e’tiborga olinmaydi.
- Xodim saqlanadi (`IntegrationEmployeeService::update()` orqali, faqat muddat maydonlari), keyin foydalanuvchining
  terminallariga `transferOne()` bilan qayta yoziladi. `Employee` ning `updating` hodisasi Telegram muddat
  ogohlantirishlarini o‘zi tozalaydi (`expiry_warned_at`, `expiry_notified_at`).
- Javob **200**: `{"ok": true, "employee": {...ro‘yxat maydonlari, "status": "active"}, "terminals": [...]}`.
  `employee.status` — `validityStatus()` (`GET …/{jshshir}` dagi kabi); `terminals` shakli `POST`/`PUT` dagidek.
- Terminal xatosi uzaytirishni bekor qilmaydi (bazada yangi muddat qoladi), `PUT` dagidek: qayta yuborish uchun
  `extend` ni yana chaqirish mumkin.
- Audit: `Integratsiya API: xodim muddati uzaytirildi` — `user_id`, `employee_id` (JSHSHIR yo‘q).

### GET /api/integration/employees?status=

- `status` — ixtiyoriy, `expired` | `expiring` | `active` | `upcoming` (`Employee::VALIDITIES`); boshqa qiymat — 422.
- Filtr `Employee::scopeValidity()` bilan, panel bilan bir xil:
  - `expired` — `valid_to` < hozir;
  - `expiring` — boshlangan va `valid_to` hozirdan `TELEGRAM_EMPLOYEE_EXPIRY_WARN_DAYS` (sukut 3) kungacha;
  - `active` — hozir amal qilmoqda (tugashiga yaqinlar ham kiradi);
  - `upcoming` — `valid_from` hali kelmagan.
- `search`, `limit`, `page`, tartib (`id`), javob shakli va har xodim maydonlari o‘zgarmaydi; rasm yo‘q.

### Hujjat

- `docs/integration-api.md`: `extend` bo‘limi, ro‘yxatda `status`, “muddati tugaganni uzaytirish” misoli.
- `/docs/api` (Scramble): yangi yo‘l `api.user:*` middleware orqali avtomatik Bearer/Basic oladi; `status` parametri
  controller’dagi `validate()` dan chiqadi. `ApiAuthDocumentationTest` ga tekshiruv.
- `CLAUDE.md` integratsiya API qatoriga `extend` va `status`.

## Claude qarorlari

- `employee.status` faqat `extend` javobida (va `GET …/{jshshir}` da). Ro‘yxat va `POST`/`PUT` javoblari o‘zgarmaydi.
- `extend` yangi `valid_to` eskisidan keyin bo‘lishini talab qilmaydi, faqat hozirdan keyin: muddatni kelajakdagi
  boshqa sanaga to‘g‘rilash ham shu yo‘l bilan bo‘ladi.
- Ro‘yxatda `status` uchun `id` bo‘yicha tartib saqlanadi (sahifalash barqaror).
- Yuz rasmi yo‘q xodim terminalga yozilmaydi (`skipped`, “Yuz rasmi yo‘q”) — mavjud `transferOne()` qoidasi; hujjatda
  aytiladi.
