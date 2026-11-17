<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class BackupPageTest extends TestCase
{
    use InteractsWithPermissions;
    use RefreshDatabase;

    private const NEWEST = 'eidora_db-2026-11-01-040000.sql.gz';

    private string $backupDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->backupDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'backup-page-test-'.uniqid();

        config([
            'db_backup.path' => $this->backupDir,
            'db_backup.keep' => 12,
            'db_backup.connection' => 'mysql',
            'database.connections.mysql.driver' => 'mysql',
            'database.connections.mysql.database' => 'eidora_db',
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->backupDir);

        parent::tearDown();
    }

    public function test_super_admin_sees_backups_newest_first(): void
    {
        $this->makeBackups();

        $this->actingAs($this->createUserWithPermissions())
            ->get(route('admin.backups.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Backups/Index')
                ->has('backups', 2)
                ->where('backups.0.filename', self::NEWEST)
                ->where('backups.0.created_at', '2026-11-01 04:00:00')
                ->where('backups.0.bytes', filesize($this->backupDir.'/'.self::NEWEST))
                ->where('backups.1.filename', 'eidora_db-2026-10-01-040000.sql.gz')
                ->where('keep', 12)
                ->where('unreadable', false)
                ->where('can.backups', true));
    }

    public function test_missing_directory_shows_empty_list(): void
    {
        $this->actingAs($this->createUserWithPermissions())
            ->get(route('admin.backups.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Backups/Index')
                ->has('backups', 0)
                ->where('unreadable', false));
    }

    public function test_non_super_admin_is_forbidden(): void
    {
        $this->makeBackups();
        $user = $this->createNonSuperAdmin();

        $this->actingAs($user)->get(route('admin.backups.index'))->assertForbidden();
        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('admin.backups.download', self::NEWEST))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('home.index'))
            ->assertInertia(fn (Assert $page) => $page->where('can.backups', false));
    }

    public function test_download_requires_password_confirmation(): void
    {
        $this->makeBackups();

        $this->actingAs($this->createUserWithPermissions())
            ->get(route('admin.backups.download', self::NEWEST))
            ->assertRedirect(route('password.confirm'));
    }

    public function test_download_returns_file_and_logs(): void
    {
        $this->makeBackups();
        $admin = $this->createUserWithPermissions();
        Log::spy();

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('admin.backups.download', self::NEWEST))
            ->assertOk()
            ->assertDownload(self::NEWEST);

        Log::shouldHaveReceived('info')
            ->withArgs(fn (string $message, array $context = []): bool => $message === 'Baza zaxirasi yuklab olindi'
                && $context['file'] === self::NEWEST
                && $context['user_id'] === $admin->id)
            ->once();
    }

    public function test_download_rejects_foreign_and_missing_files(): void
    {
        $this->makeBackups();
        file_put_contents($this->backupDir.'/eidora_db-2026-11-01-040000.sql', 'plain');
        $admin = $this->createUserWithPermissions();

        foreach ([
            'other_db-2026-11-02-040000.sql.gz',
            'notes.txt',
            'eidora_db-2026-12-01-040000.sql.gz',
            'eidora_db-2026-11-01-040000.sql',
        ] as $filename) {
            $this->actingAs($admin)
                ->withSession(['auth.password_confirmed_at' => time()])
                ->get(route('admin.backups.download', $filename))
                ->assertNotFound();
        }

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get('/admin/backups/..%2F..%2F.env/download')
            ->assertNotFound();
    }

    public function test_unreadable_directory_is_flagged(): void
    {
        if (PHP_OS_FAMILY === 'Windows' || (function_exists('posix_geteuid') && posix_geteuid() === 0)) {
            $this->markTestSkipped('Papka ruxsatlari faqat Linux/Unix da, root bo‘lmagan foydalanuvchi bilan tekshiriladi.');
        }

        $this->makeBackups();
        chmod($this->backupDir, 0000);

        try {
            $this->actingAs($this->createUserWithPermissions())
                ->get(route('admin.backups.index'))
                ->assertInertia(fn (Assert $page) => $page->has('backups', 0)->where('unreadable', true));
        } finally {
            chmod($this->backupDir, 0700);
        }
    }

    private function makeBackups(): void
    {
        File::ensureDirectoryExists($this->backupDir);
        file_put_contents($this->backupDir.'/eidora_db-2026-10-01-040000.sql.gz', gzencode('october'));
        file_put_contents($this->backupDir.'/'.self::NEWEST, gzencode('november backup'));
        file_put_contents($this->backupDir.'/other_db-2026-11-02-040000.sql.gz', gzencode('other system'));
        file_put_contents($this->backupDir.'/notes.txt', 'not a backup');
    }

    private function createNonSuperAdmin(): User
    {
        $user = $this->createUserWithPermissions();
        $user->removeRole('super-admin');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }
}
