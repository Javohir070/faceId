<?php

namespace App\Console\Commands;

use App\Services\Backup\DatabaseBackupResult;
use App\Services\Backup\DatabaseBackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class DbBackupCommand extends Command
{
    protected $signature = 'db:backup';

    protected $description = 'Bazaning siqilgan zaxira nusxasini oladi va eski nusxalarni o‘chiradi (har oy scheduler orqali)';

    public function handle(DatabaseBackupService $backups): int
    {
        try {
            $result = $backups->run();
        } catch (Throwable $exception) {
            $reason = Str::limit(trim($exception->getMessage()), 300);

            Log::error('Baza zaxirasi olinmadi', ['error' => $exception->getMessage()]);
            $this->error("Zaxira olinmadi: {$reason}");

            return self::FAILURE;
        }

        Log::info('Baza zaxirasi olindi', [
            'file' => $result->path,
            'bytes' => $result->bytes,
            'kept' => $result->kept,
            'deleted' => $result->deleted,
        ]);

        $this->info(sprintf(
            'Zaxira olindi: %s (%s). Saqlangan nusxalar: %d ta, o‘chirilgan: %d ta.',
            $result->filename,
            self::humanSize($result),
            $result->kept,
            $result->deleted,
        ));

        return self::SUCCESS;
    }

    public static function humanSize(DatabaseBackupResult $result): string
    {
        return $result->bytes >= 1048576
            ? number_format($result->bytes / 1048576, 1, ',', ' ').' MB'
            : number_format($result->bytes / 1024, 1, ',', ' ').' KB';
    }
}
