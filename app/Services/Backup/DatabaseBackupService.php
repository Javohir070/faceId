<?php

namespace App\Services\Backup;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Bazaning siqilgan zaxira nusxasi: mysqldump → {baza}-YYYY-MM-DD-HHMMSS.sql.gz, eski nusxalar o‘chiriladi.
 *
 * Dumpda JSHSHIR va yuz rasmlari bor: papka 0700, fayllar 0600. Parol buyruq satriga chiqmaydi —
 * vaqtinchalik credentials fayli (--defaults-extra-file) zaxira papkasining o‘zida yoziladi va darhol o‘chiriladi.
 */
class DatabaseBackupService
{
    private const CHUNK_BYTES = 1048576;

    private const COMPLETED_MARKER = '-- Dump completed';

    public function run(): DatabaseBackupResult
    {
        $connection = (string) config('db_backup.connection');
        $config = (array) config("database.connections.{$connection}", []);

        if (! in_array($config['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException("«{$connection}» ulanishi MySQL/MariaDB emas, zaxira faqat MySQL uchun.");
        }

        $database = (string) ($config['database'] ?? '');

        if ($database === '') {
            throw new RuntimeException('Baza nomi sozlanmagan (DB_DATABASE).');
        }

        // mysqldump ham shu umask'ni oladi: vaqtinchalik .sql fayl ham boshqalarga yopiq yaratiladi.
        $previousUmask = umask(0077);

        try {
            $directory = $this->prepareDirectory();
            $prefix = $this->filePrefix($database);
            $filename = sprintf('%s-%s.sql.gz', $prefix, now()->format('Y-m-d-His'));
            $target = $directory.DIRECTORY_SEPARATOR.$filename;

            $this->createBackup($config, $database, $directory, $target);
        } finally {
            umask($previousUmask);
        }

        [$kept, $deleted] = $this->rotate($directory, $prefix, $filename);
        $this->shareWithGroup($directory, $prefix);

        clearstatcache(true, $target);

        return new DatabaseBackupResult($target, $filename, (int) filesize($target), $kept, $deleted);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function createBackup(array $config, string $database, string $directory, string $target): void
    {
        $token = Str::random(16);
        $credentials = $directory.DIRECTORY_SEPARATOR.".client-{$token}.cnf";
        $raw = $directory.DIRECTORY_SEPARATOR.".dump-{$token}.sql";
        $partial = $target.'.part';

        try {
            $this->writePrivateFile($credentials, $this->credentialsFile($config));
            $this->dump($credentials, $raw, $database);
            $this->assertComplete($raw);
            $this->compress($raw, $partial);
            $this->assertCompressedCopy($raw, $partial);

            if (! rename($partial, $target)) {
                throw new RuntimeException('Zaxira faylini saqlab bo‘lmadi.');
            }

            @chmod($target, 0600);
        } finally {
            foreach ([$credentials, $raw, $partial] as $path) {
                if (is_file($path)) {
                    @unlink($path);
                }
            }
        }
    }

    /**
     * Panel uchun: shu bazaning zaxiralari, yangisi birinchi. `unreadable` — papka bor, lekin veb-server uni
     * o‘qiy olmaydi (serverda DB_BACKUP_GROUP sozlanmagan).
     *
     * @return array{unreadable: bool, items: list<array{filename: string, bytes: int, created_at: string}>}
     */
    public function backups(): array
    {
        $directory = $this->directory();

        if (! is_dir($directory) || ! is_readable($directory)) {
            return ['unreadable' => is_dir($directory), 'items' => []];
        }

        $items = [];

        foreach ($this->backupFiles($directory, $this->filePrefix($this->databaseName())) as [$stamp, $file]) {
            $items[] = [
                'filename' => $file,
                'bytes' => (int) @filesize($directory.DIRECTORY_SEPARATOR.$file),
                'created_at' => Carbon::createFromFormat('Y-m-d-His', $stamp)->format('Y-m-d H:i:s'),
            ];
        }

        return ['unreadable' => false, 'items' => $items];
    }

    /**
     * Yuklab olish uchun to‘liq yo‘l: nom papkadagi shu bazaning zaxira fayllaridan biriga aynan teng bo‘lishi
     * kerak (yo‘l bilan chiqib bo‘lmaydi). Topilmasa yoki o‘qib bo‘lmasa — null.
     */
    public function downloadPath(string $filename): ?string
    {
        $directory = $this->directory();

        foreach ($this->backupFiles($directory, $this->filePrefix($this->databaseName())) as [, $file]) {
            if ($file === $filename) {
                $path = $directory.DIRECTORY_SEPARATOR.$file;

                return is_readable($path) ? $path : null;
            }
        }

        return null;
    }

    private function directory(): string
    {
        return rtrim((string) config('db_backup.path'), '/\\');
    }

    private function databaseName(): string
    {
        return (string) config('database.connections.'.config('db_backup.connection').'.database', '');
    }

    private function filePrefix(string $database): string
    {
        return (string) preg_replace('/[^A-Za-z0-9_.-]/', '_', $database);
    }

    private function prepareDirectory(): string
    {
        $directory = $this->directory();

        File::ensureDirectoryExists($directory, 0700);
        @chmod($directory, 0700);

        if (! is_writable($directory)) {
            throw new RuntimeException("Zaxira papkasiga yozib bo‘lmaydi: {$directory}");
        }

        return $directory;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function credentialsFile(array $config): string
    {
        $lines = [
            '[client]',
            'user='.$this->optionValue($config['username'] ?? ''),
            'password='.$this->optionValue($config['password'] ?? ''),
            'host='.$this->optionValue($config['host'] ?? '127.0.0.1'),
            'port='.$this->optionValue($config['port'] ?? '3306'),
        ];

        if ((string) ($config['unix_socket'] ?? '') !== '') {
            $lines[] = 'socket='.$this->optionValue($config['unix_socket']);
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * MySQL option fayli qiymati: qo‘shtirnoq ichida # va ; izoh hisoblanmaydi, \ va " ekranlanadi.
     */
    private function optionValue(mixed $value): string
    {
        return '"'.strtr((string) $value, [
            '\\' => '\\\\',
            '"' => '\\"',
            "\n" => '\\n',
            "\r" => '\\r',
            "\t" => '\\t',
        ]).'"';
    }

    private function writePrivateFile(string $path, string $contents): void
    {
        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException('Vaqtinchalik ulanish faylini yozib bo‘lmadi.');
        }

        @chmod($path, 0600);
    }

    private function dump(string $credentials, string $raw, string $database): void
    {
        $result = Process::timeout(max((int) config('db_backup.timeout', 3600), 60))->run([
            $this->binary(),
            '--defaults-extra-file='.$credentials,
            '--single-transaction',
            '--quick',
            '--no-tablespaces',
            '--default-character-set=utf8mb4',
            '--result-file='.$raw,
            $database,
        ]);

        if ($result->failed()) {
            $output = trim($result->errorOutput()) ?: trim($result->output());

            throw new RuntimeException(sprintf(
                'mysqldump xato bilan tugadi (kod %s): %s',
                $result->exitCode() ?? '?',
                Str::limit($output, 300),
            ));
        }
    }

    private function binary(): string
    {
        $path = trim((string) config('db_backup.dump_binary_path'));

        return $path === '' ? 'mysqldump' : rtrim($path, '/\\').'/mysqldump';
    }

    private function assertComplete(string $raw): void
    {
        clearstatcache(true, $raw);
        $size = is_file($raw) ? (int) filesize($raw) : 0;

        if ($size === 0) {
            throw new RuntimeException('mysqldump bo‘sh natija qaytardi.');
        }

        $handle = fopen($raw, 'rb');

        if ($handle === false) {
            throw new RuntimeException('Dump faylini o‘qib bo‘lmadi.');
        }

        fseek($handle, -min($size, 1024), SEEK_END);
        $tail = (string) fread($handle, 1024);
        fclose($handle);

        if (! str_contains($tail, self::COMPLETED_MARKER)) {
            throw new RuntimeException('Dump oxirigacha yozilmagan («'.self::COMPLETED_MARKER.'» qatori yo‘q).');
        }
    }

    protected function compress(string $raw, string $partial): void
    {
        $input = fopen($raw, 'rb');
        $output = gzopen($partial, 'wb6');

        if ($input === false || $output === false) {
            throw new RuntimeException('Zaxirani siqib bo‘lmadi: fayl ochilmadi.');
        }

        try {
            while (! feof($input)) {
                $chunk = fread($input, self::CHUNK_BYTES);

                // Xatoda gzwrite false emas, 0 qaytaradi: yozilgan baytlar soni tekshiriladi.
                if ($chunk === false || ($chunk !== '' && gzwrite($output, $chunk) !== strlen($chunk))) {
                    throw new RuntimeException('Zaxirani siqib bo‘lmadi: yozishda xato (disk to‘lgan bo‘lishi mumkin).');
                }
            }
        } finally {
            fclose($input);
            $closed = gzclose($output);
        }

        if (! $closed) {
            throw new RuntimeException('Zaxirani siqib bo‘lmadi: fayl yopilmadi (disk to‘lgan bo‘lishi mumkin).');
        }
    }

    /**
     * gzclose yozish xatosini bildirmaydi (doim true), shuning uchun siqilgan fayl qaytadan o‘qiladi:
     * ochilgan hajm xom dump bilan teng va oxirida «-- Dump completed» bo‘lishi kerak.
     */
    private function assertCompressedCopy(string $raw, string $partial): void
    {
        clearstatcache(true, $raw);
        $expected = (int) filesize($raw);
        $input = gzopen($partial, 'rb');

        if ($input === false) {
            throw new RuntimeException('Siqilgan zaxirani tekshirib bo‘lmadi: fayl ochilmadi.');
        }

        $bytes = 0;
        $tail = '';

        try {
            while (! gzeof($input)) {
                $chunk = gzread($input, self::CHUNK_BYTES);

                if ($chunk === false || $chunk === '') {
                    break;
                }

                $bytes += strlen($chunk);
                $tail = substr($tail.$chunk, -1024);
            }
        } finally {
            gzclose($input);
        }

        if ($bytes !== $expected || ! str_contains($tail, self::COMPLETED_MARKER)) {
            throw new RuntimeException('Siqilgan zaxira buzilgan (disk to‘lgan bo‘lishi mumkin), saqlanmadi.');
        }
    }

    /**
     * Yangi nusxa doim qoladi; qolganlari nomidagi vaqt bo‘yicha, eng yangilari saqlanadi.
     * Faqat shu bazaning {baza}-YYYY-MM-DD-HHMMSS.sql.gz fayllariga tegiladi (papka boshqa tizim bilan umumiy bo‘lsa ham).
     *
     * @return array{0: int, 1: int} [saqlangan, o‘chirilgan]
     */
    private function rotate(string $directory, string $prefix, string $fresh): array
    {
        $keep = max((int) config('db_backup.keep', 12), 1);
        $older = array_values(array_filter(
            $this->backupFiles($directory, $prefix),
            fn (array $backup): bool => $backup[1] !== $fresh,
        ));

        $deleted = 0;

        foreach (array_slice($older, $keep - 1) as [, $file]) {
            if (@unlink($directory.DIRECTORY_SEPARATOR.$file)) {
                $deleted++;
            }
        }

        return [count($older) - $deleted + 1, $deleted];
    }

    /**
     * Shu bazaning zaxira fayllari, yangisi birinchi.
     *
     * @return list<array{0: string, 1: string}> [nomdagi vaqt YYYY-MM-DD-HHMMSS, fayl nomi]
     */
    private function backupFiles(string $directory, string $prefix): array
    {
        $pattern = '/^'.preg_quote($prefix, '/').'-(\d{4}-\d{2}-\d{2}-\d{6})\.sql\.gz$/';
        $backups = [];

        foreach (@scandir($directory) ?: [] as $file) {
            if (preg_match($pattern, $file, $matches) === 1 && is_file($directory.DIRECTORY_SEPARATOR.$file)) {
                $backups[] = [$matches[1], $file];
            }
        }

        rsort($backups);

        return $backups;
    }

    /**
     * DB_BACKUP_GROUP berilsa veb-server (panel) zaxiralarni o‘qiy oladi: papka 0750, fayllar 0640, guruh — shu.
     * Eski nusxalar ham. Guruhga berib bo‘lmasa zaxira buzilmaydi, faqat ogohlantirish yoziladi.
     */
    private function shareWithGroup(string $directory, string $prefix): void
    {
        $group = trim((string) config('db_backup.group'));

        if ($group === '') {
            return;
        }

        $failed = false;
        $paths = [$directory];

        foreach ($this->backupFiles($directory, $prefix) as [, $file]) {
            $paths[] = $directory.DIRECTORY_SEPARATOR.$file;
        }

        foreach ($paths as $path) {
            @chmod($path, is_dir($path) ? 0750 : 0640);

            if (! @chgrp($path, $group)) {
                $failed = true;
            }
        }

        if ($failed) {
            Log::warning('Baza zaxirasi: fayllarni guruhga berib bo‘lmadi', ['group' => $group, 'path' => $directory]);
        }
    }
}
