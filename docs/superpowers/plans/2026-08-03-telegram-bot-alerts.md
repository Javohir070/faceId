# Telegram Bot Alerts Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Telegram bot orqali mavjud userni bog‘lash (`telegram_chat_id`), qurilma oflayn o‘tishida va employee muddati (3 kun / tugagan) bo‘yicha barcha bog‘langan userlarga xabar yuborish.

**Architecture:** Tashqi SDK yo‘q. `TelegramBotService` HTTP orqali Bot API chaqiradi; webhook `POST /api/telegram/webhook` + secret middleware; login conversation Cache’da; oflayn alert `HikvisionDeviceService::refreshOnlineStatus` ichida true→false o‘tishida; employee alert kunlik `employees:notify-expiry` command orqali.

**Tech Stack:** Laravel 12, `Illuminate\Support\Facades\Http`, Cache, PHPUnit, Telegram Bot API.

**Spec:** `docs/superpowers/specs/2026-08-03-telegram-bot-alerts-design.md`

## Global Constraints

- Yangi user bot orqali yaratilmaydi — faqat mavjud email+password login.
- Xabarnomalar `telegram_chat_id` bo‘lgan barcha userlarga.
- Qurilma oflayn: faqat `is_online` true → false bir marta.
- Employee: 3 kun oldin bir marta + muddat tugaganda bir marta (`expiry_warned_at` / `expiry_notified_at`).
- Webhook only; tashqi Telegram SDK paket qo‘shilmaydi.
- Env: `TELEGRAM_BOT_TOKEN`, `TELEGRAM_BOT_USERNAME`, `TELEGRAM_WEBHOOK_SECRET`.
- Parol hech qachon logga yozilmaydi.
- Token bo‘sh bo‘lsa notifier no-op (app ishlashda qoladi).

## File map

| File | Responsibility |
|------|----------------|
| `config/telegram.php` | Token, username, webhook secret, warn days |
| `database/migrations/*_add_telegram_chat_id_to_users_table.php` | `users.telegram_chat_id` |
| `database/migrations/*_add_expiry_notification_flags_to_employees_table.php` | `expiry_warned_at`, `expiry_notified_at` |
| `app/Models/User.php` | fillable `telegram_chat_id` |
| `app/Models/Employee.php` | fillable/casts + `valid_to_dt` o‘zgarganda flag tozalash |
| `app/Services/Telegram/TelegramBotService.php` | `sendMessage`, `setWebhook` |
| `app/Services/Telegram/TelegramNotifier.php` | barcha bog‘langan userlarga yuborish |
| `app/Services/Telegram/TelegramAuthHandler.php` | `/start`, email, password, `/unlink` |
| `app/Http/Middleware/VerifyTelegramWebhookSecret.php` | secret header tekshiruvi |
| `app/Http/Controllers/TelegramWebhookController.php` | update → handler |
| `app/Console/Commands/NotifyEmployeeExpiryCommand.php` | kunlik expiry scan |
| `app/Console/Commands/SetTelegramWebhookCommand.php` | webhook o‘rnatish |
| `routes/api.php` | webhook route |
| `routes/console.php` | schedule `employees:notify-expiry` |
| `bootstrap/app.php` | middleware alias |
| `app/Services/Hikvision/HikvisionDeviceService.php` | oflayn o‘tishda notify |
| `.env.example` | telegram env keys |

---

### Task 1: Config, migrations, models

**Files:**
- Create: `config/telegram.php`
- Create: `database/migrations/2026_08_03_100000_add_telegram_chat_id_to_users_table.php`
- Create: `database/migrations/2026_08_03_100100_add_expiry_notification_flags_to_employees_table.php`
- Modify: `app/Models/User.php`
- Modify: `app/Models/Employee.php`
- Modify: `.env.example`
- Test: `tests/Feature/Telegram/TelegramSchemaTest.php`

**Interfaces:**
- Produces: `config('telegram.token')`, `config('telegram.username')`, `config('telegram.webhook_secret')`, `config('telegram.employee_expiry_warn_days')` (int, default 3); `User.telegram_chat_id`; `Employee.expiry_warned_at`, `Employee.expiry_notified_at`; Employee `updating` da `valid_to_dt` kelajakka uzaytirilsa flaglar null.

- [ ] **Step 1: Write the failing schema/model test**

Create `tests/Feature/Telegram/TelegramSchemaTest.php`:

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=TelegramSchemaTest`
Expected: FAIL (missing config/columns)

- [ ] **Step 3: Create config and migrations; update models and .env.example**

`config/telegram.php`:

```php
<?php

return [
    'token' => env('TELEGRAM_BOT_TOKEN', ''),
    'username' => env('TELEGRAM_BOT_USERNAME', ''),
    'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET', ''),
    'employee_expiry_warn_days' => max((int) env('TELEGRAM_EMPLOYEE_EXPIRY_WARN_DAYS', 3), 1),
];
```

Migration users:

```php
Schema::table('users', function (Blueprint $table) {
    $table->string('telegram_chat_id')->nullable()->unique()->after('password');
});
```

Migration employees:

```php
Schema::table('employees', function (Blueprint $table) {
    $table->timestamp('expiry_warned_at')->nullable()->after('valid_to_dt');
    $table->timestamp('expiry_notified_at')->nullable()->after('expiry_warned_at');
});
```

`User` fillable ga `'telegram_chat_id'` qo‘shing.

`Employee` fillable ga `'expiry_warned_at', 'expiry_notified_at'` qo‘shing; casts ga datetime; `booted()`:

```php
protected static function booted(): void
{
    static::updating(function (Employee $employee): void {
        if (! $employee->isDirty('valid_to_dt')) {
            return;
        }

        $newValidTo = $employee->valid_to_dt;

        if ($newValidTo !== null && $newValidTo->isFuture()) {
            $employee->expiry_warned_at = null;
            $employee->expiry_notified_at = null;
        }
    });
}
```

`.env.example` oxiriga:

```
TELEGRAM_BOT_TOKEN=
TELEGRAM_BOT_USERNAME=
TELEGRAM_WEBHOOK_SECRET=
TELEGRAM_EMPLOYEE_EXPIRY_WARN_DAYS=3
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=TelegramSchemaTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add config/telegram.php database/migrations/2026_08_03_100000_add_telegram_chat_id_to_users_table.php database/migrations/2026_08_03_100100_add_expiry_notification_flags_to_employees_table.php app/Models/User.php app/Models/Employee.php .env.example tests/Feature/Telegram/TelegramSchemaTest.php
git commit -m "Add Telegram schema, config, and model fields"
```

---

### Task 2: TelegramBotService + TelegramNotifier

**Files:**
- Create: `app/Services/Telegram/TelegramBotService.php`
- Create: `app/Services/Telegram/TelegramNotifier.php`
- Test: `tests/Unit/Telegram/TelegramNotifierTest.php`

**Interfaces:**
- Consumes: `config('telegram.token')`, `User.telegram_chat_id`
- Produces:
  - `TelegramBotService::sendMessage(string $chatId, string $text): bool`
  - `TelegramBotService::setWebhook(string $url, string $secretToken): bool`
  - `TelegramNotifier::notifyAllLinkedUsers(string $text): int` (yuborilgan muvaffaqiyatli soni)

- [ ] **Step 1: Write the failing notifier test**

Create `tests/Unit/Telegram/TelegramNotifierTest.php`:

```php
<?php

namespace Tests\Unit\Telegram;

use App\Models\User;
use App\Services\Telegram\TelegramNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramNotifierTest extends TestCase
{
    use RefreshDatabase;

    public function test_notify_all_linked_users_sends_to_each_chat(): void
    {
        config(['telegram.token' => 'test-token']);

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        User::factory()->create(['telegram_chat_id' => '111']);
        User::factory()->create(['telegram_chat_id' => '222']);
        User::factory()->create(['telegram_chat_id' => null]);

        $sent = app(TelegramNotifier::class)->notifyAllLinkedUsers('Salom');

        $this->assertSame(2, $sent);
        Http::assertSentCount(2);
    }

    public function test_notify_is_noop_when_token_missing(): void
    {
        config(['telegram.token' => '']);

        Http::fake();

        User::factory()->create(['telegram_chat_id' => '111']);

        $sent = app(TelegramNotifier::class)->notifyAllLinkedUsers('Salom');

        $this->assertSame(0, $sent);
        Http::assertNothingSent();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=TelegramNotifierTest`
Expected: FAIL (class not found)

- [ ] **Step 3: Implement BotService and Notifier**

`app/Services/Telegram/TelegramBotService.php`:

```php
<?php

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramBotService
{
    public function sendMessage(string $chatId, string $text): bool
    {
        $token = (string) config('telegram.token');

        if ($token === '') {
            Log::warning('Telegram: token bo\'sh, xabar yuborilmadi');

            return false;
        }

        try {
            $response = Http::timeout(15)
                ->post("https://api.telegram.org/bot{$token}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $text,
                ]);

            if (! $response->successful() || ! ($response->json('ok') ?? false)) {
                Log::warning('Telegram: sendMessage muvaffaqiyatsiz', [
                    'chat_id' => $chatId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('Telegram: sendMessage xato', [
                'chat_id' => $chatId,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function setWebhook(string $url, string $secretToken): bool
    {
        $token = (string) config('telegram.token');

        if ($token === '') {
            return false;
        }

        $payload = ['url' => $url];

        if ($secretToken !== '') {
            $payload['secret_token'] = $secretToken;
        }

        $response = Http::timeout(15)
            ->post("https://api.telegram.org/bot{$token}/setWebhook", $payload);

        return $response->successful() && ($response->json('ok') ?? false);
    }
}
```

`app/Services/Telegram/TelegramNotifier.php`:

```php
<?php

namespace App\Services\Telegram;

use App\Models\User;

class TelegramNotifier
{
    public function __construct(
        private readonly TelegramBotService $bot,
    ) {}

    public function notifyAllLinkedUsers(string $text): int
    {
        if ((string) config('telegram.token') === '') {
            return 0;
        }

        $chatIds = User::query()
            ->whereNotNull('telegram_chat_id')
            ->where('telegram_chat_id', '!=', '')
            ->pluck('telegram_chat_id');

        $sent = 0;

        foreach ($chatIds as $chatId) {
            if ($this->bot->sendMessage((string) $chatId, $text)) {
                $sent++;
            }
        }

        return $sent;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=TelegramNotifierTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/Telegram/TelegramBotService.php app/Services/Telegram/TelegramNotifier.php tests/Unit/Telegram/TelegramNotifierTest.php
git commit -m "Add Telegram bot service and notifier"
```

---

### Task 3: Webhook middleware, controller, auth handler

**Files:**
- Create: `app/Http/Middleware/VerifyTelegramWebhookSecret.php`
- Create: `app/Http/Controllers/TelegramWebhookController.php`
- Create: `app/Services/Telegram/TelegramAuthHandler.php`
- Modify: `routes/api.php`
- Modify: `bootstrap/app.php` (alias `telegram.webhook`)
- Test: `tests/Feature/Telegram/TelegramWebhookAuthTest.php`

**Interfaces:**
- Consumes: `TelegramBotService::sendMessage`, `User` email/password
- Produces: `TelegramAuthHandler::handle(array $message): void`; route `POST /api/telegram/webhook`

- [ ] **Step 1: Write the failing webhook auth tests**

Create `tests/Feature/Telegram/TelegramWebhookAuthTest.php`:

```php
<?php

namespace Tests\Feature\Telegram;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramWebhookAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'telegram.token' => 'test-token',
            'telegram.webhook_secret' => 'secret-123',
        ]);

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);
    }

    private function webhook(array $payload, ?string $secret = 'secret-123')
    {
        $headers = $secret === null
            ? []
            : ['X-Telegram-Bot-Api-Secret-Token' => $secret];

        return $this->postJson('/api/telegram/webhook', $payload, $headers);
    }

    private function message(string $chatId, string $text): array
    {
        return [
            'update_id' => 1,
            'message' => [
                'message_id' => 1,
                'chat' => ['id' => (int) $chatId, 'type' => 'private'],
                'text' => $text,
                'from' => ['id' => (int) $chatId],
            ],
        ];
    }

    public function test_rejects_invalid_webhook_secret(): void
    {
        $this->webhook($this->message('100', '/start'), 'wrong')->assertForbidden();
    }

    public function test_login_success_stores_telegram_chat_id(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $this->webhook($this->message('999', '/start'))->assertOk();
        $this->webhook($this->message('999', 'admin@example.com'))->assertOk();
        $this->webhook($this->message('999', 'password'))->assertOk();

        $this->assertSame('999', $user->fresh()->telegram_chat_id);
    }

    public function test_wrong_password_does_not_store_chat_id(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $this->webhook($this->message('999', '/start'))->assertOk();
        $this->webhook($this->message('999', 'admin@example.com'))->assertOk();
        $this->webhook($this->message('999', 'wrong-pass'))->assertOk();

        $this->assertNull($user->fresh()->telegram_chat_id);
    }

    public function test_unlink_clears_chat_id(): void
    {
        $user = User::factory()->create(['telegram_chat_id' => '999']);

        $this->webhook($this->message('999', '/unlink'))->assertOk();

        $this->assertNull($user->fresh()->telegram_chat_id);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=TelegramWebhookAuthTest`
Expected: FAIL (404 / missing classes)

- [ ] **Step 3: Implement middleware, handler, controller, routes**

`VerifyTelegramWebhookSecret`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyTelegramWebhookSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('telegram.webhook_secret');
        $provided = (string) $request->header('X-Telegram-Bot-Api-Secret-Token', '');

        if ($expected === '' || ! hash_equals($expected, $provided)) {
            abort(403);
        }

        return $next($request);
    }
}
```

`TelegramAuthHandler` — asosiy mantiq:

- Cache key: `telegram:auth:{chatId}`, TTL 15 daqiqa, value: `['step' => 'awaiting_email'|'awaiting_password', 'email' => ?string]`
- `/start`: agar `User` with chat_id → “Ulangan: {email}. Chiqish: /unlink”; aks holda state `awaiting_email`, “Email kiriting”
- `/unlink`: chat_id null, cache forget, “Uzildi”
- `awaiting_email`: email filter_var; invalid → “Email noto‘g‘ri”; valid → state password, “Parol kiriting”
- `awaiting_password`: find user by email, Hash::check; success → boshqa userlardan shu chat_id ni null qilish, joriyga yozish, cache forget, “Muvaffaqiyatli ulandingiz”; fail → cache forget, “Email yoki parol noto‘g‘ri”
- Noma’lum matn (state yo‘q): “Boshlash uchun /start yuboring”
- Parolni Log:: ga yozmang

`TelegramWebhookController`:

```php
public function __invoke(Request $request, TelegramAuthHandler $handler): JsonResponse
{
    $message = $request->input('message');

    if (is_array($message)) {
        $handler->handle($message);
    }

    return response()->json(['ok' => true]);
}
```

`routes/api.php` ga qo‘shing:

```php
use App\Http\Controllers\TelegramWebhookController;

Route::post('/telegram/webhook', TelegramWebhookController::class)
    ->middleware('telegram.webhook');
```

`bootstrap/app.php` alias:

```php
'telegram.webhook' => \App\Http\Middleware\VerifyTelegramWebhookSecret::class,
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=TelegramWebhookAuthTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Http/Middleware/VerifyTelegramWebhookSecret.php app/Http/Controllers/TelegramWebhookController.php app/Services/Telegram/TelegramAuthHandler.php routes/api.php bootstrap/app.php tests/Feature/Telegram/TelegramWebhookAuthTest.php
git commit -m "Add Telegram webhook login and unlink flow"
```

---

### Task 4: Device offline Telegram alert

**Files:**
- Modify: `app/Services/Hikvision/HikvisionDeviceService.php` (`refreshOnlineStatus` catch bloki)
- Test: `tests/Feature/Telegram/DeviceOfflineTelegramAlertTest.php`

**Interfaces:**
- Consumes: `TelegramNotifier::notifyAllLinkedUsers(string $text): int`
- Produces: true→false o‘tishida bir marta notify

- [ ] **Step 1: Write the failing alert test**

Create `tests/Feature/Telegram/DeviceOfflineTelegramAlertTest.php`:

```php
<?php

namespace Tests\Feature\Telegram;

use App\Models\Category;
use App\Models\Device;
use App\Models\User;
use App\Services\Hikvision\HikvisionDeviceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DeviceOfflineTelegramAlertTest extends TestCase
{
    use RefreshDatabase;

    public function test_notifies_once_when_device_transitions_to_offline(): void
    {
        config([
            'telegram.token' => 'test-token',
            'hikvision.ping_offline_after_failures' => 1,
        ]);

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true], 200),
            '*' => Http::response('down', 500),
        ]);

        User::factory()->create(['telegram_chat_id' => '111']);
        $owner = User::factory()->create();
        $category = Category::factory()->create();
        $device = Device::factory()->create([
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'name' => 'Kirish-1',
            'ip_address' => '192.168.1.50',
            'port' => 80,
            'is_online' => true,
        ]);

        $service = app(HikvisionDeviceService::class);
        $service->refreshOnlineStatus($device);

        $device->refresh();
        $this->assertFalse($device->is_online);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'sendMessage')
                && str_contains((string) $request['text'], 'Kirish-1')
                && str_contains((string) $request['text'], 'ishlamayapti');
        });

        // Allaqachon oflayn — ikkinchi marta yubormasin
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true], 200),
            '*' => Http::response('down', 500),
        ]);

        $service->refreshOnlineStatus($device->fresh());

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'sendMessage'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=DeviceOfflineTelegramAlertTest`
Expected: FAIL (sendMessage assert)

- [ ] **Step 3: Hook notifier into refreshOnlineStatus**

`HikvisionDeviceService::refreshOnlineStatus` catch ichida, save dan oldin:

```php
$wasOnline = (bool) $device->is_online;
// ... failures / updates ...
if ($failures >= $threshold) {
    $updates['is_online'] = false;
}

$device->forceFill($updates)->save();

if ($wasOnline && ($updates['is_online'] ?? true) === false) {
    app(TelegramNotifier::class)->notifyAllLinkedUsers(
        sprintf(
            "⚠️ Qurilma ishlamayapti (oflayn)\nNomi: %s\nIP: %s\nTur: %s",
            $device->name,
            $device->ip_address,
            $device->typeLabel(),
        )
    );
}
```

Constructor injection o‘rniga `app(TelegramNotifier::class)` ishlatish mavjud service constructorini buzmaslik uchun qulay; agar constructor DI afzal bo‘lsa — optional nullable param bilan ham mumkin, lekin `app()` shu vazifa uchun yetarli va mavjud testlarga kam ta’sir qiladi.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=DeviceOfflineTelegramAlertTest`
Expected: PASS

Also run: `php artisan test --filter=PingDevicesCommandTest`
Expected: PASS (regressiya)

- [ ] **Step 5: Commit**

```bash
git add app/Services/Hikvision/HikvisionDeviceService.php tests/Feature/Telegram/DeviceOfflineTelegramAlertTest.php
git commit -m "Notify Telegram users when device goes offline"
```

---

### Task 5: Employee expiry notify command + schedule

**Files:**
- Create: `app/Console/Commands/NotifyEmployeeExpiryCommand.php`
- Create: `app/Services/Telegram/EmployeeExpiryNotifier.php` (ixtiyoriy ajratish — command ichida ham bo‘lishi mumkin; tavsiya: alohida servis)
- Modify: `routes/console.php`
- Test: `tests/Feature/Telegram/NotifyEmployeeExpiryCommandTest.php`

**Interfaces:**
- Consumes: `TelegramNotifier::notifyAllLinkedUsers`, `config('telegram.employee_expiry_warn_days')`
- Produces: artisan `employees:notify-expiry`; daily schedule 08:00

- [ ] **Step 1: Write the failing command test**

Create `tests/Feature/Telegram/NotifyEmployeeExpiryCommandTest.php`:

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=NotifyEmployeeExpiryCommandTest`
Expected: FAIL

- [ ] **Step 3: Implement EmployeeExpiryNotifier + command + schedule**

`EmployeeExpiryNotifier::notifyDue(): array{warned:int, expired:int}`:

- Warn query: `valid_to_dt > now()` AND `valid_to_dt <= now()->addDays(warnDays)` AND `expiry_warned_at` null  
  Text: `"⏰ Xodim muddati tugashiga kam qoldi\nIsm: ...\nKarta: ...\nTugash: ..."`  
  So‘ng `expiry_warned_at = now()`
- Expired query: `valid_to_dt < now()` AND `expiry_notified_at` null  
  Text: `"❌ Xodim muddati tugadi\nIsm: ...\nKarta: ...\nTugash: ..."`  
  So‘ng `expiry_notified_at = now()`

Command:

```php
protected $signature = 'employees:notify-expiry';
protected $description = 'valid_to_dt yaqinlashgan/tugagan xodimlar haqida Telegram xabar yuboradi';
```

`routes/console.php`:

```php
Schedule::command('employees:notify-expiry')
    ->dailyAt('08:00')
    ->withoutOverlapping();
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=NotifyEmployeeExpiryCommandTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/NotifyEmployeeExpiryCommand.php app/Services/Telegram/EmployeeExpiryNotifier.php routes/console.php tests/Feature/Telegram/NotifyEmployeeExpiryCommandTest.php
git commit -m "Add employee expiry Telegram notifications"
```

---

### Task 6: Set webhook artisan command

**Files:**
- Create: `app/Console/Commands/SetTelegramWebhookCommand.php`
- Test: `tests/Feature/Telegram/SetTelegramWebhookCommandTest.php`

**Interfaces:**
- Consumes: `TelegramBotService::setWebhook(string $url, string $secretToken): bool`
- Produces: `telegram:set-webhook {url?}`

- [ ] **Step 1: Write the failing command test**

```php
<?php

namespace Tests\Feature\Telegram;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SetTelegramWebhookCommandTest extends TestCase
{
    public function test_sets_webhook_using_app_url_by_default(): void
    {
        config([
            'telegram.token' => 'test-token',
            'telegram.webhook_secret' => 'secret-123',
            'app.url' => 'https://example.test',
        ]);

        Http::fake([
            'https://api.telegram.org/bottest-token/setWebhook' => Http::response(['ok' => true], 200),
        ]);

        $this->artisan('telegram:set-webhook')
            ->assertSuccessful();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'setWebhook')
                && $request['url'] === 'https://example.test/api/telegram/webhook'
                && $request['secret_token'] === 'secret-123';
        });
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SetTelegramWebhookCommandTest`
Expected: FAIL

- [ ] **Step 3: Implement command**

```php
protected $signature = 'telegram:set-webhook {url?}';
protected $description = 'Telegram bot webhook URL ni o‘rnatadi';

public function handle(TelegramBotService $bot): int
{
    $url = $this->argument('url')
        ?: rtrim((string) config('app.url'), '/').'/api/telegram/webhook';
    $secret = (string) config('telegram.webhook_secret');

    if ((string) config('telegram.token') === '') {
        $this->error('TELEGRAM_BOT_TOKEN bo‘sh');

        return self::FAILURE;
    }

    if (! $bot->setWebhook($url, $secret)) {
        $this->error('Webhook o‘rnatilmadi');

        return self::FAILURE;
    }

    $this->info("Webhook o‘rnatildi: {$url}");

    return self::SUCCESS;
}
```

- [ ] **Step 4: Run full Telegram + related suite**

Run: `php artisan test --filter=Telegram`
Expected: PASS

Run: `php artisan test --filter=PingDevicesCommandTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/SetTelegramWebhookCommand.php tests/Feature/Telegram/SetTelegramWebhookCommandTest.php
git commit -m "Add telegram:set-webhook artisan command"
```

---

## Spec coverage checklist

| Spec requirement | Task |
|------------------|------|
| Config + env keys | Task 1 |
| `users.telegram_chat_id` | Task 1 |
| Employee expiry flags + reset on extend | Task 1 |
| BotService + Notifier | Task 2 |
| Webhook + secret middleware | Task 3 |
| Login email/password + unlink | Task 3 |
| Device offline once on transition | Task 4 |
| Employee warn 3 days + expired once | Task 5 |
| Daily schedule | Task 5 |
| `telegram:set-webhook` | Task 6 |
| No new user registration / no SDK | Global + all tasks |

## Manual smoke (implementation oxirida)

1. `.env` da `TELEGRAM_BOT_TOKEN`, `TELEGRAM_BOT_USERNAME`, `TELEGRAM_WEBHOOK_SECRET` to‘ldirilganini tekshirish.
2. Public HTTPS bo‘lsa: `php artisan telegram:set-webhook`
3. Botga `/start` → email → password → ulanish.
4. Qurilmani oflayn qilib ping thresholddan o‘tkazish — Telegram xabar.
5. Test employee `valid_to_dt` ni 2 kunga qo‘yib `php artisan employees:notify-expiry`.
