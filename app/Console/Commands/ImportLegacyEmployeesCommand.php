<?php

namespace App\Console\Commands;

use App\Services\Legacy\LegacyEmployeeImportService;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Eski Biocontrol tizimidan bir martalik ko‘chirish. Tartib (docs/legacy-import.md):
 *   1) eski dump vaqtinchalik bazaga yuklanadi (masalan biocontrol_legacy);
 *   2) --dry-run bilan nima bo‘lishi ko‘riladi;
 *   3) --dry-run siz haqiqatda yoziladi.
 */
class ImportLegacyEmployeesCommand extends Command
{
    protected $signature = 'legacy:import-employees
        {--departments= : Eski tizimdagi ko‘chiriladigan bo‘lim ID lari, vergul bilan (masalan 5,7,8,14)}
        {--database=biocontrol_legacy : Eski dump yuklangan baza nomi (shu MySQL serverda)}
        {--connection= : Tayyor ulanish nomi (testlar uchun; berilsa --database e’tiborga olinmaydi)}
        {--dry-run : Hech narsa yozilmaydi, faqat nima bo‘lishi ko‘rsatiladi}
        {--force : Tasdiqlash so‘ralmaydi}';

    protected $description = 'Eski Biocontrol bazasidan bo‘lim, guruh, xodim va yuz rasmlarini ko‘chiradi (kelgan-ketgansiz)';

    public function handle(LegacyEmployeeImportService $service): int
    {
        $departments = array_values(array_filter(
            array_map('intval', explode(',', (string) $this->option('departments'))),
            fn (int $id): bool => $id > 0,
        ));

        if ($departments === []) {
            $this->error('--departments berilmagan. Masalan: --departments=5,7,8,14');

            return self::FAILURE;
        }

        $connection = $this->option('connection') ?: $this->legacyConnection((string) $this->option('database'));
        $dryRun = (bool) $this->option('dry-run');

        // Tasdiqlash so‘rashdan oldin: baza yo‘q / huquq yo‘q / dump yuklanmagan bo‘lsa — keyingi qadam aytiladi.
        $problem = $this->legacyDatabaseProblem($connection);

        if ($problem !== null) {
            $this->error($problem);

            return self::FAILURE;
        }

        if (! $dryRun && ! $this->option('force')
            && ! $this->confirm('Ma’lumotlar haqiqatda yoziladi. Avval --dry-run bilan tekshirdingizmi? Davom etilsinmi?')) {
            $this->warn('Bekor qilindi.');

            return self::FAILURE;
        }

        try {
            $result = $service->import($connection, $departments, $dryRun);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line($dryRun
            ? '<comment>SINOV (--dry-run): hech narsa yozilmadi, quyida nima bo‘lishi ko‘rsatilgan.</comment>'
            : '<info>Ko‘chirish tugadi.</info>');

        $this->table(['', 'Natija'], [
            ['Bo‘limlar', sprintf('%d yangi, %d mavjudiga qo‘shildi', $result['departments']['created'], $result['departments']['matched'])],
            ['Guruhlar', sprintf('%d yangi, %d mavjud, %d bo‘lim nomidagi tepa guruh tashlandi', $result['groups']['created'], $result['groups']['matched'], $result['groups']['skipped_roots'])],
            ['Xodimlar', sprintf('%d yangi, %d yangilandi, %d o‘zgarishsiz', $result['employees']['created'], $result['employees']['updated'], $result['employees']['unchanged'])],
            ['Yuz rasmlari', sprintf('%d qo‘shildi, %d tasida rasm bor edi, %d yo‘q, %d yaroqsiz', $result['faces']['added'], $result['faces']['kept'], $result['faces']['missing'], $result['faces']['invalid'])],
            ['Muddati tugaganlar', sprintf('%d ta (Telegram\'ga eski xabar ketmaydi)', $result['marked_expired'])],
            ['Ogohlantirishlar', sprintf('%d ta%s', count($result['warnings']), $result['warnings'] !== [] && ! $this->output->isVerbose() ? ' (ro‘yxat uchun -v)' : '')],
        ]);

        if ($this->output->isVerbose()) {
            foreach ($result['warnings'] as $warning) {
                $this->line(sprintf('  #%d %s: %s', $warning['legacy_id'], $warning['name'], $warning['reason']));
            }
        }

        if (! $dryRun) {
            $this->line('Terminallarga hech narsa yuborilmadi: xodimlarni Sinxronizatsiya sahifasidan yuboring.');
        }

        return self::SUCCESS;
    }

    /**
     * Serverdagi odatiy xatolar uchun xom xato izi o‘rniga nima qilish kerakligi (docs/legacy-import.md, 3-qadam).
     */
    private function legacyDatabaseProblem(string $connection): ?string
    {
        $database = (string) config("database.connections.{$connection}.database");
        $user = (string) config("database.connections.{$connection}.username");

        try {
            DB::connection($connection)->table('employees')->limit(1)->count();

            return null;
        } catch (QueryException $exception) {
            $code = (int) ($exception->errorInfo[1] ?? 0);

            if ($code === 0 && preg_match('/\[(\d{4})]/', $exception->getMessage(), $matches) === 1) {
                $code = (int) $matches[1];
            }

            if (str_contains($exception->getMessage(), 'no such table')) {
                $code = 1146;
            }

            return match ($code) {
                1049 => "«{$database}» bazasi yo‘q. Yarating va eski dump'ni yuklang:\n"
                    ."  mysql -e \"CREATE DATABASE {$database} CHARACTER SET utf8mb4\"\n"
                    ."  mysql {$database} < /tmp/biocontrol_legacy.sql",
                1044, 1142 => "«{$user}» foydalanuvchisida «{$database}» ni o‘qish huquqi yo‘q:\n"
                    ."  mysql -e \"GRANT SELECT ON {$database}.* TO '{$user}'@'%'; FLUSH PRIVILEGES;\"\n"
                    .'  (host boshqa bo‘lsa: SELECT user, host FROM mysql.user;)',
                1146 => "«{$database}» bazasida eski tizim jadvallari yo‘q — dump yuklanmagan:\n"
                    ."  mysql {$database} < /tmp/biocontrol_legacy.sql",
                default => 'Eski bazaga ulanib bo‘lmadi: '.$exception->getMessage(),
            };
        }
    }

    /**
     * Joriy MySQL ulanishining nusxasi, faqat baza nomi boshqa — .env o‘zgartirilmaydi.
     */
    private function legacyConnection(string $database): string
    {
        $default = (string) config('database.default');

        config(['database.connections.legacy_import' => array_merge(
            (array) config("database.connections.{$default}"),
            ['database' => $database],
        )]);
        DB::purge('legacy_import');

        return 'legacy_import';
    }
}
