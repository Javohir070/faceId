<?php

namespace App\Console\Commands;

use App\Services\Innoweek\InnoweekImportService;
use Illuminate\Console\Command;

class ImportInnoweekCommand extends Command
{
    protected $signature = 'innoweek:import
                            {path : Innoweek bazasining SQL dump fayli (phpMyAdmin / mysqldump)}
                            {--department= : Mehmonlar tushadigan bo‘lim (standart: "Innoweek <yil>")}
                            {--dry-run : Bazaga yozmasdan faqat natijani ko‘rsatish}';

    protected $description = 'Innoweek ishtirokchilarini (users + numbers) faqat Jonli kuzatuv va Tashrif statistikasi uchun mehmon sifatida yuklaydi';

    public function handle(InnoweekImportService $importService): int
    {
        $path = (string) $this->argument('path');

        if (! is_file($path) || ! is_readable($path)) {
            $this->error("Fayl topilmadi yoki o‘qib bo‘lmaydi: {$path}");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        try {
            $result = $importService->import($path, $this->option('department'), $dryRun);
        } catch (\Throwable $exception) {
            $this->error('Yuklab bo‘lmadi: '.$exception->getMessage());

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->warn('Sinov rejimi (--dry-run): bazaga hech narsa yozilmadi.');
        }

        $this->info(sprintf(
            'Dumpda %d foydalanuvchi, ulardan %d tasiga raqam bog‘langan. Raqami yo‘q: %d, o‘chirilgan: %d.',
            $result['users'],
            $result['numbers'],
            $result['without_number'],
            $result['skipped_deleted'],
        ));

        $this->info(sprintf(
            '"%s" bo‘limiga %d mehmon: %d yangi, %d yangilangan.',
            $result['department'],
            $result['imported'],
            $result['created'],
            $result['updated'],
        ));

        if ($result['skipped_staff'] > 0) {
            $this->warn(sprintf(
                '%d ta karta oddiy xodimga tegishli — ular o‘zgartirilmadi va mehmon qilib yuklanmadi.',
                $result['skipped_staff'],
            ));
        }

        $this->table(
            ['Guruh', 'Mehmon'],
            collect($result['groups'])->map(fn (int $count, string $group): array => [$group, $count])->values()->all(),
        );

        if ($result['no_country'] > 0) {
            $this->line(sprintf('Davlati ko‘rsatilmagan %d kishi O‘zbekiston deb olindi.', $result['no_country']));
        }

        if ($result['unknown_countries'] !== []) {
            $this->warn('Davlati aniqlanmadi (xorijiy deb olindi, kodi XX — qo‘lda tuzating):');
            $this->table(
                ['Davlat (Innoweek)', 'Mehmon'],
                collect($result['unknown_countries'])->map(fn (int $count, string $name): array => [$name, $count])->values()->all(),
            );
        }

        return self::SUCCESS;
    }
}
