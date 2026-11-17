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

    // Nechta eng yangi nusxa saqlanadi (kamida 1).
    'keep' => (int) env('DB_BACKUP_KEEP', 12),

    'connection' => env('DB_CONNECTION', 'mysql'),

    // mysqldump turgan papka; bo‘sh bo‘lsa PATH dagi mysqldump. Spatie zaxirasi bilan umumiy o‘zgaruvchi.
    'dump_binary_path' => env('MYSQL_DUMP_BINARY_PATH', ''),

    // Soniya.
    'timeout' => (int) env('DB_BACKUP_TIMEOUT', 3600),

];
