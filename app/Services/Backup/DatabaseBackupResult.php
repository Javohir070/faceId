<?php

namespace App\Services\Backup;

final readonly class DatabaseBackupResult
{
    public function __construct(
        public string $path,
        public string $filename,
        public int $bytes,
        public int $kept,
        public int $deleted,
    ) {}
}
