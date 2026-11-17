<?php

/*
 * Bazaning oylik zaxira nusxasi (db:backup). Kunlik Spatie zaxirasidan (config/backup.php) alohida.
 * Hujjat: docs/database-backup.md
 */
return [

    // Faqat scheduler'ni o‘chiradi: php artisan db:backup qo‘lda doim ishlaydi.
    'enabled' => (bool) env('DB_BACKUP_ENABLED', true),

    // Zaxiralar papkasi. Public papka bo‘lmasin: dumpda JSHSHIR va yuz rasmlari bor.
    'path' => env('DB_BACKUP_PATH') ?: storage_path('app/private/db-backups'),

    // Nechta eng yangi nusxa saqlanadi. Bo‘sh yoki noto‘g‘ri qiymat — 12 (aks holda 0 → 1 bo‘lib, 11 nusxa o‘chardi).
    'keep' => (int) env('DB_BACKUP_KEEP') > 0 ? (int) env('DB_BACKUP_KEEP') : 12,

    // Veb-server foydalanuvchisining guruhi (masalan www-data): panel zaxiralarni ko‘rsatishi va yuklab berishi
    // uchun papka 0750, fayllar 0640 shu guruhga beriladi. Bo‘sh — faqat cron egasi (0700/0600).
    'group' => (string) env('DB_BACKUP_GROUP', ''),

    'connection' => env('DB_CONNECTION', 'mysql'),

    // mysqldump turgan papka; bo‘sh bo‘lsa PATH dagi mysqldump. Spatie zaxirasi bilan umumiy o‘zgaruvchi.
    'dump_binary_path' => env('MYSQL_DUMP_BINARY_PATH', ''),

    // Soniya. Bo‘sh yoki noto‘g‘ri qiymat — 3600.
    'timeout' => (int) env('DB_BACKUP_TIMEOUT') > 0 ? (int) env('DB_BACKUP_TIMEOUT') : 3600,

];
