<?php

namespace App\Console\Commands;

use App\Services\Legacy\LegacyEmployeeImportService;
use Illuminate\Console\Command;
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
