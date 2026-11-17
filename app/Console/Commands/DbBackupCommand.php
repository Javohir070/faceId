<?php

namespace App\Console\Commands;

use App\Services\Backup\DatabaseBackupResult;
use App\Services\Backup\DatabaseBackupService;
use App\Services\Telegram\TelegramNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class DbBackupCommand extends Command
{
    protected $signature = 'db:backup';

    protected $description = 'Bazaning siqilgan zaxira nusxasini oladi va eski nusxalarni o‘chiradi (har oy scheduler orqali)';

    public function handle(DatabaseBackupService $backups, TelegramNotifier $telegram): int
    {
        try {
            $result = $backups->run();
        } catch (Throwable $exception) {
            $reason = Str::limit(trim($exception->getMessage()), 300);

            Log::error('Baza zaxirasi olinmadi', ['error' => $exception->getMessage()]);
            $this->error("Zaxira olinmadi: {$reason}");
            $this->notify($telegram, "❌ Baza zaxirasi olinmadi\nSabab: {$reason}");

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

        $this->notify($telegram, sprintf(
            "✅ Baza zaxirasi olindi\nFayl: %s\nHajmi: %s\nSaqlangan nusxalar: %d ta (eng ko‘pi %d)",
            $result->filename,
            self::humanSize($result),
            $result->kept,
            max((int) config('db_backup.keep', 12), 1),
        ));

        return self::SUCCESS;
    }

    /**
     * Xabar yuborilmasa ham zaxira natijasi o‘zgarmaydi (baza ishlamay qolganda oluvchilar so‘rovi ham yiqiladi).
     */
    private function notify(TelegramNotifier $telegram, string $text): void
    {
        try {
            $telegram->notifySuperAdmins($text);
        } catch (Throwable $exception) {
            Log::warning('Baza zaxirasi: Telegram xabari yuborilmadi', ['error' => $exception->getMessage()]);
        }
    }

    public static function humanSize(DatabaseBackupResult $result): string
    {
        return $result->bytes >= 1048576
            ? number_format($result->bytes / 1048576, 1, ',', ' ').' MB'
            : number_format($result->bytes / 1024, 1, ',', ' ').' KB';
    }
}
