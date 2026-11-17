<?php

namespace Tests\Feature\Console;

use Illuminate\Process\PendingProcess;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class DbBackupCommandTest extends TestCase
{
    private const PASSWORD = 's3cr"et\\pa#ss';

    private const COMPLETE_DUMP = "-- MySQL dump\nINSERT INTO `employees` VALUES (1);\n-- Dump completed on 2026-12-01  4:00:00\n";

    private string $backupDir;

    private ?string $capturedCredentials = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->backupDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'db-backup-test-'.uniqid();

        config([
            'db_backup.path' => $this->backupDir,
            'db_backup.keep' => 12,
            'db_backup.connection' => 'mysql',
            'db_backup.dump_binary_path' => '',
            'database.connections.mysql' => [
                'driver' => 'mysql',
                'host' => '127.0.0.1',
                'port' => '3306',
                'database' => 'eidora_db',
                'username' => 'eidora',
                'password' => self::PASSWORD,
                'unix_socket' => '',
            ],
        ]);

        $this->travelTo(Carbon::parse('2026-12-01 04:00:00'));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->backupDir);

        parent::tearDown();
    }

    public function test_creates_gzipped_dump_without_password_on_command_line(): void
    {
        $this->fakeDump(self::COMPLETE_DUMP);

        $this->artisan('db:backup')
            ->expectsOutputToContain('Zaxira olindi: eidora_db-2026-12-01-040000.sql.gz')
            ->assertSuccessful();

        $this->assertSame(['eidora_db-2026-12-01-040000.sql.gz'], $this->filesInBackupDir());
        $this->assertStringContainsString(
            'INSERT INTO `employees`',
            gzdecode((string) file_get_contents($this->backupDir.'/eidora_db-2026-12-01-040000.sql.gz')),
        );

        Process::assertRan(function (PendingProcess $process): bool {
            $command = (array) $process->command;

            return str_starts_with((string) $command[1], '--defaults-extra-file=')
                && in_array('--single-transaction', $command, true)
                && in_array('--no-tablespaces', $command, true)
                && end($command) === 'eidora_db'
                && ! str_contains(implode(' ', $command), 's3cr');
        });

        $this->assertNotNull($this->capturedCredentials);
        $this->assertStringContainsString('user="eidora"', $this->capturedCredentials);
        $this->assertStringContainsString('password="s3cr\"et\\\\pa#ss"', $this->capturedCredentials);
    }

    public function test_keeps_only_newest_copies(): void
    {
        config(['db_backup.keep' => 3]);
        File::ensureDirectoryExists($this->backupDir);

        foreach (['2026-08-01', '2026-09-01', '2026-10-01', '2026-11-01'] as $day) {
            file_put_contents($this->backupDir."/eidora_db-{$day}-040000.sql.gz", gzencode('old'));
        }
        file_put_contents($this->backupDir.'/notes.txt', 'keep me');
        file_put_contents($this->backupDir.'/other.gz', 'keep me');

        $this->fakeDump(self::COMPLETE_DUMP);

        $this->artisan('db:backup')->assertSuccessful();

        $this->assertSame([
            'eidora_db-2026-10-01-040000.sql.gz',
            'eidora_db-2026-11-01-040000.sql.gz',
            'eidora_db-2026-12-01-040000.sql.gz',
            'notes.txt',
            'other.gz',
        ], $this->filesInBackupDir());
    }

    public function test_failed_dump_keeps_old_backups(): void
    {
        File::ensureDirectoryExists($this->backupDir);
        file_put_contents($this->backupDir.'/eidora_db-2026-11-01-040000.sql.gz', gzencode('old'));

        Process::fake(fn () => Process::result('', 'mysqldump: Got error: 1045: Access denied for user', 2));

        $this->artisan('db:backup')
            ->expectsOutputToContain('Zaxira olinmadi: mysqldump xato bilan tugadi (kod 2): mysqldump: Got error: 1045: Access denied')
            ->assertFailed();

        $this->assertSame(['eidora_db-2026-11-01-040000.sql.gz'], $this->filesInBackupDir());
    }

    public function test_incomplete_dump_is_rejected(): void
    {
        $this->fakeDump("-- MySQL dump\nINSERT INTO `employees` VALUES (1);\n");

        $this->artisan('db:backup')
            ->expectsOutputToContain('Zaxira olinmadi')
            ->assertFailed();

        $this->assertSame([], $this->filesInBackupDir());
    }

    public function test_non_mysql_connection_fails_without_running_dump(): void
    {
        Process::fake();
        config(['db_backup.connection' => 'sqlite']);

        $this->artisan('db:backup')
            ->expectsOutputToContain('Zaxira olinmadi')
            ->assertFailed();

        Process::assertNothingRan();
    }

    /**
     * mysqldump o‘rniga: --result-file ga dump yozadi va credentials faylini eslab qoladi.
     */
    private function fakeDump(string $dump): void
    {
        Process::fake(function (PendingProcess $process) use ($dump) {
            foreach ((array) $process->command as $argument) {
                if (str_starts_with((string) $argument, '--result-file=')) {
                    file_put_contents(substr((string) $argument, strlen('--result-file=')), $dump);
                }

                if (str_starts_with((string) $argument, '--defaults-extra-file=')) {
                    $this->capturedCredentials = (string) file_get_contents(
                        substr((string) $argument, strlen('--defaults-extra-file=')),
                    );
                }
            }

            return Process::result();
        });
    }

    /**
     * @return list<string>
     */
    private function filesInBackupDir(): array
    {
        if (! is_dir($this->backupDir)) {
            return [];
        }

        $files = array_values(array_diff(scandir($this->backupDir) ?: [], ['.', '..']));
        sort($files);

        return $files;
    }
}
