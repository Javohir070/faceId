# Ma’lumotnoma API (`/api/v1/...`)

Tashqi tizimlar (birinchi navbatda **Raqamli Idora**, `raqamli.idora.uz`) bo‘lim, guruh va xodim
ro‘yxatini shu yerdan o‘qiydi. Faqat **o‘qiydi**: hech bir amal bazani yoki terminalni o‘zgartirmaydi.

Eski `/api/legacy` dan farqi: u eski tizimning `api.php` formatini saqlash uchun turibdi va IP bo‘yicha
ochiladi. Bu esa yangi kanal — oddiy JSON, HTTP Basic, sahifalash va `updated_since` bilan.

- **Manzil:** `GET https://<server>/api/v1/departments | /groups | /employees`
- **Himoya:** HTTP Basic — `DIRECTORY_API_USER` / `DIRECTORY_API_PASSWORD`
- **Javob:** har doim `{"ok": true, ...}`; xatoda `{"ok": false, "error": {...}}` yoki Laravel’ning 422 tekshiruv xatosi
- **Kod:** `App\Http\Controllers\Api\V1\DirectoryController` → `App\Services\DirectoryApiService`,
  himoya `App\Http\Middleware\VerifyDirectoryApiAuth`

## Sozlash (.env)

```dotenv
DIRECTORY_API_USER=idora
DIRECTORY_API_PASSWORD=<tasodifiy uzun parol>
```

`.env` o‘zgargach `php artisan config:clear` (config keshlangan bo‘lsa `php artisan config:cache`).

- **Ikkisidan biri bo‘sh bo‘lsa API butunlay yopiq** — har so‘rovga 401. Sabab: javobda JSHSHIR bor,
  ya’ni shaxsiy ma’lumot, va “sozlanmagan” hech qachon “hamma kira oladi” degani bo‘lmasligi kerak.
- Rad etilgan so‘rov `storage/logs/laravel.log` ga yoziladi (sozlanmagan — `critical`, noto‘g‘ri parol — `warning`).
- Parol tarmoqda ochiq ketadi, shuning uchun API **faqat HTTPS orqali** yoki ichki tarmoqda ochilsin.

## Bo‘lim va guruh — nima nimaga mos keladi

Bu tizimda **department** aslida *tashkilot* darajasi (`Agentlik`, `Fond`, `Ilmiy-texnik axborot markazi`,
`Kasbiy ta’lim agentligi`), **group** esa haqiqiy tarkibiy bo‘linma (boshqarma, bo‘lim) va `parent_id`
orqali daraxt bo‘lib turadi. Tashqi tizimning “bo‘limi” odatda bizdagi **group** ga mos keladi — shuning
uchun moslik jadvali tashqi tizim tomonida tuziladi.

## Amallar

### GET /api/v1/departments

Parametrsiz. Har bo‘limda nechta guruh va xodim borligi ham qaytadi.

```json
{"ok": true, "count": 2, "departments": [
  {"id": 13, "name": "Agentlik", "employees_count": 101, "groups_count": 29}
]}
```

### GET /api/v1/groups

| Parametr | Majburiy | Izoh |
|---|---|---|
| `department_id` | yo‘q | Faqat shu bo‘limning guruhlari |

```json
{"ok": true, "count": 29, "groups": [
  {"id": 188, "name": "Devonxona", "department_id": 13, "parent_id": null, "employees_count": 6}
]}
```

Daraxt `parent_id` orqali yig‘iladi; ildiz guruhda u `null`.

### GET /api/v1/employees

| Parametr | Majburiy | Izoh |
|---|---|---|
| `department_id` | yo‘q | Faqat shu bo‘lim xodimlari |
| `updated_since` | yo‘q | Shu vaqtdan keyin o‘zgarganlar (`Y-m-d H:i:s`). O‘qilmasa — 422 |
| `limit` | yo‘q | Sukut 200, eng ko‘pi 1000 |
| `page` | yo‘q | Sukut 1 |

```json
{"ok": true, "count": 1, "total": 101, "page": 1, "limit": 200, "has_more": false, "employees": [
  {"id": 1344, "card": "32610883420049", "name": "Aminov Sabur Muxtarovich",
   "last_name": "Aminov", "first_name": "Sabur", "middle_name": "Muxtarovich",
   "gender": 1, "position": null,
   "department_id": 13, "department": "Agentlik",
   "group_id": 191, "group": "Axborot kommunikatsiya texnologiyalari boshqarmasi",
   "valid_from": "2026-01-01 00:00:00", "valid_to": "2027-01-01 00:00:00",
   "validity": "active", "active": true, "updated_at": "2026-11-12 10:04:11"}
]}
```

- `card` — **JSHSHIR**, tashqi tizim xodimni aynan shu bo‘yicha moslashtiradi. Qurilmadan tortilgan eski
  yozuvlarda u 14 xonali bo‘lmasligi mumkin — tashqi tizim buni o‘zi tekshirsin.
- `validity`: `active` / `expiring` / `expired` / `upcoming`. `active` maydoni esa `expired` va `upcoming`
  da `false` bo‘ladi — muddati tugashiga yaqin xodim hamon ishlayapti.
- `position` ko‘pincha `null`: bu tizimda lavozim deyarli to‘ldirilmaydi.
- Yuz rasmi berilmaydi: u biometrik ma’lumot va bu API ning vazifasi emas.
- `name` — to‘liq ism; qismlari alohida maydonlarda, lekin eski yozuvlarda `middle_name` bo‘sh bo‘lishi mumkin.

## Bo‘lim cheklovi qo‘llanmaydi

So‘rov Basic auth bilan keladi, sessiya ochilmaydi, shuning uchun `BelongsToDepartmentScope` ishlamaydi va
tashqi tizim butun tashkilotni ko‘radi. Servis buni ataylab `withoutGlobalScopes()` bilan mustahkamlaydi:
brauzerda bo‘limga cheklangan foydalanuvchi sessiyasi ochiq bo‘lsa ham javob o‘zgarmaydi
(`DirectoryApiTest::test_bolim_cheklovi_qollanmaydi`).

## Sinash

```bash
curl -u "$DIRECTORY_API_USER:$DIRECTORY_API_PASSWORD" http://localhost:8000/api/v1/departments
```

Testlar: `php artisan test tests/Feature/Api/DirectoryApiTest.php`.

## Serverga chiqarganda

1. `.env` ga `DIRECTORY_API_USER` va `DIRECTORY_API_PASSWORD` — parol **ishlab chiqarish uchun
   alohida**, uzun va tasodifiy bo‘lsin.
2. `php artisan config:clear` (kesh ishlatilsa `config:cache` va `route:cache`).
3. API ni faqat ichki tarmoqqa yoki tashqi tizim IP siga oching. Internet orqali ochilsa — faqat
   HTTPS: Basic parol har so‘rovda ochiq ketadi.
4. Tashqi tizim (Raqamli Idora) tomonidagi hujjat: o‘sha loyihada `BIOCONTROL-XODIM-IMPORT.md`.
