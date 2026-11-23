<?php

/*
|--------------------------------------------------------------------------
| Tashqi API tokeni (POST /api/auth/token)
|--------------------------------------------------------------------------
|
| Tashqi tizim email va parolni bir marta yuborib token oladi, keyin parking va
| integratsiya API lariga `Authorization: Bearer <token>` bilan kiradi.
| Batafsil: docs/api-auth.md.
|
| ttl_minutes — token qancha daqiqa amal qiladi (sukut 1440 = 24 soat). Bo'sh,
| 0 yoki manfiy qiymat sukutga qaytadi: muddatsiz token chiqmasligi kerak.
|
*/

return [

    'ttl_minutes' => (int) env('API_TOKEN_TTL_MINUTES') > 0 ? (int) env('API_TOKEN_TTL_MINUTES') : 1440,

];
