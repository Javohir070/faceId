# Integratsiya API — xodim qo‘shish, tahrirlash, o‘chirish (dizayn)

## Maqsad

Boshqa tizimlar Biocontrol’ga xodim qo‘shadi, tahrirlaydi va o‘chiradi. Administrator har bir tizim uchun panelda
**foydalanuvchi** ochadi. Tizim o‘sha foydalanuvchi nomidan ishlaydi: xodim foydalanuvchining **bo‘limiga** tushadi va
foydalanuvchiga biriktirilgan **terminallarga darhol yoziladi**.

- **Maqsad emas:** ro‘yxat yoki o‘qish (buning uchun `/api/v1` va `/api/parking` bor), guruh yoki jadval yaratish,
  boshqa bo‘lim xodimiga tegish.
- **Anti-maqsad:** tizim boshqa bo‘lim xodimini o‘zgartira olishi yoki o‘chira olishi; foydalanuvchiga biriktirilmagan
  terminalga yozish; kirishsiz yoki ruxsatsiz ochiq qolish; xato javobsiz yashirin terminal xatosi.

## Foydalanuvchi qarorlari (2026-11-23)

- Manzil: `/api/integration/employees` — `POST` qo‘shish, `PUT /{jshshir}` tahrirlash, `DELETE /{jshshir}` o‘chirish.
- Kirish: panel foydalanuvchisi (parking API kabi HTTP Basic, email + parol).
- Xodim foydalanuvchining bo‘limiga tushadi.
- Terminal: **darhol** yoziladi — foydalanuvchiga biriktirilgan terminallarga (yaratgan yoki Admin → Foydalanuvchilar
  dagi bog‘lash belgisi orqali biriktirilgan qurilmalar). Bu “xodim faqat Sinxronizatsiya orqali boradi” qoidasidan
  ikkinchi ataylab qilingan istisno (birinchisi `/api/legacy`).
- Ish jadvali: faqat sukut — `.env` dagi `INTEGRATION_API_SCHEDULE_ID`.
- Amal qilish muddati (`valid_from`, `valid_to`): majburiy.
- Yuz rasmi: qo‘shishda majburiy.

## Yondashuvlar

| Yondashuv | Afzalligi | Kamchiligi | Tanlandimi |
|---|---|---|---|
| Alohida REST API, panel foydalanuvchisi + yangi ruxsat, bo‘lim cheklovi o‘z holicha ishlaydi | Kim nima qilgani aniq, tizimni o‘chirish — foydalanuvchini o‘chirish; boshqa bo‘limga tega olmaydi | Yangi kod | **Ha** |
| `/api/legacy` `add_employee` ni kengaytirish | Tayyor | IP bo‘yicha himoya, bitta bo‘lim/jadval, hamma terminallarga yozadi, eski format | Yo‘q |
| Faqat bazaga, terminal Sinxronizatsiya orqali | Terminal qoidasi saqlanadi | Foydalanuvchi “darhol” ni tanladi | Yo‘q |

## Dizayn

**Kirish:** parking middleware’i umumiy `api.user:<kalit>` middleware’iga aylanadi (`AuthenticateApiUser`): HTTP Basic,
sessiyasiz `once`, xato urinish cheklovi (email+IP 5/daq, IP 20/daq), `Accept: application/json`, ruxsat nomi
`config('menu_permissions.api.<kalit>')`. Parking `api.user:parking`, bu API `api.user:integration` bilan.
Yangi ruxsat **`Integratsiya API`** — `menu_permissions.api.integration`, migratsiya faqat super-admin ga beradi,
rol formasida “Tashqi API” guruhida, “Hammasini belgilash” ga kirmaydi.

**Bo‘lim:** foydalanuvchining `department_id` si. Bo‘limi yo‘q foydalanuvchi (masalan super-admin) — **403**
`NO_DEPARTMENT`. So‘rov davomida foydalanuvchi tizimga kirgan hisoblanadi, shuning uchun `BelongsToDepartmentScope` va
`AssignedDevicesScope` o‘z holicha ishlaydi: tahrir/o‘chirishda boshqa bo‘lim xodimi **404**, terminallar — faqat
foydalanuvchiniki.

**POST /api/integration/employees** (JSON):

| Maydon | Qoida |
|---|---|
| `last_name`, `first_name` | majburiy, ≤80 |
| `middle_name` | ixtiyoriy, ≤80 |
| `jshshir` | majburiy, aniq 14 raqam, butun bazada yagona (boshqa bo‘limda bo‘lsa ham 422) |
| `gender` | majburiy, `1` erkak / `2` ayol |
| `position` | ixtiyoriy, ≤255 |
| `group_id` | ixtiyoriy, foydalanuvchi bo‘limidagi guruh |
| `valid_from`, `valid_to` | majburiy sana, `valid_to ≥ valid_from` |
| `face` | majburiy, base64 (ixtiyoriy `data:image/...;base64,` prefiksi bilan), JPEG yoki PNG, ≤1 MB |

Jadval — `INTEGRATION_API_SCHEDULE_ID`. Sozlanmagan yoki foydalanuvchi bo‘limiga mos bo‘lmasa (`AvailableSchedule`) —
**503** `NOT_CONFIGURED`, xodim yaratilmaydi.

Xodim va yuz rasmi bazaga yoziladi (bitta tranzaksiya), keyin foydalanuvchining har bir terminaliga
`EmployeeSyncService::transferOne()` bilan yoziladi. Javob **201**:

```json
{"ok": true,
 "employee": {"id": 1501, "jshshir": "32610883420049", "fish": "Aminov Sabur Muxtarovich",
              "last_name": "Aminov", "first_name": "Sabur", "middle_name": "Muxtarovich", "gender": 1,
              "position": null, "department_id": 13, "group_id": null,
              "valid_from": "2026-12-01 00:00:00", "valid_to": "2027-12-01 00:00:00"},
 "terminals": [{"id": 3, "name": "Kirish 1", "status": "success", "message": "OK"},
               {"id": 4, "name": "Chiqish", "status": "skipped", "message": "Terminal oflayn"}]}
```

`status`: `success` / `failed` / `skipped`. Terminal xatosi xodimni bekor qilmaydi — tizim `PUT` bilan qayta yuborishi
mumkin. Foydalanuvchida terminal bo‘lmasa `terminals: []`.

**PUT /api/integration/employees/{jshshir}:** maydonlar POST dagidek, lekin hammasi ixtiyoriy (yuborilgani
o‘zgaradi); `jshshir` o‘zgarmaydi. Keyin xodim foydalanuvchining terminallariga qayta yoziladi. Javob 200, shakli
POST bilan bir xil. Topilmasa (yoki boshqa bo‘limniki) — 404 `NOT_FOUND`.

**DELETE /api/integration/employees/{jshshir}:** xodim u yozilgan **hamma** terminallardan (faqat foydalanuvchiniki
emas — panel qoidasi kabi) darhol o‘chiriladi, keyin bazadan. Javob 200 `{"ok": true, "terminals": [...]}`.
Biror terminaldan o‘chirib bo‘lmasa xodim **bazada qoladi** va **502** `TERMINAL_ERROR` + `terminals` qaytadi —
tizim keyin qayta urinadi (`/api/legacy` `delete_employee` kabi).

*2026-11-23 tuzatish (reja bosqichida):* dastlab o‘chmagan terminallar navbatga qo‘yilishi kerak edi. Serverda queue
worker ishlamaydi, shuning uchun o‘chirilgan xodim terminalda qolib, eshikdan o‘tishda davom etardi va buni hech kim
bilmasdi. Xato javob bilan xodimni qoldirish bu holatni ko‘rinadigan qiladi.

**Audit:** har amal `laravel.log` ga `info`: `user_id`, `employee_id`, amal. JSHSHIR va rasm logga yozilmaydi.

**Hujjat:** `docs/integration-api.md`; CLAUDE.md ning tashqi kanallar va “Employees never auto-sync” qismlariga qator.

## Quruvchiga qoldirilgan

- Base64 rasmni `UploadedFile` ga aylantirish usuli; MIME ni `finfo` bilan tekshirish.
- Terminal natijasini `transferOne()` natijasidan javob shakliga o‘girish va xabar matnlari.
- O‘chirishda hamma terminallarga yetish uchun `AssignedDevicesScope` ni chetlab o‘tish usuli.
- Rol formasidagi “Tashqi API” ogohlantirish matnini ikkala API ga mos umumlashtirish.
