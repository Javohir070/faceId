# Integratsiya API (`/api/integration/employees`)

Boshqa tizimlar shu API orqali Biocontrol’ga **xodim qo‘shadi, tahrirlaydi va o‘chiradi**. Har bir tizim panel
**foydalanuvchisi** nomidan ishlaydi:

- xodim o‘sha foydalanuvchining **bo‘limiga** tushadi;
- xodim foydalanuvchiga biriktirilgan **terminallarga darhol yoziladi**.

Bu “xodim terminalga faqat Sinxronizatsiya orqali boradi” qoidasidan ataylab qilingan istisno (birinchisi —
`/api/legacy`). Terminallar boshqa tizimlar bilan umumiy: API foydalanuvchisiga faqat kerakli terminallarni
biriktiring.

- **Himoya:** HTTP Basic — panel foydalanuvchisining **email** va **paroli**, rolida `Integratsiya API` ruxsati.
  Kirish, xato urinish cheklovi va xatolar parking API bilan bir xil ([parking-api.md](parking-api.md)).
- **Kod:** `App\Http\Controllers\Api\IntegrationEmployeeController` → `App\Services\IntegrationEmployeeService` →
  `EmployeeService` (baza) va `EmployeeSyncService` (terminal); himoya `api.user:integration`
  (`App\Http\Middleware\AuthenticateApiUser`) va `EnsureApiUserHasDepartment`.

## Sozlash

1. **`.env`** — API orqali qo‘shilgan xodimlarning ish jadvali (umumiy jadval yoki API foydalanuvchisi bo‘limining
   jadvali):
   ```dotenv
   INTEGRATION_API_SCHEDULE_ID=1
   ```
   Keyin `php artisan config:clear` (kesh ishlatilsa `config:cache`). Bo‘sh yoki mos kelmasa qo‘shish **503** qaytaradi.
2. **Admin → Sozlamalar → Rollar → Qo‘shish.** Masalan, `integratsiya` rolini oching va “Tashqi API” guruhidan
   faqat **Integratsiya API** ni belgilang. “Hammasini belgilash” uni belgilamaydi.
3. **Admin → Sozlamalar → Foydalanuvchilar → Qo‘shish.** Tizim uchun foydalanuvchi yarating: uzun tasodifiy parol,
   xodimlar tushadigan **bo‘lim** va `integratsiya` roli. Bo‘limsiz foydalanuvchi ham, super-admin ham (bo‘limi
   bo‘lsa ham) API ni ishlata olmaydi — 403: super-admin uchun bo‘lim va terminal cheklovlari ishlamaydi.
4. Shu ro‘yxatdagi bog‘lash belgisi orqali foydalanuvchiga **terminallarni** biriktiring — xodim aynan shularga
   yoziladi. Foydalanuvchi qo‘shgan terminallar ham hisoblanadi.

`Integratsiya API` ruxsati migratsiya bilan yaratiladi (`2026_11_23_100000_add_integration_api_permission`) va
avtomatik faqat super-admin’ga beriladi.

## POST /api/integration/employees — qo‘shish

| Maydon | Majburiy | Izoh |
|---|---|---|
| `last_name`, `first_name` | ha | 80 belgigacha |
| `middle_name` | yo‘q | 80 belgigacha |
| `jshshir` | ha | Aniq 14 raqam. Butun bazada yagona — boshqa bo‘limdagi xodimda bo‘lsa ham 422 |
| `gender` | ha | `1` erkak, `2` ayol |
| `position` | yo‘q | Lavozim |
| `group_id` | yo‘q | Faqat foydalanuvchi bo‘limidagi guruh |
| `valid_from`, `valid_to` | ha | Sana va vaqt (`2026-12-01 00:00:00`) — tizim vaqti (Toshkent). Faqat sana berilsa — 00:00:00. Zona bilan kelsa (`2026-11-30T19:00:00Z`, `+03:00`) tizim vaqtiga o‘giriladi |
| `face` | ha | Yuz rasmi, base64 JPEG yoki PNG, 1 MB gacha. `data:image/jpeg;base64,` prefiksi bo‘lishi mumkin |

Javob **201**:

```json
{"ok": true,
 "employee": {"id": 1501, "jshshir": "32610883420049", "fish": "Aminov Sabur Muxtarovich",
              "last_name": "Aminov", "first_name": "Sabur", "middle_name": "Muxtarovich", "gender": 1,
              "position": null, "department_id": 13, "group_id": null,
              "valid_from": "2026-12-01 00:00:00", "valid_to": "2027-12-01 23:59:59"},
 "terminals": [{"id": 3, "name": "Kirish 1", "status": "success", "message": "OK"},
               {"id": 4, "name": "Chiqish", "status": "skipped", "message": "Terminal oflayn"}]}
```

- `terminals[].status`:
  - `success` — yozildi;
  - `skipped` — terminal oflayn yoki xodimda yuz rasmi yo‘q (panel orqali qo‘shilgan xodimni `PUT` qilganda);
  - `failed` — terminal xatosi yoki terminalning login/paroli panelda sozlanmagan.
- Terminalda shu JSHSHIR bilan boshqa tizim yozgan foydalanuvchi bo‘lsa, u ustidan yoziladi (o‘chirishda — o‘chadi):
  terminallar umumiy, JSHSHIR esa terminalda xodim raqami.
- Terminal xatosi xodimni bekor qilmaydi: xodim bazada qoladi. Qayta yuborish uchun xuddi shu xodimga `PUT` yuboring
  (masalan bo‘sh `{}`).
- Foydalanuvchiga terminal biriktirilmagan bo‘lsa `terminals: []`.

## PUT (yoki PATCH) /api/integration/employees/{jshshir} — tahrirlash

Maydonlar `POST` dagidek, lekin **hammasi ixtiyoriy**: faqat yuborilganlari o‘zgaradi. `jshshir` o‘zgarmaydi. Ismning
bitta qismi yuborilsa, qolganlari saqlanadi va to‘liq ism qayta yig‘iladi. `valid_to` bazadagi `valid_from` dan oldin
bo‘lsa 422 qaytadi.

Xodim saqlangach, foydalanuvchining terminallariga **qayta yoziladi**. Javob **200**, shakli `POST` bilan bir xil.
Boshqa terminallarda (panel orqali yozilganlarida) eski ma’lumot qoladi — ularni panelda Sinxronizatsiya qiling.

## DELETE /api/integration/employees/{jshshir} — o‘chirish

Xodim u yozilgan **hamma** terminallardan (faqat foydalanuvchiniki emas) shu so‘rovning o‘zida o‘chiriladi, keyin
bazadan.

```json
{"ok": true, "terminals": [{"id": 3, "name": "Kirish 1", "status": "success", "message": "O‘chirildi"}]}
```

Biror terminaldan o‘chirib bo‘lmasa xodim **bazada qoladi** va **502** qaytadi. Terminal ishlay boshlagach,
qayta urinib ko‘ring:

```json
{"ok": false, "error": {"code": "TERMINAL_ERROR", "message": "..."},
 "terminals": [{"id": 4, "name": "Chiqish", "status": "failed", "message": "Terminal xodimni o‘chirmadi"}]}
```

Sabab: o‘chmagan terminal navbatga qo‘yilsa va serverda queue worker ishlamasa, o‘chirilgan xodim terminalda qolib,
eshikdan o‘taverardi. Terminal butunlay ishdan chiqqan bo‘lsa, xodimni panelda o‘chiring.

## Xatolar

| HTTP | `error.code` | Qachon |
|---|---|---|
| 401 | `UNAUTHORIZED` | Basic sarlavha yo‘q yoki email/parol noto‘g‘ri |
| 403 | `FORBIDDEN` | Rolida `Integratsiya API` ruxsati yo‘q |
| 403 | `NO_DEPARTMENT` | Foydalanuvchiga bo‘lim biriktirilmagan |
| 403 | `SUPER_ADMIN` | Super-admin bilan kirilgan — tashqi tizim uchun alohida foydalanuvchi oching |
| 404 | `NOT_FOUND` | Bu JSHSHIR li xodim yo‘q yoki boshqa bo‘limniki |
| 422 | — | Tekshiruv xatosi (Laravel’ning `{"message", "errors"}` shakli, o‘zbekcha) |
| 429 | `TOO_MANY_ATTEMPTS` | Ko‘p xato parol ([parking-api.md](parking-api.md)) |
| 502 | `TERMINAL_ERROR` | O‘chirishda terminal xatosi, xodim bazada qoldi |
| 503 | `NOT_CONFIGURED` | `INTEGRATION_API_SCHEDULE_ID` yo‘q yoki foydalanuvchi bo‘limiga mos emas |

Har bir qo‘shish, tahrir va o‘chirish `storage/logs/laravel.log` ga `info` bo‘lib yoziladi: `user_id` va
`employee_id`. Bu audit qatorlarida JSHSHIR, rasm va parol yo‘q. Lekin terminal bilan ishlash qatorlari
(`Hikvision: ...`) panel sinxronizatsiyasidagi kabi terminal xodim raqamini (`employee_no`, ya’ni JSHSHIR) yozadi.

## Sinash

```bash
curl -u "integratsiya@example.uz:<parol>" -H "Content-Type: application/json" \
  -d '{"last_name":"Aminov","first_name":"Sabur","jshshir":"32610883420049","gender":1,"valid_from":"2026-12-01","valid_to":"2027-12-01 23:59:59","face":"<base64>"}' \
  https://<server>/api/integration/employees
```

Diqqat: bu buyruq xodimni **haqiqiy terminallarga yozadi**. Sinash uchun foydalanuvchiga terminal biriktirmang.

Testlar (terminallar soxta): `php artisan test tests/Feature/Api/IntegrationEmployeeApiTest.php`.

## Serverga chiqarganda

1. `git pull` va `php artisan migrate --force` — `Integratsiya API` ruxsati yaratiladi.
2. `npm run build` — rol formasi o‘zgargan.
3. `.env` ga `INTEGRATION_API_SCHEDULE_ID`, keyin `php artisan config:cache` va `php artisan route:cache` (kesh
   ishlatilsa).
4. Panelda rol, foydalanuvchi (bo‘lim bilan) va terminallarni biriktiring (yuqoridagi “Sozlash”).
5. API ni faqat HTTPS orqali yoki ichki tarmoqda oching: parol har so‘rovda ketadi.
