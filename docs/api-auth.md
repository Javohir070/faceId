# Tashqi API ga kirish: token va Basic

[Parking API](parking-api.md) va [Integratsiya API](integration-api.md) ga tashqi tizim **panel foydalanuvchisi**
sifatida kiradi. Kirishning ikki usuli bor, ikkalasi ham ishlaydi:

1. **Token (tavsiya):** email va parolni bir marta yuborib 24 soatlik token olish, keyin har so‘rovda
   `Authorization: Bearer <token>`.
2. **Basic:** har so‘rovda `Authorization: Basic base64(email:parol)`.

Ikkala usulda ham foydalanuvchi rolida API ruxsati (`Parking API` yoki `Integratsiya API`) bo‘lishi shart va u **har
so‘rovda** tekshiriladi. So‘rov sessiya ham, cookie ham ochmaydi.

- **Kod:** `App\Http\Controllers\Api\ApiTokenController` (token), `App\Http\Middleware\AuthenticateApiUser`
  (`api.user:<kalit>`), `App\Services\ApiUserLogin` (parol tekshiruvi, cheklov, token egasi). Tokenlar — Laravel Sanctum
  (`personal_access_tokens` jadvali).

## POST /api/auth/token — token olish

```json
{"email": "parking@example.uz", "password": "<parol>", "name": "Parking tizimi"}
```

`name` ixtiyoriy (100 belgigacha), tokenni ajratib olish uchun; berilmasa `tashqi-api`.

Javob **201**:

```json
{"ok": true, "token": "15|Xy8Lk...", "token_type": "Bearer", "expires_at": "2026-11-24 14:05:00"}
```

- `token` ni to‘liq saqlang (`15|` qismi bilan) — u boshqa ko‘rsatilmaydi.
- `expires_at` — tizim vaqti (Toshkent). Muddat `API_TOKEN_TTL_MINUTES` (sukut 1440 = 24 soat).
- Token faqat rolida `Parking API` yoki `Integratsiya API` ruxsati bor foydalanuvchiga beriladi.
- Har kirishda **yangi** token beriladi; oldingilari muddati tugaguncha ishlaydi. Tizimning bir nechta nusxasi bir-birini
  chiqarib yubormaydi.

## Token bilan so‘rov

```bash
curl -H "Authorization: Bearer 15|Xy8Lk..." https://<server>/api/parking/employees
```

Token muddati tugasa yoki bekor qilinsa **401** (`WWW-Authenticate: Bearer`) qaytadi — tizim yangi token olib, so‘rovni
qaytaradi.

## DELETE /api/auth/token — tokenni bekor qilish

`Authorization: Bearer <token>` bilan yuboriladi, faqat **shu** token o‘chadi. Javob `{"ok": true}`. Token yo‘q yoki
noto‘g‘ri bo‘lsa 401.

## Token qachon ishlamay qoladi

| Holat | Natija |
|---|---|
| 24 soat o‘tdi | 401 |
| `DELETE /api/auth/token` | 401 |
| Foydalanuvchi paroli almashtirildi (admin paneli, profil, parolni tiklash) | hamma tokenlari o‘chadi — 401 |
| Foydalanuvchi o‘chirildi | hamma tokenlari o‘chadi — 401 |
| Roldan API ruxsati olindi | token qoladi, lekin har so‘rov 403 |

Token sizib chiqqan bo‘lsa — foydalanuvchi parolini almashtiring: hamma tokenlari darhol bekor bo‘ladi.
Muddati bir kundan ko‘proq o‘tgan tokenlar har kuni tunda o‘chiriladi (`sanctum:prune-expired`).

## Xatolar

| HTTP | `error.code` | Qachon |
|---|---|---|
| 401 | `UNAUTHORIZED` | Email/parol noto‘g‘ri, token noto‘g‘ri yoki muddati tugagan, Basic sarlavha yo‘q |
| 403 | `FORBIDDEN` | Rolida kerakli API ruxsati yo‘q (token olishda — birorta ham API ruxsati yo‘q) |
| 422 | — | Token so‘rovida `email` yoki `password` yo‘q |
| 429 | `TOO_MANY_ATTEMPTS` | Bir daqiqada bir IP va email dan 5 ta yoki bir IP dan istalgan emaillar bilan 20 ta xato parol. `Retry-After` — necha soniya kutish |

Xato parol hisobi token olish va Basic uchun **umumiy**: biri orqali parol terish ikkinchisining cheklovini chetlab
o‘tolmaydi. Token bilan so‘rovlarga cheklov qo‘llanmaydi (tokenni taxmin qilib bo‘lmaydi). Tokenning o‘zi va parol logga
yozilmaydi.

## Xavfsizlik

- Faqat HTTPS orqali yoki ichki tarmoqda: token ham, parol ham sarlavhada ochiq ketadi.
- Tokenni tizim konfiguratsiyasida saqlang, kodga yozmang. Parolni token olish bosqichidan boshqa joyda saqlamaslik
  mumkin — token tugaganda yangisini olish uchun parol kerak bo‘ladi.

## Serverga chiqarganda

`.env` ga ixtiyoriy `API_TOKEN_TTL_MINUTES` (sukut 1440), keyin `php artisan config:cache` va `route:cache` (kesh
ishlatilsa). Yangi migratsiya yo‘q: `personal_access_tokens` jadvali allaqachon bor. Cron `schedule:run` ni ishga
tushirayotgan bo‘lsa, tozalash o‘zi ishlaydi.
