<?php

namespace Tests\Feature\Console;

use App\Models\Employee;
use App\Models\Group;
use App\Services\Innoweek\InnoweekSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncInnoweekCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.timezone' => 'Asia/Tashkent',
            'innoweek.api_url' => 'https://innoweek.test',
            'innoweek.api_token' => 'secret-token',
            'innoweek.department' => 'Mehmonlar',
            'innoweek.page_size' => 2,
        ]);
    }

    public function test_pulls_participants_page_by_page_and_resumes_from_cursor(): void
    {
        Http::fake([
            'innoweek.test/api/biocontrol/participants*' => Http::sequence()
                ->push(['data' => [
                    $this->item(['id' => 1, 'card' => '0003700001', 'last_name' => 'Valiyev', 'first_name' => 'Ali', 'profession' => 'Ishtirokchi']),
                    $this->item(['id' => 2, 'card' => '0003700002', 'first_name' => 'Kim', 'last_name' => 'Min-jun', 'gender' => 2, 'country' => ['name_uz' => 'Koreya, Rep.', 'name_en' => 'Korea, Rep.'], 'profession' => 'Homiy']),
                ], 'next_cursor' => 'c1', 'has_more' => true])
                ->push(['data' => [
                    $this->item(['id' => 3, 'card' => '0003700003', 'profession' => 'OAV (Media)', 'registered_at' => '2026-10-26T04:15:00+00:00']),
                ], 'next_cursor' => 'c2', 'has_more' => false])
                // Keyingi ishga tushish: joyida ro‘yxatdan o‘tgan bitta yangi ishtirokchi.
                ->push(['data' => [
                    $this->item(['id' => 4, 'card' => '0003700004', 'first_name' => 'Yangi', 'last_name' => 'Mehmon', 'country' => null]),
                ], 'next_cursor' => 'c3', 'has_more' => false]),
        ]);

        $this->artisan('innoweek:sync')
            ->expectsOutputToContain('3 keldi — "Mehmonlar" bo‘limiga 3 yangi, 0 yangilangan')
            ->assertSuccessful();

        $this->assertSame('c2', Cache::get(InnoweekSyncService::CURSOR_KEY));

        $kim = Employee::query()->withVisitors()->where('card', '0003700002')->firstOrFail();
        $this->assertTrue($kim->is_visitor);
        $this->assertSame('Min-jun Kim', $kim->name);
        $this->assertSame('KR', $kim->country);
        $this->assertSame('Homiy', $kim->group->name);
        $this->assertSame('Mehmonlar', $kim->department->name);

        $media = Employee::query()->withVisitors()->where('card', '0003700003')->firstOrFail();
        $this->assertSame('OAV (Media)', $media->group->name);
        $this->assertSame('2026-10-26 09:15:00', $media->created_at->format('Y-m-d H:i:s'));

        $this->artisan('innoweek:sync')
            ->expectsOutputToContain('1 keldi — "Mehmonlar" bo‘limiga 1 yangi')
            ->assertSuccessful();

        $newcomer = Employee::query()->withVisitors()->where('card', '0003700004')->firstOrFail();
        $this->assertSame('UZ', $newcomer->country);
        $this->assertSame('Ishtirokchi', $newcomer->group->name);
        $this->assertSame(4, Employee::query()->withVisitors()->count());
        $this->assertSame(3, Group::query()->count());

        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer secret-token')
            && str_contains($request->url(), 'cursor=c2'));
    }

    public function test_full_option_restarts_and_updates_existing_visitors(): void
    {
        Cache::forever(InnoweekSyncService::CURSOR_KEY, 'old');
        Employee::factory()->visitor()->create(['card' => '0003700001', 'name' => 'Eski nom']);
        $staff = Employee::factory()->create(['card' => '0003700009', 'name' => 'Oddiy xodim']);

        Http::fake([
            'innoweek.test/*' => Http::response(['data' => [
                $this->item(['id' => 1, 'card' => '0003700001', 'last_name' => 'Yangi', 'first_name' => 'Nom']),
                $this->item(['id' => 9, 'card' => '0003700009']),
                $this->item(['id' => 10, 'card' => '0003700010', 'status' => 'deleted']),
            ], 'next_cursor' => 'c9', 'has_more' => false]),
        ]);

        $this->artisan('innoweek:sync', ['--full' => true])
            ->expectsOutputToContain('3 keldi — "Mehmonlar" bo‘limiga 0 yangi, 1 yangilangan, 1 oddiy xodim kartasi, 1 o‘chirilgan.')
            ->assertSuccessful();

        Http::assertSent(fn (Request $request): bool => ! str_contains($request->url(), 'cursor='));
        $this->assertSame('Yangi Nom', Employee::query()->withVisitors()->where('card', '0003700001')->value('name'));
        $this->assertSame('Oddiy xodim', $staff->fresh()->name);
        $this->assertFalse(Employee::query()->withVisitors()->where('card', '0003700010')->exists());
    }

    public function test_api_error_and_missing_config_fail_cleanly(): void
    {
        Http::fake(['innoweek.test/*' => Http::response('Unauthorized', 401)]);

        $this->artisan('innoweek:sync')
            ->expectsOutputToContain('Innoweek API xatosi: HTTP 401')
            ->assertFailed();

        config(['innoweek.api_token' => '']);

        $this->artisan('innoweek:sync')
            ->expectsOutputToContain('INNOWEEK_API_URL yoki INNOWEEK_API_TOKEN sozlanmagan')
            ->assertFailed();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function item(array $overrides): array
    {
        return [
            'id' => 1,
            'card' => '0003700001',
            'first_name' => 'Ism',
            'last_name' => 'Familiya',
            'middle_name' => null,
            'position' => 'Talaba',
            'gender' => 1,
            'country' => ['name_uz' => "O'zbekiston", 'name_en' => 'Uzbekistan'],
            'profession' => 'Ishtirokchi',
            'year' => 2026,
            'status' => 'active',
            'registered_at' => '2026-10-25T10:00:00+05:00',
            ...$overrides,
        ];
    }
}
