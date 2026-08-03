<?php

namespace Tests\Feature\Telegram;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NotifyEmployeeExpiryCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'telegram.token' => 'test-token',
            'telegram.employee_expiry_warn_days' => 3,
        ]);
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);
        User::factory()->create(['telegram_chat_id' => '111']);
    }

    public function test_warns_once_when_expiry_within_warn_days(): void
    {
        $employee = Employee::factory()->create([
            'name' => 'Ali',
            'valid_to_dt' => now()->addDays(2),
            'expiry_warned_at' => null,
            'expiry_notified_at' => null,
        ]);

        $this->artisan('employees:notify-expiry')->assertSuccessful();
        $this->assertNotNull($employee->fresh()->expiry_warned_at);

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $this->artisan('employees:notify-expiry')->assertSuccessful();
        Http::assertNothingSent();
    }

    public function test_notifies_once_when_expired(): void
    {
        $employee = Employee::factory()->create([
            'name' => 'Vali',
            'valid_to_dt' => now()->subHour(),
            'expiry_warned_at' => now()->subDays(3),
            'expiry_notified_at' => null,
        ]);

        $this->artisan('employees:notify-expiry')->assertSuccessful();
        $this->assertNotNull($employee->fresh()->expiry_notified_at);

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $this->artisan('employees:notify-expiry')->assertSuccessful();
        Http::assertNothingSent();
    }
}
