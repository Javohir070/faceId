<?php

/*
|--------------------------------------------------------------------------
| Eski Biocontrol API (POST /api/legacy)
|--------------------------------------------------------------------------
|
| Eski tizimning api.php’siga ulangan tashqi tizimlar uchun: so‘rov va javob
| formati eskisidek. Batafsil: docs/legacy-api.md.
|
*/

return [

    // Vergul bilan: IP yoki CIDR (masalan 203.0.113.5,10.0.0.0/24). Bo‘sh bo‘lsa hech kim kira olmaydi.
    'allowed_ips' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('LEGACY_API_ALLOWED_IPS', '')),
    ))),

    // add_employee yangi xodim yaratganda shu bo‘lim va ish jadvali beriladi (mavjud xodimniki o‘zgarmaydi).
    'department_id' => env('LEGACY_API_DEPARTMENT_ID'),
    'schedule_id' => env('LEGACY_API_SCHEDULE_ID'),

];
