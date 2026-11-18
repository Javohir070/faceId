# Parking API — xodimlar ro‘yxati (dizayn)

## Maqsad

Parking tizimi Biocontrol’dan xodimlar ro‘yxatini oladi: **id, FISH, JSHSHIR**. Ro‘yxatni qidirish va
sahifalab olish mumkin. Bu Raqamli Idora uchun ochilgan `/api/v1/...` dan **alohida** API.

- **Maqsad emas:** yozish (faqat o‘qiydi), yuz rasmi, bo‘lim/guruh/muddat maydonlari, panelda token sahifasi.
- **Anti-maqsad:** API kirishsiz ochiq qolishi; panel foydalanuvchisi bo‘limiga qarab ro‘yxat kesilib qolishi;
  `/api/v1` ga ta’sir qilishi.

## Foydalanuvchi qarorlari (2026-11-18)

- Manzil: `GET /api/parking/employees`.
- Kirish: administrator panelda parking uchun **foydalanuvchi va rol** yaratadi; parking tizimi har so‘rovda o‘sha
  foydalanuvchining email va parolini **HTTP Basic** bilan yuboradi.
- Hamma xodimlar qaytadi — foydalanuvchining bo‘limiga qaralmaydi.
- Filtrlar: **qidiruv** (FISH yoki JSHSHIR) va **sahifalash**.

## Yondashuvlar

| Yondashuv | Afzalligi | Kamchiligi | Tanlandimi |
|---|---|---|---|
| HTTP Basic, panel foydalanuvchisi email+parol | Yangi UI yo‘q, rol matritsasidan boshqariladi | Parol har so‘rovda ketadi (HTTPS shart), har so‘rovda bcrypt | **Ha** |
| Sanctum token (login → Bearer) | Parol bir marta ketadi | Ikki qadam, token o‘chirish yo‘li kerak | Yo‘q |
| Alohida `.env` login-parol (`/api/v1` kabi) | Oddiy | Foydalanuvchi “rol ochib beraman” dedi — panel orqali boshqarilishi kerak | Yo‘q |

## Dizayn

**So‘rov:** `GET /api/parking/employees?search=&page=&limit=`

| Parametr | Izoh |
|---|---|
| `search` | FISH (`employees.name`) yoki JSHSHIR (`employees.card`) ichida qidiradi; bo‘sh — e’tiborsiz |
| `page` | sukut 1, kamida 1 |
| `limit` | sukut 200, 1..1000 |

Noto‘g‘ri qiymat — Laravel’ning 422 javobi.

**Javob:**

```json
{"ok": true, "count": 1, "total": 412, "page": 1, "limit": 200, "has_more": true,
 "employees": [{"id": 1344, "fish": "Aminov Sabur Muxtarovich", "jshshir": "32610883420049"}]}
```

Tartib — `id` bo‘yicha. Muddati tugagan xodimlar ham chiqadi (foydalanuvchi “faqat faol” filtrini tanlamadi).

**Kirish (`parking.api` middleware):**

1. `Authorization: Basic` yo‘q yoki email/parol noto‘g‘ri — **401**, `WWW-Authenticate: Basic`, JSON
   `{"ok": false, "error": {"code": "UNAUTHORIZED", ...}}`. Logga `warning` (email, IP — `App\Http\ClientIp`; parol yo‘q).
2. Foydalanuvchi to‘g‘ri, lekin rolida **`Parking API`** ruxsati yo‘q — **403** (`FORBIDDEN`).
3. Bir IP + email uchun daqiqasiga 5 ta xato urinishdan keyin — **429** (`TOO_MANY_ATTEMPTS`), parol tekshirilmaydi.
4. Tekshiruv sessiyasiz (`Auth::guard('web')->once`): cookie ham, sessiya ham ochilmaydi.

**Bo‘lim cheklovi:** so‘rov davomida foydalanuvchi tizimga “kirgan” bo‘ladi, shuning uchun
`BelongsToDepartmentScope` ishlab ketadi. Servis `withoutGlobalScopes()` bilan buni ataylab o‘chiradi —
bo‘limi bo‘sh parking foydalanuvchisi ham hammani ko‘radi.

**Ruxsat:** `config/menu_permissions.php` ga yangi `api` bo‘limi (`'parking' => 'Parking API'`), seeder uni
oladi, mavjud bazalar uchun migratsiya ruxsatni yaratib faqat `super-admin` ga beradi. Rol matritsasida “Boshqa”
guruhida ko‘rinadi. Menyu yo‘q, `can` xaritasi o‘zgarmaydi.

**Kod qatlami:** `routes/api.php` → `App\Http\Controllers\Api\ParkingController` →
`App\Services\ParkingApiService`; himoya `App\Http\Middleware\AuthenticateParkingApi`.

**Hujjat:** `docs/parking-api.md` (sozlash: ruxsat → rol → foydalanuvchi; curl misol; HTTPS talabi),
CLAUDE.md ga bir qator.

## Quruvchiga qoldirilgan

- Xabar matnlari (o‘zbekcha), log matnlari.
- Testlar tuzilishi.
