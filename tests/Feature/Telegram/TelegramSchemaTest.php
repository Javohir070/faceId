<?php

namespace Tests\Feature\Telegram;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TelegramSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_store_telegram_chat_id(): void
    {
        $user = User::factory()->create(['telegram_chat_id' => '123456']);

        $this->assertSame('123456', $user->fresh()->telegram_chat_id);
    }

    public function test_employee_expiry_flags_reset_when_valid_to_extended(): void
    {
        $employee = Employee::factory()->create([
            'valid_to_dt' => now()->subDay(),
            'expiry_warned_at' => now()->subDays(2),
            'expiry_notified_at' => now()->subDay(),
        ]);

        $employee->update(['valid_to_dt' => now()->addYear()]);

        $employee->refresh();
        $this->assertNull($employee->expiry_warned_at);
        $this->assertNull($employee->expiry_notified_at);
    }

    public function test_telegram_config_keys_exist(): void
    {
        $this->assertIsString(config('telegram.token'));
        $this->assertIsString(config('telegram.username'));
        $this->assertIsString(config('telegram.webhook_secret'));
        $this->assertSame(3, (int) config('telegram.employee_expiry_warn_days'));
    }
}
