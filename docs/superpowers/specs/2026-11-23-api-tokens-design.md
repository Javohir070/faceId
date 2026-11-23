# Tashqi API uchun token (dizayn)

## Maqsad

Tashqi tizim email va parolni **bir marta** yuborib token oladi, keyin har so‘rovda parol o‘rniga
`Authorization: Bearer <token>` yuboradi. Parol tarmoqda kamroq yuradi va tizim konfiguratsiyasida saqlanmasa ham bo‘ladi.

- **Maqsad emas:** panelda tokenlar sahifasi, token huquqlarini (abilities) ajratish, OAuth.
- **Anti-maqsad:** token parolsiz ham abadiy ishlab qolishi; ruxsat olib tashlangan yoki paroli almashtirilgan
  foydalanuvchining tokeni ishlashda davom etishi; Basic bilan ulangan tizimlarning buzilishi.

## Foydalanuvchi qarorlari (2026-11-23)

- Token **ikkala** API da ishlaydi: `/api/parking/...` va `/api/integration/...`.
- Basic (email + parol) ham **qoladi**.
- Token muddati **24 soat**.
- Bir foydalanuvchida **bir nechta** token bo‘lishi mumkin; bekor qilish uchun alohida “chiqish” API.

## Yondashuvlar

| Yondashuv | Afzalligi | Kamchiligi | Tanlandimi |
|---|---|---|---|
| Sanctum shaxsiy tokenlari (o‘rnatilgan, jadval bor), Bearer tekshiruvi mavjud `AuthenticateApiUser` ichida | Ruxsat tekshiruvi, bo‘lim/terminal scope’lari, JSON xatolar bir joyda qoladi; yangi paket yo‘q | Sanctum’ning `auth:sanctum` guard’i ishlatilmaydi | **Ha** |
| `auth:sanctum` guard + alohida middleware zanjiri | Laravel’ning standart yo‘li | Ruxsat, scope va xato shakllarini ikkinchi marta yozish kerak; Basic bilan birga ishlatish chalkash | Yo‘q |
| O‘zimizning JWT | Bazaga murojaatsiz | Yangi paket, bekor qilish qiyin | Yo‘q |

## Dizayn

**POST /api/auth/token** — JSON `{"email", "password", "name"?}` (`name` — token nomi, masalan tizim nomi, 100 belgigacha):

- Email/parol noto‘g‘ri — **401** `UNAUTHORIZED`. Xato urinish cheklovi Basic bilan **umumiy** (email+IP 5/daq, IP 20/daq) — **429**.
- Foydalanuvchida `menu_permissions.api` dagi ruxsatlardan **birortasi ham** yo‘q bo‘lsa — **403** `FORBIDDEN`: token faqat
  tashqi tizim foydalanuvchisiga beriladi.
- Aks holda **201**:
  `{"ok": true, "token": "15|Xy...", "token_type": "Bearer", "expires_at": "2026-11-24 14:05:00"}`.
- Token muddati `API_TOKEN_TTL_MINUTES` (sukut 1440 = 24 soat). Tokenning o‘zi logga yozilmaydi.

**DELETE /api/auth/token** — `Authorization: Bearer <token>`: shu tokenni o‘chiradi, **200** `{"ok": true}`. Token yo‘q yoki
noto‘g‘ri — 401.

**`AuthenticateApiUser`:** `Authorization: Bearer ...` kelsa — Sanctum `PersonalAccessToken::findToken()`; topilmasa,
muddati o‘tgan bo‘lsa yoki foydalanuvchi yo‘q bo‘lsa — **401** (`WWW-Authenticate: Bearer`). Topilsa foydalanuvchi so‘rov
uchun guard’ga qo‘yiladi (sessiyasiz), `last_used_at` yangilanadi, keyin ruxsat tekshiruvi va boshqa hammasi Basic’dagidek.
Bearer yo‘lida xato urinish cheklovi yo‘q: token 40 belgilik tasodifiy, uni taxmin qilib bo‘lmaydi. Bearer bo‘lmasa —
avvalgidek Basic.

**Bekor qilish:**
- Foydalanuvchi paroli o‘zgarsa (admin paneli, profil, parolni tiklash) yoki foydalanuvchi o‘chirilsa — uning hamma
  tokenlari o‘chadi (`User` model hodisalari, uchala yo‘lni bir joyda qamraydi).
- Roldan ruxsat olinsa — token qoladi, lekin har so‘rovda ruxsat tekshirilgani uchun 403.
- Muddati o‘tgan tokenlar har kuni `sanctum:prune-expired` bilan tozalanadi.

**Hujjat:** `docs/api-auth.md` (ikkala API uchun kirish: token, Basic, cheklov, xatolar); `parking-api.md` va
`integration-api.md` undan havola oladi; CLAUDE.md.

## Quruvchiga qoldirilgan

- Basic va token endpoint’i uchun cheklov kodini umumiy joyga chiqarish usuli.
- `name` berilmasa token nomi.
