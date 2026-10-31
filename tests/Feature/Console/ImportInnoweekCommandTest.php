<?php

namespace Tests\Feature\Console;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Group;
use App\Models\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportInnoweekCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $dumpPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dumpPath = tempnam(sys_get_temp_dir(), 'innoweek').'.sql';
        file_put_contents($this->dumpPath, $this->dump());
    }

    protected function tearDown(): void
    {
        @unlink($this->dumpPath);

        parent::tearDown();
    }

    public function test_imports_users_with_numbers_as_visitors_for_live_pages(): void
    {
        config(['app.timezone' => 'Asia/Tashkent']);

        $this->artisan('innoweek:import', ['path' => $this->dumpPath])
            ->expectsOutputToContain('"Innoweek 2026" bo‘limiga 4 mehmon: 4 yangi, 0 yangilangan.')
            ->expectsOutputToContain('Davlati ko‘rsatilmagan 1 kishi O‘zbekiston deb olindi.')
            ->expectsOutputToContain('Sovet Ittifoqi')
            ->assertSuccessful();

        $department = Department::query()->where('name', 'Innoweek 2026')->firstOrFail();
        $groups = Group::query()->where('department_id', $department->id)->orderBy('id')->pluck('id', 'name');
        // Guruh = professions.name_uz; faol toifalar professions tartibida oldindan yaratiladi, nofaol "Media" — yo‘q.
        $this->assertSame(['Ishtirokchi', 'Homiy', 'OAV (Media)'], $groups->keys()->all());
        $schedule = Schedule::query()->where('name', 'Standart')->firstOrFail();

        $this->assertSame(4, Employee::query()->withVisitors()->count());
        $this->assertSame(4, Employee::query()->withVisitors()->where('is_visitor', true)->count());
        // Oddiy sahifalar uchun mehmonlar ko‘rinmaydi.
        $this->assertSame(0, Employee::query()->count());

        // Ikki raqamli foydalanuvchi — 2026 yilgi raqam olinadi; karta boshidagi nollar saqlanadi.
        $ali = Employee::query()->withVisitors()->where('card', '0003700000')->firstOrFail();
        $this->assertSame('Valiyev Ali G‘ani o\'g\'li', $ali->name);
        $this->assertSame(Employee::GENDER_MALE, $ali->gender);
        $this->assertSame('UZ', $ali->country);
        $this->assertSame('Talaba', $ali->position);
        $this->assertSame($department->id, $ali->department_id);
        $this->assertSame($groups['Ishtirokchi'], $ali->group_id);
        $this->assertSame($schedule->id, $ali->schedule_id);
        $this->assertSame('2026-01-01 00:00:00', $ali->valid_from_dt->format('Y-m-d H:i:s'));
        $this->assertSame('2026-12-31 23:59:59', $ali->valid_to_dt->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-24 01:30:00', $ali->created_at->format('Y-m-d H:i:s'));
        $this->assertFalse(Employee::query()->withVisitors()->where('card', '0003600000')->exists());

        $kim = Employee::query()->withVisitors()->where('card', '0003700001')->firstOrFail();
        $this->assertSame('Min-jun Kim', $kim->name);
        $this->assertSame('KR', $kim->country);
        $this->assertSame(Employee::GENDER_FEMALE, $kim->gender);
        $this->assertSame('CEO, "Tech" (Seoul); 1/2', $kim->position);
        $this->assertSame($groups['Homiy'], $kim->group_id);

        $olim = Employee::query()->withVisitors()->where('card', '0003700002')->firstOrFail();
        $this->assertSame('UZ', $olim->country);
        $this->assertNull($olim->gender);
        $this->assertNull($olim->position);
        $this->assertSame($groups['OAV (Media)'], $olim->group_id);

        $soviet = Employee::query()->withVisitors()->where('card', '0003700004')->firstOrFail();
        $this->assertSame('XX', $soviet->country);
        $this->assertTrue($soviet->isForeign());
        // profession_id bo‘sh — standart guruh.
        $this->assertSame($groups['Ishtirokchi'], $soviet->group_id);

        // O‘chirilgan foydalanuvchi va raqamsiz foydalanuvchi yuklanmaydi.
        $this->assertFalse(Employee::query()->withVisitors()->where('card', '0003700003')->exists());
        $this->assertFalse(Employee::query()->withVisitors()->where('name', 'like', 'Foydalanuvchi%')->exists());
    }

    public function test_reimport_updates_visitors_and_never_touches_staff_with_same_card(): void
    {
        $staff = Employee::factory()->create([
            'card' => '0003700001',
            'name' => 'Oddiy xodim',
        ]);

        $this->artisan('innoweek:import', ['path' => $this->dumpPath])
            ->expectsOutputToContain('3 mehmon: 3 yangi, 0 yangilangan.')
            ->expectsOutputToContain('1 ta karta oddiy xodimga tegishli')
            ->assertSuccessful();

        $staff->refresh();
        $this->assertSame('Oddiy xodim', $staff->name);
        $this->assertFalse($staff->is_visitor);
        $this->assertNotSame('Innoweek 2026', $staff->department->name);

        $visitor = Employee::query()->withVisitors()->where('card', '0003700000')->firstOrFail();
        $visitor->forceFill(['name' => 'Qo‘lda o‘zgargan', 'created_at' => '2026-01-05 10:00:00'])->save();

        $this->artisan('innoweek:import', ['path' => $this->dumpPath, '--department' => 'Innoweek 2026'])
            ->expectsOutputToContain('3 mehmon: 0 yangi, 3 yangilangan.')
            ->assertSuccessful();

        $visitor->refresh();
        $this->assertSame('Valiyev Ali G‘ani o\'g\'li', $visitor->name);
        $this->assertSame('2026-01-05 10:00:00', $visitor->created_at->format('Y-m-d H:i:s'));

        $this->assertSame(4, Employee::query()->withVisitors()->count());
        $this->assertSame(1, Department::query()->where('name', 'Innoweek 2026')->count());
        // Qayta yuklashda faol toifa guruhlari takrorlanmaydi.
        $this->assertSame(['Ishtirokchi', 'Homiy', 'OAV (Media)'], Group::query()->orderBy('id')->pluck('name')->all());
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->artisan('innoweek:import', ['path' => $this->dumpPath, '--dry-run' => true])
            ->expectsOutputToContain('bazaga hech narsa yozilmadi')
            ->expectsOutputToContain('4 mehmon: 4 yangi, 0 yangilangan.')
            ->assertSuccessful();

        $this->assertSame(0, Employee::query()->withVisitors()->count());
        $this->assertSame(0, Department::query()->count());
        $this->assertSame(0, Group::query()->count());
    }

    public function test_missing_file_fails(): void
    {
        $this->artisan('innoweek:import', ['path' => $this->dumpPath.'.yoq'])
            ->expectsOutputToContain('Fayl topilmadi')
            ->assertFailed();
    }

    private function dump(): string
    {
        return <<<'SQL'
-- phpMyAdmin SQL Dump
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

CREATE TABLE `archive_years` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED DEFAULT NULL,
  `year` smallint UNSIGNED DEFAULT NULL,
  `slogan_uz` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `archive_years` (`id`, `user_id`, `year`, `slogan_uz`) VALUES
(8, 1, 2025, 'Chegarasiz g''oyalar'),
(9, 2, 2026, 'O\'zgarayotgan dunyo, (ilg\'or) innovatsiyalar; test');

INSERT INTO `countries` (`id`, `name_uz`, `name_en`, `status`) VALUES
(1, 'O\'zbekiston', 'Uzbekistan', 'active'),
(128, 'Koreya, Rep.', 'Korea, Rep.', 'active'),
(216, 'Sovet Ittifoqi', 'Soviet Union', 'active');

INSERT INTO `news` (`id`, `title`) VALUES
(1, 'INSERT INTO `users` emas');

INSERT INTO `numbers` (`id`, `full_number`, `user_id`, `archive_id`, `created_at`, `updated_at`) VALUES
(1, '0003600000', 1, 8, NULL, NULL),
(2, '0003700000', 1, 9, NULL, NULL),
(3, '0003700001', 2, 9, NULL, NULL),
(4, '0003700002', 3, 9, NULL, NULL),
(5, '0003700003', 4, 9, NULL, NULL),
(6, '0003700004', 6, 9, NULL, NULL),
(7, '0003700005', NULL, 9, NULL, NULL);

INSERT INTO `professions` (`id`, `user_id`, `name_uz`, `name_ru`, `name_en`, `status`, `created_at`, `updated_at`) VALUES
(12, 1, 'Media', 'Media', 'Media', 'inactive', NULL, NULL),
(13, 1, 'Ishtirokchi', 'Участник', 'Participant', 'active', NULL, NULL),
(14, 1, 'Homiy', 'Спонсор', 'Sponsor', 'active', NULL, NULL),
(15, 1, 'OAV (Media)', 'СМИ', 'Media', 'active', NULL, NULL);

INSERT INTO `users` (`id`, `user_type`, `profession_id`, `first_name`, `last_name`, `middle_name`, `position`, `gender`, `country_id`, `status`, `created_at`, `password`) VALUES
(1, 5, 13, 'Ali', 'Valiyev', 'G‘ani o\'g\'li', 'Talaba', 1, 1, 'active', '2026-10-23 20:30:00', '$2y$10$abc'),
(2, 4, 14, 'Kim', 'Min-jun', NULL, 'CEO, \"Tech\" (Seoul); 1/2', 2, 128, 'active', '2026-10-20 05:00:00', 'x'),
(3, 1, 15, 'Olim', 'Karimov', NULL, '  ', NULL, NULL, 'active', NULL, 'x'),
(4, 0, 13, 'Ivan', 'Petrov', NULL, NULL, 1, 216, 'deleted', '2026-10-01 00:00:00', 'x'),
(5, 5, 13, 'Raqamsiz', 'Foydalanuvchi', NULL, NULL, 1, 1, 'active', NULL, 'x'),
(6, 0, NULL, 'Sarvar', 'Soviet', NULL, NULL, 1, 216, 'active', NULL, 'x');

COMMIT;
SQL;
    }
}
