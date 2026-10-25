<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Zal ekranlari — login'siz maxfiy havola
    |--------------------------------------------------------------------------
    |
    | Jonli kuzatuv va Tashrif statistikasi `/screen/{token}` va `/screen/{token}/stats` orqali login'siz ochiladi —
    | havolani bilgan har kim ko‘radi (ekranlarda ism va guruhlar bor, shuning uchun token uzun va tasodifiy bo‘lsin).
    | Bo‘sh — ochiq havola o‘chiq (404). Admin sahifalari (`/admin/live*`) avvalgidek login va huquq bilan.
    |
    */

    'screen_token' => env('LIVE_SCREEN_TOKEN'),

];
