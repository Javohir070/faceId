<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

/**
 * Barcha matnlar o‘zbekcha: Laravel'ning standart validatsiya va login xabarlari ham (lang/uz).
 * Formada o‘z `messages()` bo‘lmagan qoidalar shu xabarlarga tushadi.
 */
class UzbekValidationMessagesTest extends TestCase
{
    use InteractsWithPermissions;
    use RefreshDatabase;

    public function test_app_locale_is_uzbek(): void
    {
        $this->assertSame('uz', app()->getLocale());
    }

    public function test_default_rule_messages_use_uzbek_text_and_field_names(): void
    {
        $this->actingAs($this->createUserWithPermissions())
            ->from(route('admin.notes.create'))
            ->post(route('admin.notes.store'), [])
            ->assertSessionHasErrors([
                'employee_id' => 'Xodim maydoni to‘ldirilishi shart.',
                'type' => 'Tur maydoni to‘ldirilishi shart.',
                'dt' => 'Sana maydoni to‘ldirilishi shart.',
                'note' => 'Izoh maydoni to‘ldirilishi shart.',
            ]);
    }

    public function test_forms_without_own_messages_show_no_english(): void
    {
        $admin = $this->createUserWithPermissions();
        $routes = [
            'admin.notes.store',
            'admin.groups.store',
            'admin.users.store',
            'admin.roles.store',
            'admin.devices.store',
            'admin.employees.store',
        ];

        foreach ($routes as $name) {
            $this->actingAs($admin)->from('/admin')->post(route($name), []);

            $messages = collect(session('errors')?->getBag('default')->all() ?? []);

            $this->assertNotEmpty($messages, "{$name}: validatsiya xatosi kutilgan edi");
            foreach ($messages as $message) {
                $this->assertDoesNotMatchRegularExpression(
                    '/\b(the|field|is required|must|invalid|selected)\b/i',
                    $message,
                    "{$name}: inglizcha xabar — {$message}",
                );
            }

            session()->forget('errors');
        }
    }

    public function test_failed_login_message_is_uzbek(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'notogri-parol'])
            ->assertSessionHasErrors(['email' => 'Email yoki parol noto‘g‘ri.']);
    }

    public function test_report_filter_with_reversed_dates_reports_uzbek_error(): void
    {
        $this->actingAs($this->createUserWithPermissions([config('menu_permissions.resources')['reports']]))
            ->from(route('admin.reports.index'))
            ->get(route('admin.reports.index', ['date_from' => '2026-11-12', 'date_to' => '2026-11-01']))
            ->assertRedirect(route('admin.reports.index'))
            ->assertSessionHasErrors([
                'date_to' => 'Tugash sanasi boshlanish sanasi bilan bir xil yoki undan keyingi sana bo‘lishi kerak.',
            ]);
    }
}
