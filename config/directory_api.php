<?php

/*
|--------------------------------------------------------------------------
| Ma'lumotnoma API (GET /api/v1/...)
|--------------------------------------------------------------------------
|
| Tashqi tizimlar (Raqamli Idora) bo'lim, guruh va xodim ro'yxatini shu
| yerdan o'qiydi. Faqat o'qiydi, hech narsa yozmaydi. Batafsil:
| docs/directory-api.md.
|
| Himoya — HTTP Basic. Login yoki parol bo'sh bo'lsa API butunlay yopiq:
| xodim ro'yxati JSHSHIR bilan birga keladi, ya'ni shaxsiy ma'lumot, va
| "sozlanmagan" hech qachon "hamma kira oladi" degani bo'lmasligi kerak.
|
*/

return [

    'user' => env('DIRECTORY_API_USER'),
    'password' => env('DIRECTORY_API_PASSWORD'),

];
