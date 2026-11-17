<?php

namespace Tests\Feature\Console;

use App\Models\User;
use App\Services\Backup\DatabaseBackupService;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Tests\Concerns\CreatesTelegramAdmin;
use Tests\TestCase;

class DbBackupCommandTest extends TestCase
{
    use CreatesTelegramAdmin;
    use RefreshDatabase;

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

    public function test_rotation_ignores_dumps_of_other_databases(): void
    {
        config(['db_backup.keep' => 1]);
        File::ensureDirectoryExists($this->backupDir);
        file_put_contents($this->backupDir.'/eidora_db-2026-11-01-040000.sql.gz', gzencode('old'));
        file_put_contents($this->backupDir.'/biocontrol_db-2026-11-01-040000.sql.gz', gzencode('other system'));

        $this->fakeDump(self::COMPLETE_DUMP);

        $this->artisan('db:backup')->assertSuccessful();

        $this->assertSame([
            'biocontrol_db-2026-11-01-040000.sql.gz',
            'eidora_db-2026-12-01-040000.sql.gz',
        ], $this->filesInBackupDir());
    }

    public function test_blank_or_invalid_keep_falls_back_to_twelve(): void
    {
        foreach (['' => 12, 'abc' => 12, '0' => 12, '5' => 5] as $value => $expected) {
            $_ENV['DB_BACKUP_KEEP'] = $_SERVER['DB_BACKUP_KEEP'] = (string) $value;

            try {
                $config = require config_path('db_backup.php');
            } finally {
                unset($_ENV['DB_BACKUP_KEEP'], $_SERVER['DB_BACKUP_KEEP']);
            }

            $this->assertSame($expected, $config['keep'], "DB_BACKUP_KEEP=\"{$value}\"");
        }

        $_ENV['DB_BACKUP_TIMEOUT'] = $_SERVER['DB_BACKUP_TIMEOUT'] = '';

        try {
            $config = require config_path('db_backup.php');
        } finally {
            unset($_ENV['DB_BACKUP_TIMEOUT'], $_SERVER['DB_BACKUP_TIMEOUT']);
        }

        $this->assertSame(3600, $config['timeout']);
    }

    public function test_daily_spatie_backup_excludes_monthly_dumps(): void
    {
        $this->assertContains(
            storage_path('app/private/db-backups'),
            config('backup.backup.source.files.exclude'),
        );
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

    public function test_truncated_compressed_file_is_rejected(): void
    {
        File::ensureDirectoryExists($this->backupDir);
        file_put_contents($this->backupDir.'/eidora_db-2026-11-01-040000.sql.gz', gzencode('old'));
        config(['db_backup.keep' => 1]);

        // Disk to‘lganda gzwrite/gzclose xato qaytarmaydi: natijada oxiri kesilgan .gz qoladi.
        $this->app->instance(DatabaseBackupService::class, new class extends DatabaseBackupService
        {
            protected function compress(string $raw, string $partial): void
            {
                file_put_contents($partial, substr((string) gzencode((string) file_get_contents($raw)), 0, -12));
            }
        });
        $this->fakeDump(self::COMPLETE_DUMP);

        $this->artisan('db:backup')
            ->expectsOutputToContain('Zaxira olinmadi')
            ->assertFailed();

        $this->assertSame(['eidora_db-2026-11-01-040000.sql.gz'], $this->filesInBackupDir());
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

    public function test_success_is_reported_to_super_admins_only(): void
    {
        $this->fakeTelegram();
        $this->createTelegramSuperAdmin(['telegram_chat_id' => '111']);
        $this->createTelegramSuperAdmin(['telegram_chat_id' => '222', 'telegram_notify_prefs' => 'device']);
        User::factory()->create(['telegram_chat_id' => '333', 'telegram_notify_prefs' => 'all']);
        $this->fakeDump(self::COMPLETE_DUMP);

        $this->artisan('db:backup')->assertSuccessful();

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => $request['chat_id'] === '111'
            && str_contains((string) $request['text'], 'Baza zaxirasi olindi')
            && str_contains((string) $request['text'], 'eidora_db-2026-12-01-040000.sql.gz')
            && str_contains((string) $request['text'], 'eng ko‘pi 12'));
    }

    public function test_failure_is_reported_to_super_admins(): void
    {
        $this->fakeTelegram();
        $this->createTelegramSuperAdmin(['telegram_chat_id' => '111']);
        Process::fake(fn () => Process::result('', 'mysqldump: Got error: 1045: Access denied for user', 2));

        $this->artisan('db:backup')->assertFailed();

        Http::assertSent(fn (Request $request): bool => $request['chat_id'] === '111'
            && str_contains((string) $request['text'], 'Baza zaxirasi olinmadi')
            && str_contains((string) $request['text'], 'Access denied')
            && ! str_contains((string) $request['text'], 's3cr'));
    }

    public function test_telegram_error_does_not_change_exit_code(): void
    {
        $this->fakeTelegram(500);
        $this->createTelegramSuperAdmin(['telegram_chat_id' => '111']);
        $this->fakeDump(self::COMPLETE_DUMP);

        $this->artisan('db:backup')->assertSuccessful();

        Http::assertSentCount(1);
    }

    public function test_backup_is_scheduled_monthly(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn (Event $event): bool => str_contains((string) $event->command, 'db:backup'));

        $this->assertCount(1, $events);
        $this->assertSame('0 4 1 * *', $events->first()->expression);
        $this->assertTrue($events->first()->withoutOverlapping);
    }

    private function fakeTelegram(int $status = 200): void
    {
        config(['telegram.token' => 'test-token']);

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => $status === 200], $status),
        ]);
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
