<?php

/*
|--------------------------------------------------------------------------
| Integratsiya API (/api/integration/employees)
|--------------------------------------------------------------------------
|
| Tashqi tizim panel foydalanuvchisi nomidan o'z bo'limiga xodim qo'shadi,
| tahrirlaydi va o'chiradi; xodim foydalanuvchining terminallariga darhol
| yoziladi. Batafsil: docs/integration-api.md.
|
| Har xodimda ish jadvali bo'lishi shart. API orqali qo'shilganlar hammasi
| shu jadvalga tushadi: umumiy jadval (bo'limsiz) yoki foydalanuvchi bo'limi
| jadvali bo'lishi kerak. Bo'sh yoki mos kelmasa API 503 qaytaradi.
|
*/

return [

    'schedule_id' => env('INTEGRATION_API_SCHEDULE_ID') !== null && env('INTEGRATION_API_SCHEDULE_ID') !== ''
        ? (int) env('INTEGRATION_API_SCHEDULE_ID')
        : null,

];
