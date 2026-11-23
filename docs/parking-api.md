# Parking API (`/api/parking/...`)

Parking tizimi xodimlar ro‘yxatini shu yerdan oladi: **id, FISH, JSHSHIR**. API faqat **o‘qiydi**: bazani ham,
terminalni ham o‘zgartirmaydi.

Raqamli Idora uchun ochilgan `/api/v1/...` dan ([directory-api.md](directory-api.md)) farqi:

- login va parol `.env` da emas — parking tizimi **panel foydalanuvchisi** sifatida kiradi;
- kirish rol orqali boshqariladi;
- javobda faqat uchta maydon bor.

- **Manzil:** `GET https://<server>/api/parking/employees`
- **Himoya:** HTTP Basic — panel foydalanuvchisining **email** va **paroli**, rolida `Parking API` ruxsati bilan
- **Javob:** `{"ok": true, ...}`; xatoda `{"ok": false, "error": {"code": ..., "message": ...}}` yoki Laravel’ning 422 tekshiruv xatosi
- **Kod:** `App\Http\Controllers\Api\ParkingController` → `App\Services\ParkingApiService`, himoya
  `App\Http\Middleware\AuthenticateApiUser` (`api.user:parking`, [integratsiya API](integration-api.md) bilan umumiy)

## Sozlash (panelda)

1. **Admin → Sozlamalar → Rollar → Qo‘shish.** Masalan, `parking` nomli rol oching va matritsaning “Tashqi API”
   qismidan faqat **Parking API** ni belgilang. Boshqa ruxsat bermang: bu foydalanuvchi panelda hech narsa
   ko‘rmasligi kerak.

   **Bu ruxsatni bo‘lim rollariga bermang.** U bo‘lim cheklovisiz ishlaydi: rol egasi o‘z paroli bilan hamma
   bo‘limlar xodimlarini JSHSHIR bilan oladi. Shuning uchun rol formasidagi “Hammasini belgilash” uni belgilamaydi.
2. **Admin → Sozlamalar → Foydalanuvchilar → Qo‘shish.** Masalan, `parking@...` emaili va uzun tasodifiy parol
   bilan foydalanuvchi yarating va unga `parking` rolini bering. Forma bo‘limni talab qiladi — istalganini tanlang,
   ro‘yxatga ta’sir qilmaydi.
3. Email va parolni parking tizimi sozlamasiga yozing.

`Parking API` ruxsati migratsiya bilan yaratiladi (`2026_11_18_100000_add_parking_api_permission`) va avtomatik faqat
super-admin’ga beriladi.

Kirishni yopish uchun foydalanuvchini o‘chiring, uning parolini almashtiring yoki roldan ruxsatni oling — keyingi
so‘rovdayoq 401 yoki 403 qaytadi.

## GET /api/parking/employees

| Parametr | Majburiy | Izoh |
|---|---|---|
| `search` | yo‘q | FISH yoki JSHSHIR ichidan qidiradi (`Aminov`, `Sabur`, `32610883`). 100 belgigacha |
| `limit` | yo‘q | Sukut 200, eng ko‘pi 1000 |
| `page` | yo‘q | Sukut 1 |

```json
{"ok": true, "count": 1, "total": 412, "page": 1, "limit": 200, "has_more": true, "employees": [
  {"id": 1344, "fish": "Aminov Sabur Muxtarovich", "jshshir": "32610883420049"}
]}
```

- Ro‘yxat `id` bo‘yicha tartiblangan. Hammasini olish uchun `has_more` `false` bo‘lguncha `page` ni oshiring.
- **Hamma xodimlar** qaytadi: parking butun bino uchun, shuning uchun parking foydalanuvchisining bo‘limi
  ahamiyatsiz. Servis bo‘lim cheklovini (`BelongsToDepartmentScope`) ataylab o‘chiradi.
- **Muddati tugagan xodimlar ham** ro‘yxatda bor. Javobda muddat ham, holat ham yo‘q.
- `jshshir` — `employees.card`. Qurilmadan tortilgan eski yozuvlarda u 14 xonali bo‘lmasligi mumkin.
- Yuz rasmi, bo‘lim, guruh berilmaydi.

## Xatolar

| HTTP | `error.code` | Qachon |
|---|---|---|
| 401 | `UNAUTHORIZED` | Basic sarlavha yo‘q yoki email/parol noto‘g‘ri. Javobda `WWW-Authenticate: Basic` |
| 403 | `FORBIDDEN` | Email va parol to‘g‘ri, lekin rolida `Parking API` ruxsati yo‘q |
| 429 | `TOO_MANY_ATTEMPTS` | Bir daqiqada bir IP va email dan 5 ta yoki bir IP dan istalgan emaillar bilan 20 ta xato urinish. `Retry-After` sarlavhasida necha soniya kutish kerakligi |
| 422 | — | `limit` 1..1000 oralig‘ida emas, `page` 1 dan kichik yoki `search` 100 belgidan uzun |

- 429 holatida to‘g‘ri parol ham qabul qilinmaydi: cheklov paroldan oldin tekshiriladi. Muvaffaqiyatli kirish shu
  email hisobini tozalaydi, IP hisobi esa daqiqa o‘tib o‘zi tushadi.
- Email katta-kichik harf va chetidagi bo‘shliqlardan qat’i nazar bitta hisobga tushadi.
- Basic sarlavhasiz so‘rov xato urinish hisoblanmaydi va logga yozilmaydi: ko‘p HTTP klientlar parolni faqat 401
  javobidan keyin yuboradi.
- `Accept: application/json` yuborilmasa ham xatolar JSON bo‘lib qaytadi.
- Noto‘g‘ri parol yoki ruxsatsiz so‘rov `storage/logs/laravel.log` ga `warning` bo‘lib yoziladi: email va IP, ruxsat
  bo‘lmaganda esa `user_id`. Parol va `search` qiymati `laravel.log` ga yozilmaydi.
- `search` so‘rov manzilida (`?search=...`) ketadi, shuning uchun JSHSHIR bo‘yicha qidiruv **veb-server
  (nginx) access log**iga tushadi. Kerak bo‘lsa, shu manzil uchun access logni o‘chiring yoki qisqa saqlang.

## Xavfsizlik

- Har so‘rovda parol tekshiriladi, lekin sessiya ham, cookie ham ochilmaydi.
- Parol har so‘rovda tarmoq orqali ketadi. Shuning uchun API **faqat HTTPS orqali** yoki ichki tarmoqda ochilsin.
- Bu foydalanuvchining paroli bilan panelga ham kirish mumkin. Rolida boshqa ruxsat bo‘lmasa, panelda hech narsa ko‘rmaydi.

## Sinash

```bash
curl -u "parking@example.uz:<parol>" "http://localhost:8000/api/parking/employees?search=Aminov&limit=50"
```

Testlar: `php artisan test tests/Feature/Api/ParkingApiTest.php`.

## Serverga chiqarganda

1. `git pull` va `php artisan migrate --force` — `Parking API` ruxsati yaratiladi.
2. Kesh ishlatilsa: `php artisan config:cache` va `php artisan route:cache`.
3. Panelda rol va foydalanuvchini yarating (yuqoridagi “Sozlash” bo‘limi).
4. Parking tizimi tomonida manzilni (`https://<server>/api/parking/employees`), email va parolni sozlang.
