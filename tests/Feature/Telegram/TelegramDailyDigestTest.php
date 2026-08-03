<?php

namespace Tests\Feature\Telegram;

use App\Models\Category;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Report;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesTelegramAdmin;
use Tests\TestCase;

class TelegramDailyDigestTest extends TestCase
{
    use CreatesTelegramAdmin;
    use RefreshDatabase;

    public function test_daily_digest_counts_late_and_absent(): void
    {
        config(['telegram.token' => 'test-token']);

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $this->createTelegramSuperAdmin(['telegram_chat_id' => '111']);

        $schedule = Schedule::factory()->create([
            'enter_time' => '09:00',
            'exit_time' => '18:00',
        ]);

        $lateEmployee = Employee::factory()->create([
            'name' => 'Kechikkan',
            'schedule_id' => $schedule->id,
            'valid_from_dt' => now()->subMonth(),
            'valid_to_dt' => now()->addMonth(),
        ]);

        $absentEmployee = Employee::factory()->create([
            'name' => 'Kelmagan',
            'schedule_id' => $schedule->id,
            'valid_from_dt' => now()->subMonth(),
            'valid_to_dt' => now()->addMonth(),
        ]);

        $owner = User::factory()->create();
        $category = Category::factory()->create();
        $device = Device::factory()->create([
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'is_online' => false,
            'name' => 'Gate-1',
            'type' => Device::TYPE_ENTRY,
        ]);

        $yesterday = now()->subDay()->startOfDay();

        Report::query()->create([
            'employee_id' => $lateEmployee->id,
            'department_id' => $lateEmployee->department_id,
            'device_id' => $device->id,
            'employee_name' => $lateEmployee->name,
            'type' => Report::TYPE_ENTRY,
            'occurred_at' => $yesterday->copy()->setTime(9, 30),
        ]);

        $this->artisan('telegram:daily-digest')->assertSuccessful();

        Http::assertSent(function ($request) {
            $text = (string) $request['text'];

            return str_contains($request->url(), 'sendMessage')
                && str_contains($text, 'Kechikkan: 1')
                && str_contains($text, 'Kelmagan: 1')
                && str_contains($text, 'Kechikkan')
                && str_contains($text, 'Kelmagan')
                && str_contains($text, 'Gate-1');
        });
    }
}
