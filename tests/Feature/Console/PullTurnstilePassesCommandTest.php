<?php

namespace Tests\Feature\Console;

use App\Models\Employee;
use App\Models\Report;
use App\Services\Turnstile\TurnstilePullService;
use App\Services\VisitStatsService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PullTurnstilePassesCommandTest extends TestCase
{
    use RefreshDatabase;

    private Employee $visitor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-24 11:00:00');

        Schema::connection('turnstile')->create('gecishareketleri', function (Blueprint $table) {
            $table->increments('submission_id');
            $table->smallInteger('durum')->nullable();
            $table->string('kartid', 10)->nullable();
            $table->smallInteger('fuarid')->nullable();
            $table->dateTime('tarih');
            $table->string('cihaz_yon', 1)->nullable();
            $table->string('cihaz_adi', 250)->nullable();
            $table->smallInteger('hatakodu')->nullable();
            $table->smallInteger('ziyaretcimi')->nullable();
            $table->smallInteger('katilimcimi')->nullable();
            $table->smallInteger('personelmi')->nullable();
            $table->string('cihaz_id', 10)->nullable();
            $table->string('cihaz_ip', 15)->nullable();
            $table->string('cihaz_giris', 10)->nullable();
            $table->smallInteger('onayli')->nullable();
            $table->string('test', 100)->nullable();
        });

        $this->visitor = Employee::factory()->visitor()->create([
            'name' => 'Innoweek Mehmon',
            'card' => '0003700000',
            'gender' => Employee::GENDER_FEMALE,
        ]);
    }

    public function test_pulls_todays_passes_into_live_stats_only(): void
    {
        $this->pass(['kartid' => '0003700000', 'tarih' => '2026-10-23 17:00:00']); // kecha — birinchi ishga tushishda olinmaydi
        $this->pass(['kartid' => '0003700000', 'tarih' => '2026-10-24 09:00:00', 'cihaz_yon' => 'g']);
        $this->pass(['kartid' => '0003700000', 'tarih' => '2026-10-24 10:00:00', 'cihaz_yon' => 'c']);
        $this->pass(['kartid' => '0003700000', 'tarih' => '2026-10-24 10:30:00', 'cihaz_yon' => 'g', 'hatakodu' => 3]);
        $this->pass(['kartid' => '0003799999', 'tarih' => '2026-10-24 10:40:00', 'cihaz_yon' => 'g']);
        $this->pass(['kartid' => '0003700000', 'tarih' => '2026-10-24 10:50:00', 'cihaz_yon' => '', 'cihaz_giris' => '1']);

        $this->artisan('turnstile:pull')
            ->expectsOutputToContain('5 o‘qildi — 3 yangi, 0 takror, 1 noma’lum karta, 1 rad etilgan, 0 kechikib qo‘shildi (kursor 6)')
            ->assertSuccessful();

        $reports = Report::query()->withVisitors()->orderBy('occurred_at')->get();
        $this->assertSame([Report::TYPE_ENTRY, Report::TYPE_EXIT, Report::TYPE_ENTRY], $reports->pluck('type')->all());
        $this->assertSame(['09:00', '10:00', '10:50'], $reports->map(fn (Report $report): string => $report->occurred_at->format('H:i'))->all());
        $this->assertTrue($reports->every(fn (Report $report): bool => $report->employee_id === $this->visitor->id));
        $this->assertSame(6, Cache::get(TurnstilePullService::CURSOR_KEY));

        // Mehmon o‘tishlari faqat jonli sahifalarda.
        $this->assertSame(0, Report::query()->count());
        $stats = app(VisitStatsService::class)->today();
        $this->assertSame(1, $stats['entered']);
        $this->assertSame(1, $stats['inside']);
        $this->assertSame(1, $stats['female']);

        // Qayta ishga tushirish — yangi qator bo‘lmasa hech narsa qo‘shilmaydi.
        $this->artisan('turnstile:pull')
            ->expectsOutputToContain('0 o‘qildi')
            ->assertSuccessful();

        $this->pass(['kartid' => '0003700000', 'tarih' => '2026-10-24 10:55:00', 'cihaz_yon' => 'c']);

        $this->artisan('turnstile:pull')
            ->expectsOutputToContain('1 o‘qildi — 1 yangi')
            ->assertSuccessful();

        $this->assertSame(4, Report::query()->withVisitors()->count());
        $this->assertSame(0, app(VisitStatsService::class)->today()['inside']);
    }

    public function test_since_option_backfills_on_first_run_and_cleared_table_resets_cursor(): void
    {
        $this->pass(['kartid' => '0003700000', 'tarih' => '2026-10-23 17:00:00']);

        $this->artisan('turnstile:pull', ['--since' => '2026-10-23'])
            ->expectsOutputToContain('1 yangi')
            ->assertSuccessful();

        // Turniket jadvali tozalanib, id lar yana 1 dan boshlandi — saqlangan kursor (50) jadvaldagi eng katta
        // id dan katta, shuning uchun nolga tushadi va yangi o‘tish o‘tkazib yuborilmaydi.
        DB::connection('turnstile')->table('gecishareketleri')->truncate();
        Cache::forever(TurnstilePullService::CURSOR_KEY, 50);
        $this->pass(['kartid' => '0003700000', 'tarih' => '2026-10-24 09:00:00']);

        $this->artisan('turnstile:pull')
            ->expectsOutputToContain('1 o‘qildi — 1 yangi')
            ->assertSuccessful();

        $this->assertSame(2, Report::query()->withVisitors()->count());
    }

    public function test_rescan_picks_up_passes_of_cards_imported_later(): void
    {
        $this->pass(['kartid' => '0003700001', 'tarih' => '2026-10-24 09:00:00']);
        $this->pass(['kartid' => '0003700000', 'tarih' => '2026-10-24 09:10:00']);

        $this->artisan('turnstile:pull')
            ->expectsOutputToContain('2 o‘qildi — 1 yangi, 0 takror, 1 noma’lum karta')
            ->assertSuccessful();

        Employee::factory()->visitor()->create(['card' => '0003700001']);

        $this->artisan('turnstile:pull', ['--rescan' => true])
            ->expectsOutputToContain('2 o‘qildi — 1 yangi, 1 takror, 0 noma’lum karta')
            ->assertSuccessful();

        $this->assertSame(2, Report::query()->withVisitors()->count());
    }

    public function test_unknown_card_pass_is_recorded_once_the_card_arrives(): void
    {
        // Joyida ro‘yxatdan o‘tgan odam Innoweek sync kelguncha turniketdan o‘tdi.
        $this->pass(['kartid' => '0003700002', 'tarih' => '2026-10-24 10:59:00']);

        $this->artisan('turnstile:pull')
            ->expectsOutputToContain('1 o‘qildi — 0 yangi, 0 takror, 1 noma’lum karta')
            ->assertSuccessful();
        $this->assertSame([1 => '0003700002'], Cache::get(TurnstilePullService::UNKNOWN_KEY));

        $newcomer = Employee::factory()->visitor()->create(['card' => '0003700002']);

        $this->artisan('turnstile:pull')
            ->expectsOutputToContain('0 o‘qildi — 0 yangi, 0 takror, 0 noma’lum karta, 0 rad etilgan, 1 kechikib qo‘shildi')
            ->assertSuccessful();

        $this->assertTrue(Report::query()->withVisitors()->where('employee_id', $newcomer->id)->exists());
        $this->assertNull(Cache::get(TurnstilePullService::UNKNOWN_KEY));
    }

    public function test_fair_filter_and_duplicates(): void
    {
        config(['turnstile.fair_id' => 12]);

        $this->pass(['kartid' => '0003700000', 'tarih' => '2026-10-24 09:00:00', 'fuarid' => 12]);
        $this->pass(['kartid' => '0003700000', 'tarih' => '2026-10-24 09:05:00', 'fuarid' => 7]);
        // Webhook/boshqa kanal allaqachon yozgan o‘tish — takror deb hisoblanadi.
        $this->pass(['kartid' => '0003700000', 'tarih' => '2026-10-24 09:00:00', 'fuarid' => 12]);

        $this->artisan('turnstile:pull')
            ->expectsOutputToContain('2 o‘qildi — 1 yangi, 1 takror')
            ->assertSuccessful();

        $this->assertSame(1, Report::query()->withVisitors()->count());
    }

    public function test_turnstile_timezone_is_converted_to_app_time(): void
    {
        // Expo Panel `tarih` ni Turkiya vaqtida (UTC+3) yozadi; ilova — Toshkent (UTC+5).
        config(['app.timezone' => 'Asia/Tashkent', 'turnstile.timezone' => 'Europe/Istanbul']);
        $defaultTimezone = date_default_timezone_get();
        date_default_timezone_set('Asia/Tashkent');
        $this->beforeApplicationDestroyed(fn () => date_default_timezone_set($defaultTimezone));
        $this->travelTo(Carbon::parse('2026-10-24 11:00:00', 'Asia/Tashkent'));

        $this->pass(['kartid' => '0003700000', 'tarih' => '2026-10-23 21:30:00']); // Toshkentda kecha 23:30 — olinmaydi
        $this->pass(['kartid' => '0003700000', 'tarih' => '2026-10-23 22:30:00']); // Toshkentda bugun 00:30
        $this->pass(['kartid' => '0003700000', 'tarih' => '2026-10-24 07:09:33']); // Toshkentda 09:09:33

        $this->artisan('turnstile:pull')
            ->expectsOutputToContain('2 o‘qildi — 2 yangi')
            ->assertSuccessful();

        $this->assertSame(
            ['2026-10-24 00:30:00', '2026-10-24 09:09:33'],
            Report::query()->withVisitors()->orderBy('occurred_at')->get()
                ->map(fn (Report $report): string => $report->occurred_at->format('Y-m-d H:i:s'))->all(),
        );
    }

    public function test_unreachable_turnstile_database_fails_cleanly(): void
    {
        Schema::connection('turnstile')->drop('gecishareketleri');

        $this->artisan('turnstile:pull')
            ->expectsOutputToContain('Turniket bazasidan o‘qib bo‘lmadi')
            ->assertFailed();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function pass(array $attributes): void
    {
        DB::connection('turnstile')->table('gecishareketleri')->insert([
            'durum' => 1,
            'fuarid' => 12,
            'cihaz_yon' => 'g',
            'cihaz_adi' => '201-GIRIS',
            'hatakodu' => 0,
            'cihaz_id' => '10000001',
            'cihaz_ip' => '192.168.111.201',
            'cihaz_giris' => '1',
            'onayli' => 1,
            ...$attributes,
        ]);
    }
}
