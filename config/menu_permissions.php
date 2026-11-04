<?php

return [
    'home' => 'Bosh sahifa',

    'resources' => [
        'categories' => 'Kategoriyalar',
        'devices' => 'Qurilmalar',
        'departments' => "Bo'limlar",
        'groups' => 'Guruhlar',
        'schedules' => 'Jadvallar',
        'employees' => 'Xodimlar',
        'employees-pull' => 'Xodim tortish',
        'notes' => 'Izohlar',
        'sync' => 'Sinxronizatsiya',
        'reports' => 'Hisobotlar',
        'attendance' => 'Davomat',
        'reports-pull' => 'Event tortish',
        'users' => 'Foydalanuvchilar',
        'roles' => 'Rollar',
        'permissions' => 'Ruxsatlar',
    ],

    // Amal ruxsatlari: resources dagi ruxsat — ko‘rish; qo‘shish, tahrirlash va o‘chirish alohida beriladi.
    // Yangi kalit qo‘shilsa mavjud bazalar uchun migratsiya ham kerak (2026_11_02_100000_add_action_permissions).
    'actions' => [
        'categories' => ['create' => 'Kategoriyalarni qo‘shish', 'edit' => 'Kategoriyalarni tahrirlash', 'delete' => 'Kategoriyalarni o‘chirish'],
        'devices' => ['create' => 'Qurilmalarni qo‘shish', 'edit' => 'Qurilmalarni tahrirlash', 'delete' => 'Qurilmalarni o‘chirish'],
        'departments' => ['create' => "Bo'limlarni qo‘shish", 'edit' => "Bo'limlarni tahrirlash", 'delete' => "Bo'limlarni o‘chirish"],
        'groups' => ['create' => 'Guruhlarni qo‘shish', 'edit' => 'Guruhlarni tahrirlash', 'delete' => 'Guruhlarni o‘chirish'],
        'schedules' => ['create' => 'Jadvallarni qo‘shish', 'edit' => 'Jadvallarni tahrirlash', 'delete' => 'Jadvallarni o‘chirish'],
        'employees' => ['create' => 'Xodimlarni qo‘shish', 'edit' => 'Xodimlarni tahrirlash', 'delete' => 'Xodimlarni o‘chirish'],
        'notes' => ['create' => 'Izohlarni qo‘shish', 'edit' => 'Izohlarni tahrirlash', 'delete' => 'Izohlarni o‘chirish'],
        'users' => ['create' => 'Foydalanuvchilarni qo‘shish', 'edit' => 'Foydalanuvchilarni tahrirlash', 'delete' => 'Foydalanuvchilarni o‘chirish'],
        'roles' => ['create' => 'Rollarni qo‘shish', 'edit' => 'Rollarni tahrirlash', 'delete' => 'Rollarni o‘chirish'],
        'permissions' => ['create' => 'Ruxsatlarni qo‘shish', 'edit' => 'Ruxsatlarni tahrirlash', 'delete' => 'Ruxsatlarni o‘chirish'],
    ],

    // `user` rolining standart huquqlari (DatabaseSeeder). O‘zgarsa mavjud bazalar uchun migratsiya ham kerak
    // (2026_11_02_120000_grant_default_user_role_permissions, 2026_11_03_100000_remove_extra_permissions_from_user_role,
    // 2026_11_04_100000_grant_event_pull_to_all_roles).
    'user_role' => [
        'full' => ['employees', 'groups', 'reports', 'reports-pull', 'notes', 'schedules', 'sync', 'attendance'],
        'view' => ['devices'],
    ],
];
